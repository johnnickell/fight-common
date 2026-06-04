# File Transfer

A transport-abstraction layer for remote file operations. The `FileTransport` port covers
sending, retrieving, and listing files on a remote host. `FileTransferService` acts as a
named registry when an application needs to talk to multiple remote endpoints.

```
Application\FileTransfer
├── FileTransferService                 — Named registry of FileTransport instances
├── Transport\
│   └── FileTransport (interface)       — sendFile(), retrieveFileContents(),
│                                          retrieveFileResource(), readDirectory()
├── Resource\
│   ├── Resource                        — Immutable file/directory descriptor
│   └── ResourceType (enum: string)     — FILE, DIR, LINK, FIFO, CHAR, BLOCK, SOCKET, UNKNOWN
└── Exception\
    └── FileTransferException           — extends SystemException

Adapter\FileTransfer
├── Sftp\
│   └── SftpFileTransport               — FileTransport → phpseclib3 SFTP
├── Ftp\
│   └── FtpFileTransport                — FileTransport → PHP FTP extension
├── Logging\
│   └── LoggingFileTransport            — Decorator: logs operations then delegates
└── Null\
    └── NullFileTransport               — No-op (tests / dev)
```

---

## Table of Contents

1. [FileTransport](#filetransport)
2. [Resource](#resource)
3. [ResourceType](#resourcetype)
4. [FileTransferService](#filetransferservice)
5. [Adapters](#adapters)
6. [Symfony Configuration](#symfony-configuration)
7. [Usage Examples](#usage-examples)

---

## FileTransport

`Fight\Common\Application\FileTransfer\Transport\FileTransport`

```php
interface FileTransport
{
    /** @throws FileTransferException */
    public function sendFile(string $path, mixed $contents): void;

    /** @throws FileTransferException */
    public function retrieveFileContents(string $path): string;

    /** @throws FileTransferException */
    public function retrieveFileResource(string $path): mixed;   // returns resource

    /**
     * @return iterable<Resource>
     * @throws FileTransferException
     */
    public function readDirectory(string $directory): iterable;
}
```

`$contents` in `sendFile()` accepts a string or a stream resource. `retrieveFileResource()`
returns an open stream resource positioned at the start of the file.

---

## Resource

`Fight\Common\Application\FileTransfer\Resource\Resource`

An immutable value object describing a single remote entry returned by `readDirectory()`.

```php
final readonly class Resource implements Stringable
{
    public function __construct(
        string $path,
        int $size,
        int $userId,
        int $groupId,
        int $mode,
        DateTimeImmutable $accessTime,
        DateTimeImmutable $modifyTime,
        ResourceType $type
    ) {}
}
```

### Accessors

| Method | Returns | Description |
|---|---|---|
| `path()` | `string` | Full remote path (trimmed) |
| `size()` | `int` | Size in bytes |
| `userId()` | `int` | Owning user ID |
| `groupId()` | `int` | Owning group ID |
| `mode()` | `int` | Octal permission mode as integer |
| `permissions()` | `string` | 4-character octal string e.g. `'0644'` |
| `accessTime()` | `DateTimeImmutable` | Last access time |
| `modifyTime()` | `DateTimeImmutable` | Last modification time |
| `type()` | `ResourceType` | Entry type |
| `__toString()` | `string` | Alias for `path()` |

---

## ResourceType

`Fight\Common\Application\FileTransfer\Resource\ResourceType`

```php
enum ResourceType: string
{
    case FILE    = 'file';
    case DIR     = 'dir';
    case LINK    = 'link';
    case FIFO    = 'fifo';
    case CHAR    = 'char';
    case BLOCK   = 'block';
    case SOCKET  = 'socket';
    case UNKNOWN = 'unknown';
}
```

---

## FileTransferService

`Fight\Common\Application\FileTransfer\FileTransferService`

A named registry for multiple `FileTransport` instances. Useful when an application
connects to more than one remote host or protocol simultaneously.

```php
$service = new FileTransferService();
$service->addTransport('sftp-primary', $sftpTransport);
$service->addTransport('ftp-backup',   $ftpTransport);

$transport = $service->getTransport('sftp-primary');
$transport->sendFile('/uploads/report.csv', $csvData);
```

Throws `FileTransferException` if a duplicate key is registered. Throws `KeyException` if
the requested key is not found.

---

## Adapters

### SftpFileTransport

`Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport`

Wraps a `phpseclib3\Net\SFTP` connection. The SFTP object is constructed and authenticated
externally then injected:

```php
use phpseclib3\Net\SFTP;
use Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport;

$sftp = new SFTP('sftp.example.com');
$sftp->login('user', 'password');

$transport = new SftpFileTransport($sftp);
```

`readDirectory()` uses `SFTP::rawlist()` and maps phpseclib's numeric type codes to
`ResourceType` values (1 → FILE, 2 → DIR, 3 → LINK, anything else → UNKNOWN).

### FtpFileTransport

`Fight\Common\Adapter\FileTransfer\Ftp\FtpFileTransport`

Uses PHP's built-in FTP extension. Manages the connection lifecycle internally — connects
on first use and disconnects after each operation.

```php
use Fight\Common\Adapter\FileTransfer\Ftp\FtpFileTransport;

$transport = new FtpFileTransport(
    host:     'ftp.example.com',
    port:     21,
    username: 'ftpuser',
    password: 'secret',
    ssl:      true,    // use ftp_ssl_connect (default false)
    timeout:  90,      // seconds (default 90)
    passive:  true     // PASV mode (default false)
);
```

`sendFile()` creates missing parent directories automatically. `readDirectory()` uses
`ftp_mlsd()` for structured directory listings. Requires the PHP `ftp` extension and
`libssl` for SSL connections.

### LoggingFileTransport

`Fight\Common\Adapter\FileTransfer\Logging\LoggingFileTransport`

Decorator that logs the path of each operation via PSR-3 before delegating:

```php
$transport = new LoggingFileTransport(
    new SftpFileTransport($sftp),
    $logger,
    LogLevel::INFO   // default DEBUG
);
```

Log channels:
- `sendFile` → `[FileTransfer]: Sending file` with `path`
- `retrieveFileContents` / `retrieveFileResource` → `[FileTransfer]: Retrieving file contents/resource` with `path`
- `readDirectory` → `[FileTransfer]: Reading directory` with `path`

### NullFileTransport

`Fight\Common\Adapter\FileTransfer\Null\NullFileTransport`

Silent no-op adapter. `sendFile()` does nothing. `retrieveFileContents()` returns `''`.
`retrieveFileResource()` returns an empty `php://memory` stream. `readDirectory()` returns
an empty array. Useful in tests and local development.

---

## Symfony Configuration

```yaml
# config/packages/common_file_transfer.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- SFTP ---
    phpseclib3\Net\SFTP:
        factory: ['phpseclib3\Net\SFTP', 'new']
        arguments: ['%env(SFTP_HOST)%']
        calls:
            - [login, ['%env(SFTP_USER)%', '%env(SFTP_PASSWORD)%']]

    Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport:
        arguments:
            - '@phpseclib3\Net\SFTP'

    Fight\Common\Adapter\FileTransfer\Logging\LoggingFileTransport:
        decorates: Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport
        arguments:
            - '@.inner'
            - '@logger'
            - 'info'

    # --- Registry ---
    Fight\Common\Application\FileTransfer\FileTransferService:
        calls:
            - [addTransport, ['sftp', '@Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport']]

    # --- Interface alias ---
    Fight\Common\Application\FileTransfer\Transport\FileTransport:
        alias: Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport
```

---

## Usage Examples

### Uploading a File

```php
use Fight\Common\Application\FileTransfer\Transport\FileTransport;

class ReportExporter
{
    public function __construct(private FileTransport $transport) {}

    public function export(Report $report): void
    {
        $this->transport->sendFile(
            '/exports/reports/'.$report->filename(),
            $report->toCsv()
        );
    }
}
```

### Downloading a File

```php
$contents = $transport->retrieveFileContents('/reports/2026-06.csv');

// As a stream resource
$stream = $transport->retrieveFileResource('/reports/2026-06.csv');
```

### Listing a Directory

```php
foreach ($transport->readDirectory('/uploads') as $resource) {
    if ($resource->type() === ResourceType::FILE) {
        echo sprintf(
            '%s  %s  %d bytes',
            $resource->permissions(),
            $resource->path(),
            $resource->size()
        );
    }
}
```

### Using Multiple Transports

```php
$service = new FileTransferService();
$service->addTransport('sftp',   $sftpTransport);
$service->addTransport('backup', $ftpTransport);

// Route by business logic
$service->getTransport($isPrimary ? 'sftp' : 'backup')
    ->sendFile('/data/'.$filename, $contents);
```

### Testing with NullFileTransport

```php
use Fight\Common\Adapter\FileTransfer\Null\NullFileTransport;

$transport = new NullFileTransport();
$transport->sendFile('/any/path', 'data');      // no-op
$contents = $transport->retrieveFileContents('/any/path');  // ''
```
