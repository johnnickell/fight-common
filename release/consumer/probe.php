<?php

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Value\Identifier\Uuid;

/**
 * Probes public behavior through the installed production autoloader.
 */
function certification_consumer_probe(string $autoloadPath): bool
{
    require $autoloadPath;

    $uuid = Uuid::fromString('{6BA7B810-9DAD-11D1-80B4-00C04FD430C8}');
    $values = ArrayList::of('string');
    $values->add($uuid->toString());

    $releaseClass = implode('\\', ['Fight', 'Release', 'Application', 'CertificationRecord']);
    $passed = $values->count() === 1
        && $values->get(0) === Uuid::NAMESPACE_DNS
        && $uuid->toUrn() === 'urn:uuid:'.Uuid::NAMESPACE_DNS
        && !class_exists($releaseClass);

    echo json_encode(
        [
            'status'                    => $passed ? 'passed' : 'failed',
            'installed_package'         => 'johnnickell/fight-common',
            'uuid_round_trip'           => $uuid->toString(),
            'typed_collection_count'    => $values->count(),
            'release_namespace_exposed' => class_exists($releaseClass)
        ],
        JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ).PHP_EOL;

    return $passed;
}

$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) !== 2 || !is_file($arguments[1])) {
    fwrite(STDERR, "Usage: probe.php <installed-autoload.php>\n");
    exit(2);
}

exit(certification_consumer_probe($arguments[1]) ? 0 : 1);
