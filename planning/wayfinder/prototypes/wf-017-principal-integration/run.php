<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request as LaravelRequest;
use Nyholm\Psr7\ServerRequest;
use Prototype\PrincipalIntegration\AdapterCurrentPrincipalProvider;
use Prototype\PrincipalIntegration\AuthoritativePrincipalStore;
use Prototype\PrincipalIntegration\CodeIgniterAuthenticationService;
use Prototype\PrincipalIntegration\NativeAuthentication;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\Middleware\Authentication;

use function Prototype\PrincipalIntegration\runLane;

require __DIR__ . '/vendor/autoload.php';

$locked = json_decode(file_get_contents(__DIR__ . '/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
$versions = [];
foreach ($locked['packages'] as $package) {
    $versions[$package['name']] = $package['version'];
}

runLane('symfony', ['symfony/security-core' => $versions['symfony/security-core']], [
    'native_boundary' => TokenStorage::class,
    'adapter' => 'starter-owned token-storage principal provider',
], static function (?NativeAuthentication $native, AuthoritativePrincipalStore $store): AdapterCurrentPrincipalProvider {
    $storage = new TokenStorage();
    if ($native !== null) {
        $user = new class($native) implements UserInterface {
            public function __construct(public NativeAuthentication $authentication) {}
            public function getRoles(): array { return []; }
            public function getUserIdentifier(): string { return $this->authentication->userId; }
        };
        $storage->setToken(new UsernamePasswordToken($user, 'api'));
    }

    return new AdapterCurrentPrincipalProvider(static function () use ($storage): ?NativeAuthentication {
        $user = $storage->getToken()?->getUser();
        return $user instanceof UserInterface && property_exists($user, 'authentication') ? $user->authentication : null;
    }, $store);
});

runLane('laravel', ['illuminate/auth' => $versions['illuminate/auth']], [
    'native_boundary' => RequestGuard::class,
    'adapter' => 'starter-owned request guard principal provider',
], static function (?NativeAuthentication $native, AuthoritativePrincipalStore $store): AdapterCurrentPrincipalProvider {
    $request = LaravelRequest::create('/api/v1/access/users');
    $guard = new RequestGuard(static fn () => $native === null ? null : new GenericUser([
        'id' => $native->userId,
        'session_id' => $native->sessionId,
        'authentication_version' => $native->authenticationVersion,
    ]), $request);

    return new AdapterCurrentPrincipalProvider(static function () use ($guard): ?NativeAuthentication {
        $user = $guard->user();
        return $user === null ? null : new NativeAuthentication($user->getAuthIdentifier(), $user->session_id, $user->authentication_version);
    }, $store);
});

runLane('yii', ['yiisoft/auth' => $versions['yiisoft/auth']], [
    'native_boundary' => Authentication::class . ' request attribute',
    'adapter' => 'starter-owned request-scoped identity principal provider',
], static function (?NativeAuthentication $native, AuthoritativePrincipalStore $store): AdapterCurrentPrincipalProvider {
    $identity = $native === null ? null : new class($native) implements IdentityInterface {
        public function __construct(public NativeAuthentication $authentication) {}
        public function getId(): ?string { return $this->authentication->userId; }
    };
    $request = (new ServerRequest('GET', '/api/v1/access/users'))->withAttribute(Authentication::class, $identity);

    return new AdapterCurrentPrincipalProvider(static function () use ($request): ?NativeAuthentication {
        $identity = $request->getAttribute(Authentication::class);
        return $identity instanceof IdentityInterface && property_exists($identity, 'authentication') ? $identity->authentication : null;
    }, $store);
});

runLane('codeigniter', ['codeigniter4/framework' => $versions['codeigniter4/framework']], [
    'native_boundary' => 'CodeIgniter authentication implementation user_id() convention',
    'adapter' => 'starter-owned filter/service principal provider',
], static function (?NativeAuthentication $native, AuthoritativePrincipalStore $store): AdapterCurrentPrincipalProvider {
    $service = new CodeIgniterAuthenticationService($native);
    return new AdapterCurrentPrincipalProvider(static function () use ($service): ?NativeAuthentication {
        $authentication = $service->authentication();
        return $service->userId() === $authentication?->userId ? $authentication : null;
    }, $store);
});

runLane('slim', ['psr/http-message' => $versions['psr/http-message'], 'nyholm/psr7' => $versions['nyholm/psr7']], [
    'native_boundary' => 'PSR-7 authenticated request attribute',
    'adapter' => 'starter-owned PSR-15 middleware principal provider',
], static function (?NativeAuthentication $native, AuthoritativePrincipalStore $store): AdapterCurrentPrincipalProvider {
    $request = (new ServerRequest('GET', '/api/v1/access/users'))->withAttribute('fight.authentication', $native);
    return new AdapterCurrentPrincipalProvider(static fn (): ?NativeAuthentication => $request->getAttribute('fight.authentication'), $store);
});
