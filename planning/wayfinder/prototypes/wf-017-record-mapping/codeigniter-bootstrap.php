<?php

declare(strict_types=1);

namespace Config {
    use CodeIgniter\Config\BaseConfig;

    final class Feature extends BaseConfig
    {
        public bool $limitZeroAsAll = true;
    }
}

namespace {
    require_once __DIR__ . '/../wf-017-transaction-seam/codeigniter/vendor/codeigniter4/framework/system/Common.php';
}
