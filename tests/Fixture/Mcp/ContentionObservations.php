<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use RuntimeException;
use Throwable;

/**
 * Owns best-effort observations in the fixture's private local temporary directory
 */
final class ContentionObservations
{
    /**
     * Returns bounded fixture data or a payload-free observation error
     */
    public static function read(string $path): array
    {
        $stream = null;
        try {
            // Reject pipes/devices/symlinks before opening; never wait on another diagnostic producer.
            clearstatcache(true, $path);
            $stat = @lstat($path);
            if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || $stat['size'] > 65536) { throw new RuntimeException('Unavailable observation'); }
            $stream = @fopen($path, 'rb');
            if ($stream === false) { throw new RuntimeException('Unreadable observation'); }
            stream_set_blocking($stream, false);
            $json = stream_get_contents($stream, 65537);
            if ($json === false || strlen($json) > 65536) { throw new RuntimeException('Oversized observation'); }
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { throw new RuntimeException('Invalid observation shape'); }
            return $data;
        } catch (Throwable $failure) {
            return ['observation_error' => $failure::class];
        } finally {
            try { if (is_resource($stream)) { fclose($stream); } } catch (Throwable) { /* Observation cleanup is best effort too. */ }
        }
    }

    /**
     * Publishes optional local evidence without retrying or throwing
     */
    public static function write(string $path, array $data): ?string
    {
        $stream = null;
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
            if (strlen($json) > 65536) { throw new RuntimeException('Oversized observation'); }
            foreach ([$path, $path.'.tmp'] as $candidate) {
                clearstatcache(true, $candidate);
                $stat = @lstat($candidate);
                if ($stat !== false && ($stat['mode'] & 0170000) !== 0100000) { throw new RuntimeException('Nonregular observation'); }
            }
            $stream = @fopen($path.'.tmp', 'wb');
            if ($stream === false) { throw new RuntimeException('Unwritable observation'); }
            stream_set_blocking($stream, false);
            // One bounded write, no locks, retry loop or fsync. Local regular files only.
            if (@fwrite($stream, $json) !== strlen($json)) { throw new RuntimeException('Incomplete observation'); }
            fclose($stream);
            $stream = null;
            if (!@rename($path.'.tmp', $path)) { throw new RuntimeException('Unpublished observation'); }
            return null;
        } catch (Throwable $failure) {
            return $failure::class;
        } finally {
            try { if (is_resource($stream)) { fclose($stream); } } catch (Throwable) { /* Observation cleanup is best effort too. */ }
        }
    }
}
