#!/usr/bin/env python3
"""Own one disposable database run, with durable mounts and fail-closed recovery."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time


class Hold(Exception):
    pass


def fingerprint(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def require(condition, message):
    if not condition:
        raise Hold(message)


def container_identity(value):
    identity = {key: value[key] for key in ('Id', 'Name', 'Created', 'Image', 'Config', 'HostConfig', 'Mounts')}
    identity['HostConfig'] = dict(identity['HostConfig'])
    # Docker normalizes this unset Boolean from false to null at first start.
    # Both retain OOM killing; true is deliberately a different identity.
    if identity['HostConfig'].get('OomKillDisable') is None:
        identity['HostConfig']['OomKillDisable'] = False
    return fingerprint(identity)


def volume_identity(value):
    return fingerprint({key: value.get(key) for key in ('Name', 'CreatedAt', 'Driver', 'Mountpoint', 'Labels', 'Options', 'Scope')})


class Lifecycle:
    def __init__(self, args):
        self.args = args
        self.path = Path(args.receipt).resolve()
        self.state = json.loads(self.path.read_text()) if self.path.exists() else None

    def save(self):
        fd, name = tempfile.mkstemp(prefix='.database-', dir=self.path.parent)
        try:
            with os.fdopen(fd, 'w') as output:
                json.dump(self.state, output, indent=2)
                output.write('\n')
                output.flush()
                os.fsync(output.fileno())
            os.replace(name, self.path)
            directory = os.open(self.path.parent, os.O_RDONLY)
            try:
                os.fsync(directory)
            finally:
                os.close(directory)
        finally:
            if os.path.exists(name):
                os.unlink(name)

    def run(self, *args):
        result = subprocess.run([self.args.docker, *args], capture_output=True, timeout=120)
        self.state.setdefault('commands', []).append({'operation': list(args[:2]), 'argv_sha256': fingerprint(args),
            'exit': result.returncode, 'stdout_sha256': hashlib.sha256(result.stdout).hexdigest(),
            'stderr_sha256': hashlib.sha256(result.stderr).hexdigest()})
        self.save()
        require(result.returncode == 0, 'Docker command failed; retain exact resources and inspect receipt')
        return result.stdout.decode().strip()

    def inspect(self, kind, identity):
        return json.loads(self.run(kind, 'inspect', identity))[0]

    def containers(self):
        ids = self.run('container', 'ls', '--all', '--no-trunc', '--quiet').splitlines()
        return [self.inspect('container', identity) for identity in ids]

    def start(self):
        require(self.state is None, 'receipt already exists; never replay creation, resume cleanup explicitly')
        self.state = {'version': 1, 'run': self.args.run, 'source': str(Path(__file__).resolve()),
            'source_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),
            'authority': 'owning disposable database caller; newly created fixture data only',
            'data': 'disposable', 'containers': [], 'volumes': [], 'network': None, 'attempts': [],
            'images': [], 'caches': {'disposition': 'retain', 'reason': 'shared daemon builder cache; no exact cache deletion capability or private builder created'},
            'source_head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()}
        self.save()
        network_name = 'fight-common-test-network-' + self.args.run
        require(network_name not in self.run('network', 'ls', '--format', '{{.Name}}').splitlines(), 'network name exists')
        self.state['creation_intent'] = {'network': network_name, 'run': self.args.run, 'time': time.time()}
        self.save()
        network_id = self.run('network', 'create', network_name)
        network = self.inspect('network', network_id)
        self.state['network'] = {'id': network_id, 'name': network_name, 'created': network['Created']}
        self.save()
        for engine, image, variables, health in (
            ('mysql', 'mysql:8.4.11', ['MYSQL_DATABASE=fight_common', 'MYSQL_USER=fight_common', 'MYSQL_PASSWORD=fight_common', 'MYSQL_ROOT_PASSWORD=root'], 'mysqladmin ping --host=127.0.0.1 --user=root --password=root'),
            ('postgresql', 'postgres:17', ['POSTGRES_DB=fight_common', 'POSTGRES_USER=fight_common', 'POSTGRES_PASSWORD=fight_common'], 'pg_isready --username=fight_common --dbname=fight_common')):
            name = 'fight-common-test-' + engine + '-' + self.args.run
            before_images = self.run('image', 'ls', '--no-trunc', '--quiet').splitlines()
            require(not any(item['Name'] == '/' + name for item in self.containers()), 'container name exists')
            self.state['creation_intent'][engine] = {'name': name, 'image': image, 'time': time.time()}
            self.save()
            options = ['container', 'create', '--name', name, '--network', network_id, '--network-alias', engine,
                '--health-cmd=' + health, '--health-interval=2s', '--health-timeout=5s', '--health-retries=30']
            for value in variables:
                options += ['--env', value]
            identity = self.run(*options, image)
            current = self.inspect('container', identity)
            entry = {'id': identity, 'name': current['Name'], 'identity': container_identity(current), 'mounts': current['Mounts'], 'created_observation': current}
            self.state['containers'].append(entry)
            observed_image = self.inspect('image', current['Image'])
            self.state['images'].append({'id': current['Image'], 'size_bytes': observed_image['Size'],
                'use': 'reused' if current['Image'] in before_images else 'pulled', 'disposition': 'retain',
                'reason': 'base image and shared layers are not disposable by workload association'})
            # Capture image-declared anonymous mounts while the creating container still exists.
            for mount in current['Mounts']:
                require(mount['Type'] == 'volume', 'unexpected non-volume database mount')
                observed = self.inspect('volume', mount['Name'])
                self.state['volumes'].append({'name': mount['Name'], 'creator': identity,
                    'destination': mount['Destination'], 'identity': volume_identity(observed), 'data': 'disposable'})
            self.save()
            self.run('container', 'start', identity)
            deadline = time.monotonic() + 90
            while time.monotonic() < deadline:
                current = self.inspect('container', identity)
                require(container_identity(current) == entry['identity'], 'database container identity changed')
                health_state = current['State'].get('Health', {}).get('Status')
                if health_state == 'healthy':
                    break
                require(health_state == 'starting', 'database failed health check')
                time.sleep(2)
            else:
                raise Hold('database health deadline exceeded')

    def cleanup(self):
        require(self.state is not None and self.state['version'] == 1, 'creation receipt unavailable; retain unknown resources')
        attempt = {'time': time.time(), 'results': [], 'complete': False}
        self.state['attempts'].append(attempt)
        self.save()
        def effect(kind, identity, operation):
            result = {'kind': kind, 'identity': identity, 'status': 'pending'}
            attempt['results'].append(result)
            self.save()
            try:
                result['status'] = operation()
            except (Hold, OSError, ValueError, KeyError, subprocess.TimeoutExpired) as error:
                result.update(status='held', reason=str(error) if isinstance(error, Hold) else 'inspection unavailable')
            self.save()
        for entry in self.state['containers']:
            def remove_container(entry=entry):
                inventory = self.containers()
                current = next((item for item in inventory if item['Id'] == entry['id']), None)
                if current is None:
                    require(not any(item['Name'] == entry['name'] for item in inventory), 'container recreated under recorded name')
                    return 'absent'
                require(container_identity(current) == entry['identity'], 'container configuration/mounts changed')
                # Another container joining this disposable network is a consumer hold, never shutdown authority.
                owned = {item['id'] for item in self.state['containers']}
                network_id = self.state['network']['id']
                require(not any(item['Id'] not in owned and any(net.get('NetworkID') == network_id or name == self.state['network']['name'] for name, net in item['NetworkSettings']['Networks'].items()) for item in inventory), 'shared/stopped network consumer')
                if current['State']['Running']:
                    self.run('container', 'stop', '--time', '10', entry['id'])
                current = self.inspect('container', entry['id'])
                require(container_identity(current) == entry['identity'] and not current['State']['Running'], 'container changed/restarted before removal')
                inventory = self.containers()
                require(not any(item['Id'] not in owned and self.state['network']['name'] in item['NetworkSettings']['Networks'] for item in inventory), 'new/stopped shared consumer before removal')
                require(container_identity(self.inspect('container', entry['id'])) == entry['identity'], 'container changed immediately before removal')
                self.run('container', 'rm', entry['id'])
                require(not any(item['Id'] == entry['id'] or item['Name'] == entry['name'] for item in self.containers()), 'container removal unverified')
                return 'removed'
            effect('container', entry['id'], remove_container)
        for entry in self.state['volumes']:
            def remove_volume(entry=entry):
                names = self.run('volume', 'ls', '--format', '{{.Name}}').splitlines()
                if entry['name'] not in names:
                    return 'absent'
                require(volume_identity(self.inspect('volume', entry['name'])) == entry['identity'], 'volume recreated/identity changed')
                require(not any(mount['Type'] == 'volume' and mount['Name'] == entry['name'] for item in self.containers() for mount in item['Mounts']), 'volume has current/stopped consumer')
                require(volume_identity(self.inspect('volume', entry['name'])) == entry['identity'], 'volume changed before removal')
                self.run('volume', 'rm', entry['name'])
                require(entry['name'] not in self.run('volume', 'ls', '--format', '{{.Name}}').splitlines(), 'volume removal unverified')
                return 'removed'
            effect('volume', entry['name'], remove_volume)
        network = self.state['network']
        if network:
            def remove_network():
                if network['id'] not in self.run('network', 'ls', '--no-trunc', '--quiet').splitlines():
                    require(network['name'] not in self.run('network', 'ls', '--format', '{{.Name}}').splitlines(), 'network recreated')
                    return 'absent'
                current = self.inspect('network', network['id'])
                require(current['Name'] == network['name'] and current['Created'] == network['created'], 'network identity changed')
                require(not current['Containers'], 'network endpoints remain')
                require(not any(network['name'] in item['NetworkSettings']['Networks'] for item in self.containers()), 'stopped/shared network consumer')
                self.run('network', 'rm', network['id'])
                require(network['id'] not in self.run('network', 'ls', '--no-trunc', '--quiet').splitlines(), 'network removal unverified')
                return 'removed'
            effect('network', network['id'], remove_network)
        # Uncertain creation before ID capture is never silently complete or deleted by name.
        expected = len([key for key in self.state.get('creation_intent', {}) if key in ('mysql', 'postgresql')])
        uncertain = expected != len(self.state['containers']) or (self.state.get('creation_intent') and not network)
        attempt['complete'] = not uncertain and all(item['status'] in ('removed', 'absent') for item in attempt['results'])
        attempt['creation_uncertain'] = uncertain
        self.save()
        require(attempt['complete'], 'cleanup incomplete; resolve exact receipt holds, then rerun cleanup with the same receipt')


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=('start', 'cleanup'))
    parser.add_argument('--receipt', required=True)
    parser.add_argument('--run', default='')
    parser.add_argument('--docker', default='docker')
    args = parser.parse_args()
    path = Path(args.receipt)
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    require(not path.is_symlink(), 'receipt must be a regular owned private file')
    if path.exists():
        require(path.stat().st_uid == os.getuid() and path.stat().st_mode & 0o777 == 0o600, 'receipt ownership/permissions changed')
    with path.with_suffix('.lock').open('a') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        lifecycle = Lifecycle(args)
        getattr(lifecycle, args.action)()


if __name__ == '__main__':
    try:
        main()
    except (Hold, OSError, ValueError, KeyError, subprocess.TimeoutExpired) as error:
        print(str(error) if isinstance(error, Hold) else 'Database lifecycle inspection unavailable; retain resources and receipt', file=sys.stderr)
        sys.exit(1)
