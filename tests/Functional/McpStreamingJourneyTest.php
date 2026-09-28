<?php

declare(strict_types=1);

namespace Fight\Test\Common\Functional;

use Fight\Test\Common\Fixture\Mcp\ProgressEndpoint;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class McpStreamingJourneyTest extends TestCase
{
    #[DataProvider('compositions')]
    public function test_that_package_composition_delivers_live_progress_safe_failures_and_disconnect(string $composition): void
    {
        $directory = sys_get_temp_dir().'/fight-common-mcp-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false);
        fclose($reservation);
        $process = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'zlib.output_compression=0',
            '-d', 'display_errors=0', '-S', $address, 'tests/Fixture/Mcp/server.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']],
            $pipes, dirname(__DIR__, 2), ['MCP_WIRE_DIRECTORY' => $directory] + getenv());
        self::assertIsResource($process);
        $evidence = [];
        try {
            $ready = false;
            for ($attempt = 0; $attempt < 200; ++$attempt) {
                $probe = @stream_socket_client('tcp://'.$address, $errno, $error, 0.05);
                if (is_resource($probe)) { fclose($probe); $ready = true; break; }
                usleep(10000);
            }
            self::assertTrue($ready, file_get_contents($directory.'/server.log'));
            foreach (['success', 'expected', 'unexpected', 'direct', 'disconnect', 'buffered', 'legacy'] as $scenario) {
                file_put_contents($directory.'/events.jsonl', '');
                $socket = stream_socket_client('tcp://'.$address, $errno, $error, 5);
                self::assertIsResource($socket, $error);
                stream_set_timeout($socket, 5);
                $request = ProgressEndpoint::request($scenario === 'direct' ? null : 'wire-token');
                $body = (string) $request->getBody();
                $wire = "POST /mcp?composition=$composition&scenario=$scenario HTTP/1.1\r\nHost: $address\r\nConnection: close\r\nContent-Length: ".strlen($body)."\r\n";
                foreach ($request->getHeaders() as $name => $values) { foreach ($values as $value) { $wire .= "$name: $value\r\n"; } }
                fwrite($socket, $wire."\r\n".$body);
                $headers = '';
                while (($line = fgets($socket)) !== false && $line !== "\r\n") { $headers .= $line; }
                $received = '';
                $frames = [];
                $timestamps = [];
                while (($line = fgets($socket)) !== false) {
                    $received .= $line;
                    if (str_starts_with($line, 'data: ')) {
                        $frames[] = json_decode(substr(trim($line), 6), true, flags: JSON_THROW_ON_ERROR);
                        $timestamps[] = microtime(true);
                        if ($scenario === 'disconnect') { break; }
                    }
                }
                self::assertFalse(stream_get_meta_data($socket)['timed_out'], file_get_contents($directory.'/server.log'));
                if ($scenario === 'disconnect') { stream_socket_shutdown($socket, STREAM_SHUT_RDWR); }
                fclose($socket);
                $events = [];
                for ($attempt = 0; $attempt < 500; ++$attempt) {
                    $events = array_map(fn(string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($directory.'/events.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
                    if (array_any($events, fn(array $event) => $event['event'] === 'closed')) { break; }
                    usleep(10000);
                }
                $closed = array_values(array_filter($events, fn(array $event) => $event['event'] === 'closed'));
                self::assertCount(1, $closed, file_get_contents($directory.'/server.log'));
                self::assertSame('cli-server', $closed[0]['sapi']);
                self::assertSame('0', $closed[0]['buffering']);
                self::assertSame('0', $closed[0]['compression']);
                self::assertSame($scenario === 'unexpected' ? 1 : 0, $closed[0]['diagnostics']);
                self::assertStringNotContainsString('private', $received);
                self::assertStringNotContainsString('</stream>', $received);
                self::assertStringNotContainsString('event:', $received);
                self::assertStringNotContainsString('id:', $received);
                self::assertStringNotContainsString('Mcp-Session', $headers);
                if (in_array($scenario, ['success', 'expected', 'unexpected', 'disconnect'], true)) {
                    self::assertStringContainsString('200 OK', $headers);
                    self::assertMatchesRegularExpression('/Content-Type: text\/event-stream/i', $headers);
                    self::assertMatchesRegularExpression('/X-Accel-Buffering: no/i', $headers);
                    self::assertMatchesRegularExpression('/Cache-Control: .*no-cache/i', $headers);
                    self::assertStringNotContainsString('Content-Length:', $headers);
                    self::assertSame('notifications/progress', $frames[0]['method']);
                    self::assertSame('wire-token', $frames[0]['params']['progressToken']);
                    self::assertSame(1, $closed[0]['calls']);
                }
                if ($scenario === 'disconnect') {
                    self::assertCount(1, $frames);
                    self::assertContains('cancelled', array_column($events, 'event'));
                    self::assertContains('cooperative-stop', array_column($events, 'event'));
                    self::assertNotContains('finish', array_column($events, 'event'));
                } elseif (in_array($scenario, ['success', 'expected', 'unexpected'], true)) {
                    self::assertCount(3, $frames);
                    self::assertSame('notifications/progress', $frames[1]['method']);
                    $finish = array_values(array_filter($events, fn(array $event) => $event['event'] === 'finish'))[0]['time'];
                    self::assertLessThan($finish, $timestamps[0], 'First progress must reach the client before completion.');
                    self::assertLessThan($finish, $timestamps[1], 'Second progress must reach the client before completion.');
                    self::assertGreaterThan($timestamps[0], $timestamps[1]);
                    self::assertSame(6, substr_count($received, "\n"));
                    if ($scenario === 'success') { self::assertSame('done', $frames[2]['result']['structuredContent']); }
                    if ($scenario === 'expected') { self::assertTrue($frames[2]['result']['isError']); self::assertSame('Unavailable.', $frames[2]['result']['content'][0]['text']); }
                    if ($scenario === 'unexpected') { self::assertSame(['code' => -32603, 'message' => 'Internal error.'], $frames[2]['error']); }
                } elseif ($scenario === 'direct') {
                    self::assertStringContainsString('200 OK', $headers);
                    self::assertMatchesRegularExpression('/Content-Type: application\/json/i', $headers);
                    self::assertSame('done', json_decode($received, true, flags: JSON_THROW_ON_ERROR)['result']['structuredContent']);
                    self::assertStringEndsWith('NullMcpProgressReporter', $events[0]['reporter']);
                    self::assertSame([], $frames);
                } elseif ($scenario === 'legacy') {
                    self::assertStringContainsString('400', $headers);
                    self::assertSame(-32602, json_decode($received, true, flags: JSON_THROW_ON_ERROR)['error']['code']);
                    self::assertSame(0, $closed[0]['calls']);
                } else {
                    self::assertStringContainsString('500', $headers);
                    self::assertSame('', $received);
                    self::assertSame(0, $closed[0]['calls']);
                    self::assertContains('runtime-rejected', array_column($events, 'event'));
                }
                $evidence[$scenario] = compact('headers', 'received', 'timestamps', 'events');
            }
        } finally {
            proc_terminate($process);
            proc_close($process);
            $destination = getenv('FIGHT_MCP_EVIDENCE_DIR');
            if ($destination !== false) {
                file_put_contents($destination.'/'.$composition.'.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                copy($directory.'/server.log', $destination.'/'.$composition.'.log');
            }
            foreach (glob($directory.'/*') as $file) { if (is_file($file)) { unlink($file); } }
            rmdir($directory);
        }
    }

    public static function compositions(): iterable
    {
        foreach (['psr', 'symfony', 'laravel', 'yii', 'codeigniter', 'slim'] as $composition) { yield $composition => [$composition]; }
    }
}
