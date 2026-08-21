#!/usr/bin/env python3
"""Persist one immutable release artifact through descriptor-relative syscalls."""

from __future__ import annotations

import ctypes
import errno
import hashlib
import hmac
import os
import secrets
import stat
import sys
from collections.abc import Sequence


CREATED = 0
COLLISION = 10
FAILURE = 20
PUBLICATION_VERIFICATION_REQUIRED = 30
MISSING = 10
STAGING_DIRECTORY = ".release-artifact-staging-v1"
RENAME_NOREPLACE = 1
SHA256_HEX_LENGTH = 64
MAX_ARTIFACT_BYTES = 16 * 1024 * 1024


def _directory_flags() -> int:
    return os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC


def _file_flags() -> int:
    return os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC


def _valid_segment(segment: str) -> bool:
    return (
        bool(segment)
        and segment not in {".", ".."}
        and "/" not in segment
        and "\0" not in segment
    )


def _parse_bounded_ascii_decimal(value: str, maximum: int) -> int:
    maximum_text = str(maximum)

    if (
        not value
        or len(value) > len(maximum_text)
        or any(character not in "0123456789" for character in value)
        or (len(value) > 1 and value.startswith("0"))
    ):
        raise ValueError("invalid canonical decimal")

    parsed = int(value)

    if parsed > maximum:
        raise ValueError("decimal exceeds its governed maximum")

    return parsed


def _open_absolute_directory(path: str) -> tuple[int, list[int], list[str]]:
    if not os.path.isabs(path) or os.path.normpath(path) != path:
        raise ValueError("directory authority must be one normalized absolute path")

    descriptors = [os.open("/", _directory_flags())]
    components: list[str] = []

    for component in path.split("/")[1:]:
        if not _valid_segment(component):
            raise ValueError("invalid absolute directory component")

        descriptors.append(os.open(component, _directory_flags(), dir_fd=descriptors[-1]))
        components.append(component)

    return descriptors[-1], descriptors, components


def _open_parent(runs_root: str, relative_parent: str) -> tuple[int, list[int], list[str]]:
    _, descriptors, components = _open_absolute_directory(runs_root)

    if not relative_parent:
        return descriptors[-1], descriptors, components

    for component in relative_parent.split("/"):
        if not _valid_segment(component):
            raise ValueError("invalid relative output component")

        descriptors.append(os.open(component, _directory_flags(), dir_fd=descriptors[-1]))
        components.append(component)

    return descriptors[-1], descriptors, components


def _is_writable_directory(descriptor: int) -> bool:
    writable_bits = stat.S_IWUSR | stat.S_IWGRP | stat.S_IWOTH

    return bool(os.fstat(descriptor).st_mode & writable_bits) and os.access(
        ".",
        os.W_OK,
        dir_fd=descriptor,
        effective_ids=True,
        follow_symlinks=False,
    )


def _chain_still_linked(descriptors: list[int], components: list[str]) -> bool:
    for index, component in enumerate(components):
        expected = os.fstat(descriptors[index + 1])
        current = os.stat(component, dir_fd=descriptors[index], follow_symlinks=False)

        if (
            not stat.S_ISDIR(current.st_mode)
            or current.st_dev != expected.st_dev
            or current.st_ino != expected.st_ino
        ):
            return False

    return True


def _is_regular_collision(parent_fd: int, filename: str) -> bool:
    descriptor: int | None = None

    try:
        descriptor = os.open(
            filename,
            os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC,
            dir_fd=parent_fd,
        )
        return stat.S_ISREG(os.fstat(descriptor).st_mode)
    except OSError:
        return False
    finally:
        if descriptor is not None:
            os.close(descriptor)


def _open_staging_directory(parent_fd: int) -> int:
    try:
        os.mkdir(STAGING_DIRECTORY, 0o700, dir_fd=parent_fd)
        os.fsync(parent_fd)
    except FileExistsError:
        pass

    descriptor = os.open(STAGING_DIRECTORY, _directory_flags(), dir_fd=parent_fd)
    identity = os.fstat(descriptor)

    if (
        not stat.S_ISDIR(identity.st_mode)
        or stat.S_IMODE(identity.st_mode) != 0o700
        or identity.st_uid != os.geteuid()
    ):
        os.close(descriptor)
        raise OSError(errno.EPERM, "artifact staging authority is not private")

    return descriptor


def _publish_exclusive(
    staging_fd: int,
    staged_name: str,
    parent_fd: int,
    filename: str,
    force_link_fallback: bool,
) -> bool:
    libc = ctypes.CDLL(None, use_errno=True)

    try:
        renameat2 = libc.renameat2
    except AttributeError:
        renameat2 = None

    if renameat2 is not None and not force_link_fallback:
        renameat2.argtypes = [
            ctypes.c_int,
            ctypes.c_char_p,
            ctypes.c_int,
            ctypes.c_char_p,
            ctypes.c_uint,
        ]
        renameat2.restype = ctypes.c_int
        result = renameat2(
            staging_fd,
            os.fsencode(staged_name),
            parent_fd,
            os.fsencode(filename),
            RENAME_NOREPLACE,
        )

        if result == 0:
            return False

        error = ctypes.get_errno()

        if error not in {
            errno.ENOSYS,
            errno.EINVAL,
            errno.EPERM,
            getattr(errno, "ENOTSUP", errno.EOPNOTSUPP),
            errno.EOPNOTSUPP,
        }:
            raise OSError(error, os.strerror(error), filename)

    os.link(
        staged_name,
        filename,
        src_dir_fd=staging_fd,
        dst_dir_fd=parent_fd,
        follow_symlinks=False,
    )

    return True


def _name_matches_descriptor(directory_fd: int, name: str, descriptor: int) -> bool:
    expected = os.fstat(descriptor)

    try:
        current = os.stat(name, dir_fd=directory_fd, follow_symlinks=False)
    except OSError:
        return False

    return (
        stat.S_ISREG(current.st_mode)
        and current.st_dev == expected.st_dev
        and current.st_ino == expected.st_ino
    )


def _inject_staged_replacement(staging_fd: int, staged_name: str) -> None:
    held_name = staged_name + ".held"
    os.rename(staged_name, held_name, src_dir_fd=staging_fd, dst_dir_fd=staging_fd)
    replacement_fd = os.open(staged_name, _file_flags(), 0o600, dir_fd=staging_fd)

    try:
        if not _write_all(replacement_fd, b"adversarial-replacement", None):
            raise OSError(errno.EIO, "replacement fixture was not fully written")

        os.fsync(replacement_fd)
    finally:
        os.close(replacement_fd)


def _inject_post_publication_final_state(
    parent_fd: int,
    filename: str,
    final_state: str | None,
) -> bool:
    """Apply a test-only final-state fault and report whether verification is required."""
    if final_state == "missing":
        os.unlink(filename, dir_fd=parent_fd)

        return True

    if final_state == "mismatch":
        os.unlink(filename, dir_fd=parent_fd)
        replacement_fd = os.open(filename, _file_flags(), 0o644, dir_fd=parent_fd)

        try:
            if not _write_all(replacement_fd, b'{"post_publish":"mismatch"}\n', None):
                raise OSError(errno.EIO, "post-publication mismatch fixture was not fully written")

            os.fsync(replacement_fd)
        finally:
            os.close(replacement_fd)

        return True

    return False


def _write_all(descriptor: int, contents: bytes, write_limit: int | None) -> bool:
    written = 0
    expected = len(contents)
    limit = expected if write_limit is None else min(write_limit, expected)

    while written < limit:
        count = os.write(descriptor, contents[written:limit])

        if count <= 0:
            return False

        written += count

    return written == expected


def _read_framed_input(expected_length: int, expected_digest: str) -> bytes:
    if expected_length < 0 or len(expected_digest) != SHA256_HEX_LENGTH:
        raise ValueError("invalid artifact input frame")

    if any(character not in "0123456789abcdef" for character in expected_digest):
        raise ValueError("invalid artifact input digest")

    contents = bytearray()

    while len(contents) < expected_length:
        chunk = sys.stdin.buffer.read(min(65536, expected_length - len(contents)))

        if not chunk:
            raise OSError(errno.EIO, "artifact input ended before its declared length")

        contents.extend(chunk)

    if sys.stdin.buffer.read(1):
        raise OSError(errno.E2BIG, "artifact input exceeded its declared length")

    framed = bytes(contents)
    actual_digest = hashlib.sha256(framed).hexdigest()

    if not hmac.compare_digest(actual_digest, expected_digest):
        raise OSError(errno.EBADMSG, "artifact input digest disagreed with its frame")

    return framed


def _inject_collision(
    parent_fd: int,
    filename: str,
    contents: bytes,
    identical: bool,
) -> None:
    collision_contents = contents if identical else b'{"concurrent":"different"}\n'
    descriptor = os.open(filename, _file_flags(), 0o644, dir_fd=parent_fd)

    try:
        if not _write_all(descriptor, collision_contents, None):
            raise OSError(errno.EIO, "collision fixture was not fully written")

        os.fsync(descriptor)
    finally:
        os.close(descriptor)

    os.fsync(parent_fd)


def _inject_parent_replacement(
    runs_root: str,
    relative_parent: str,
    outside: str,
) -> None:
    if not relative_parent or not os.path.isabs(outside):
        raise ValueError("parent replacement requires a nested parent and absolute target")

    original = os.path.join(runs_root, *relative_parent.split("/"))
    held = original + ".held"
    os.rename(original, held)
    os.symlink(outside, original)


def resolve(runs_root: str, relative_parent: str) -> int:
    """Prove literal no-follow authority for one writable output below a writable runs root."""
    descriptors: list[int] = []

    try:
        parent_fd, descriptors, components = _open_parent(runs_root, relative_parent)
        runs_root_index = len([component for component in runs_root.split("/") if component])
        runs_root_fd = descriptors[runs_root_index]

        if (
            not _chain_still_linked(descriptors, components)
            or not _is_writable_directory(runs_root_fd)
            or not _is_writable_directory(parent_fd)
        ):
            return COLLISION

        return CREATED
    except (OSError, ValueError):
        return COLLISION
    finally:
        for descriptor in reversed(descriptors):
            try:
                os.close(descriptor)
            except OSError:
                pass


def store(
    runs_root: str,
    relative_parent: str,
    filename: str,
    contents: bytes,
    write_limit: int | None = None,
    collision_injection: bool | None = None,
    parent_replacement: str | None = None,
    staged_replacement_on_failure: bool = False,
    force_link_fallback: bool = False,
    staged_replacement_after_link: bool = False,
    post_publish_failure: str | None = None,
    post_publish_final: str | None = None,
) -> int:
    """Create one artifact without resolving its final path through the process cwd."""
    if not os.path.isabs(runs_root) or not _valid_segment(filename):
        return FAILURE

    descriptors: list[int] = []
    artifact_fd: int | None = None
    staging_fd: int | None = None
    staged_name = ""
    published = False

    try:
        parent_fd, descriptors, components = _open_parent(runs_root, relative_parent)

        if parent_replacement is not None:
            _inject_parent_replacement(runs_root, relative_parent, parent_replacement)

        if not _chain_still_linked(descriptors, components):
            return FAILURE

        if collision_injection is not None:
            _inject_collision(parent_fd, filename, contents, collision_injection)

        staging_fd = _open_staging_directory(parent_fd)
        staged_name = ".artifact-" + secrets.token_hex(32) + ".tmp"
        artifact_fd = os.open(staged_name, _file_flags(), 0o644, dir_fd=staging_fd)

        if not _write_all(artifact_fd, contents, write_limit):
            if staged_replacement_on_failure:
                os.close(artifact_fd)
                artifact_fd = None
                _inject_staged_replacement(staging_fd, staged_name)

            raise OSError(errno.EIO, "artifact write was incomplete")

        os.fsync(artifact_fd)
        os.fsync(staging_fd)

        if not _chain_still_linked(descriptors, components):
            return FAILURE

        try:
            linked = _publish_exclusive(
                staging_fd,
                staged_name,
                parent_fd,
                filename,
                force_link_fallback,
            )
        except FileExistsError:
            return COLLISION if _is_regular_collision(parent_fd, filename) else FAILURE

        published = True

        final_state_requires_verification = _inject_post_publication_final_state(
            parent_fd,
            filename,
            post_publish_final,
        )

        if post_publish_failure == "fstat":
            raise OSError(errno.EIO, "injected post-publication identity failure")

        if linked:
            if staged_replacement_after_link:
                _inject_staged_replacement(staging_fd, staged_name)

            if not _name_matches_descriptor(staging_fd, staged_name, artifact_fd):
                return PUBLICATION_VERIFICATION_REQUIRED

            os.unlink(staged_name, dir_fd=staging_fd)

        os.close(artifact_fd)
        artifact_fd = None

        if post_publish_failure == "fsync":
            raise OSError(errno.EIO, "injected post-publication durability failure")

        os.fsync(staging_fd)
        os.fsync(parent_fd)

        try:
            if post_publish_failure == "cleanup":
                raise OSError(errno.EIO, "injected post-publication cleanup failure")

            os.rmdir(STAGING_DIRECTORY, dir_fd=parent_fd)
            os.fsync(parent_fd)
        except OSError as error:
            if error.errno not in {errno.ENOTEMPTY, errno.EEXIST}:
                raise

        if post_publish_failure == "output":
            raise OSError(errno.EIO, "injected post-publication protocol failure")

        return (
            PUBLICATION_VERIFICATION_REQUIRED
            if final_state_requires_verification
            else CREATED
        )
    except (OSError, ValueError):
        if artifact_fd is not None:
            try:
                os.close(artifact_fd)
            except OSError:
                pass

            artifact_fd = None
        return PUBLICATION_VERIFICATION_REQUIRED if published else FAILURE
    finally:
        if artifact_fd is not None:
            try:
                os.close(artifact_fd)
            except OSError:
                pass

        if staging_fd is not None:
            try:
                os.close(staging_fd)
            except OSError:
                pass

        for descriptor in reversed(descriptors):
            try:
                os.close(descriptor)
            except OSError:
                pass


def read(
    runs_root: str,
    relative_parent: str,
    filename: str,
    parent_replacement: str | None = None,
) -> tuple[int, bytes]:
    """Read one regular artifact relative to held directory descriptors."""
    if not os.path.isabs(runs_root) or not _valid_segment(filename):
        return FAILURE, b""

    descriptors: list[int] = []
    artifact_fd: int | None = None

    try:
        parent_fd, descriptors, components = _open_parent(runs_root, relative_parent)

        if parent_replacement is not None:
            _inject_parent_replacement(runs_root, relative_parent, parent_replacement)

        if not _chain_still_linked(descriptors, components):
            return FAILURE, b""

        try:
            artifact_fd = os.open(
                filename,
                os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC,
                dir_fd=parent_fd,
            )
        except FileNotFoundError:
            return MISSING, b""

        if not stat.S_ISREG(os.fstat(artifact_fd).st_mode):
            return FAILURE, b""

        identity = os.fstat(artifact_fd)

        if identity.st_size > MAX_ARTIFACT_BYTES:
            return FAILURE, b""

        chunks: list[bytes] = []
        total = 0

        while total <= MAX_ARTIFACT_BYTES:
            chunk = os.read(
                artifact_fd,
                min(65536, MAX_ARTIFACT_BYTES + 1 - total),
            )

            if not chunk:
                break

            chunks.append(chunk)
            total += len(chunk)

        if total > MAX_ARTIFACT_BYTES:
            return FAILURE, b""

        return CREATED, b"".join(chunks)
    except (OSError, ValueError):
        return FAILURE, b""
    finally:
        if artifact_fd is not None:
            try:
                os.close(artifact_fd)
            except OSError:
                pass

        for descriptor in reversed(descriptors):
            try:
                os.close(descriptor)
            except OSError:
                pass


def _parse_optional(
    arguments: Sequence[str],
    maximum_write_limit: int,
) -> tuple[int | None, bool | None, str | None, bool, bool, bool, str | None, str | None]:
    write_limit: int | None = None
    collision_injection: bool | None = None
    parent_replacement: str | None = None
    staged_replacement_on_failure = False
    force_link_fallback = False
    staged_replacement_after_link = False
    post_publish_failure: str | None = None
    post_publish_final: str | None = None

    for argument in arguments:
        if argument.startswith("--write-limit="):
            if write_limit is not None:
                raise ValueError("duplicate write limit")

            value = argument.removeprefix("--write-limit=")

            write_limit = _parse_bounded_ascii_decimal(value, maximum_write_limit)
        elif argument == "--collision=identical":
            if collision_injection is not None:
                raise ValueError("duplicate collision injection")

            collision_injection = True
        elif argument == "--collision=different":
            if collision_injection is not None:
                raise ValueError("duplicate collision injection")

            collision_injection = False
        elif argument.startswith("--replace-parent="):
            if parent_replacement is not None:
                raise ValueError("duplicate parent replacement")

            parent_replacement = argument.removeprefix("--replace-parent=")

            if not parent_replacement:
                raise ValueError("invalid parent replacement")
        elif argument == "--replace-staged-on-failure":
            if staged_replacement_on_failure:
                raise ValueError("duplicate staged replacement injection")

            staged_replacement_on_failure = True
        elif argument == "--force-link-fallback":
            if force_link_fallback:
                raise ValueError("duplicate hard-link fallback injection")

            force_link_fallback = True
        elif argument == "--replace-staged-after-link":
            if staged_replacement_after_link:
                raise ValueError("duplicate staged replacement injection")

            staged_replacement_after_link = True
        elif argument.startswith("--fail-after-publish="):
            if post_publish_failure is not None:
                raise ValueError("duplicate post-publication failure injection")

            post_publish_failure = argument.removeprefix("--fail-after-publish=")

            if post_publish_failure not in {"fstat", "fsync", "cleanup", "output"}:
                raise ValueError("invalid post-publication failure injection")
        elif argument.startswith("--post-publish-final="):
            if post_publish_final is not None:
                raise ValueError("duplicate post-publication final-state injection")

            post_publish_final = argument.removeprefix("--post-publish-final=")

            if post_publish_final not in {"exists", "missing", "mismatch"}:
                raise ValueError("invalid post-publication final-state injection")
        else:
            raise ValueError("unsupported option")

    return (
        write_limit,
        collision_injection,
        parent_replacement,
        staged_replacement_on_failure,
        force_link_fallback,
        staged_replacement_after_link,
        post_publish_failure,
        post_publish_final,
    )


def main(arguments: Sequence[str]) -> int:
    if len(arguments) == 3 and arguments[0] == "resolve":
        _, runs_root, relative_parent = arguments

        return resolve(runs_root, relative_parent)

    if len(arguments) < 4:
        return FAILURE

    operation, runs_root, relative_parent, filename, *protocol = arguments

    if operation == "write":
        if len(protocol) < 2:
            return FAILURE

        expected_length_text, expected_digest, *optional = protocol

        try:
            expected_length = _parse_bounded_ascii_decimal(
                expected_length_text,
                MAX_ARTIFACT_BYTES,
            )
        except ValueError:
            return FAILURE
    else:
        optional = protocol
        expected_length = 0
        expected_digest = ""

    try:
        (
            write_limit,
            collision_injection,
            parent_replacement,
            staged_replacement_on_failure,
            force_link_fallback,
            staged_replacement_after_link,
            post_publish_failure,
            post_publish_final,
        ) = _parse_optional(optional, expected_length)
    except (OSError, ValueError):
        return FAILURE

    if operation == "read":
        if (
            write_limit is not None
            or collision_injection is not None
            or staged_replacement_on_failure
            or force_link_fallback
            or staged_replacement_after_link
            or post_publish_failure is not None
            or post_publish_final is not None
        ):
            return FAILURE

        status, contents = read(runs_root, relative_parent, filename, parent_replacement)

        if status == CREATED:
            try:
                sys.stdout.buffer.write(contents)
                sys.stdout.buffer.flush()
            except OSError:
                return FAILURE

        return status

    if operation != "write":
        return FAILURE

    try:
        contents = _read_framed_input(expected_length, expected_digest)
    except (OSError, ValueError):
        return FAILURE

    status = store(
        runs_root,
        relative_parent,
        filename,
        contents,
        write_limit,
        collision_injection,
        parent_replacement,
        staged_replacement_on_failure,
        force_link_fallback,
        staged_replacement_after_link,
        post_publish_failure,
        post_publish_final,
    )

    if status == PUBLICATION_VERIFICATION_REQUIRED and post_publish_failure == "output":
        try:
            sys.stdout.buffer.write(b"post-publication protocol ambiguity")
            sys.stdout.buffer.flush()
        except OSError:
            pass

    return status


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
