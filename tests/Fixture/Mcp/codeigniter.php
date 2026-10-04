<?php

declare(strict_types=1);

// Boot upstream's test configuration only to exercise the native response lifecycle.
// This is not a booted installed-package starter receipt.
use CodeIgniter\Config\Factories;
use Illuminate\Container\Container;

Container::getInstance()->instance('config', new class {
    public function get(mixed $key, mixed $default = null): mixed
    {
        return is_string($key) ? (Factories::get('config', $key) ?? $default) : $default;
    }
});
define('WRITEPATH', (getenv('MCP_WIRE_DIRECTORY') ?: sys_get_temp_dir()).'/');
require dirname(__DIR__, 3).'/vendor/codeigniter4/framework/system/Test/bootstrap.php';
