<?php

declare(strict_types=1);

// Run before installing PHP extensions; the base image already provides OpenSSL.
$certificateLocations = openssl_get_cert_locations();
$bundlePath = getenv($certificateLocations['default_cert_file_env']);
if ($bundlePath === false) {
    $bundlePath = $certificateLocations['default_cert_file'];
}

$bundle = is_readable($bundlePath) ? file_get_contents($bundlePath) : false;
if ($bundle === false) {
    fwrite(STDERR, "Cannot read OS CA bundle: $bundlePath\n");
    exit(1);
}

// Check certificate subjects, not issuer names or raw PEM text. Fail on incomplete bundles.
$certificates = explode('-----END CERTIFICATE-----', trim($bundle));
if (array_pop($certificates) !== '' || $certificates === []) {
    fwrite(STDERR, "Invalid OS CA bundle: $bundlePath\n");
    exit(1);
}

$beginMarker = '-----BEGIN CERTIFICATE-----';
foreach ($certificates as $pem) {
    $pem = trim($pem);
    if (!str_starts_with($pem, $beginMarker) || substr_count($pem, $beginMarker) !== 1) {
        fwrite(STDERR, "Invalid certificate in OS CA bundle: $bundlePath\n");
        exit(1);
    }

    $certificate = openssl_x509_parse($pem."\n-----END CERTIFICATE-----\n");
    if ($certificate === false) {
        fwrite(STDERR, "Cannot parse certificate in OS CA bundle: $bundlePath\n");
        exit(1);
    }

    if (in_array('emSign ECC Root CA - G3', (array) ($certificate['subject']['CN'] ?? []), true)) {
        fwrite(STDERR, "Rejected emSign ECC Root CA - G3 in OS CA bundle: $bundlePath\n");
        exit(1);
    }
}

printf("os %s %s\n", $bundlePath, hash('sha256', $bundle));
