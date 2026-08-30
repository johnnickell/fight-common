<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\ClassLikeConfig;
use Deptrac\Deptrac\Contract\Config\Collector\FunctionNameConfig;
use Deptrac\Deptrac\Contract\Config\Collector\PhpInteralConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config, string ...$paths): void {
    $config
        ->paths(...$paths)
        ->layers(
            $domain = Layer::withName('Domain')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Domain\\'),
                FunctionNameConfig::create('^Fight\\Common\\Domain\\'),
            ),
            $application = Layer::withName('Application')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Application\\'),
            ),
            $adapter = Layer::withName('Adapter')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Adapter\\'),
            ),
            $standards = Layer::withName('Standards')->collectors(
                ClassLikeConfig::create('^Fight\\Common\\Standards\\'),
            ),
            $phpInternals = Layer::withName('PHP internals')->collectors(
                PhpInteralConfig::create('.*'),
            ),
            $psr = Layer::withName('PSR contracts')->collectors(
                ClassLikeConfig::create('^Psr\\'),
            ),
            $cronExpression = Layer::withName('CronExpression')->collectors(
                ClassLikeConfig::create('^Cron\\CronExpression$'),
            ),
            $doctrine = Layer::withName('Doctrine infrastructure')->collectors(
                ClassLikeConfig::create('^Doctrine\\(?:DBAL|ORM)\\'),
            ),
            $guzzle = Layer::withName('Guzzle infrastructure')->collectors(
                ClassLikeConfig::create('^GuzzleHttp\\'),
            ),
            $jwt = Layer::withName('Lcobucci JWT infrastructure')->collectors(
                ClassLikeConfig::create('^Lcobucci\\JWT\\'),
            ),
            $flysystem = Layer::withName('Flysystem infrastructure')->collectors(
                ClassLikeConfig::create('^League\\Flysystem\\'),
            ),
            $phpseclib = Layer::withName('phpseclib infrastructure')->collectors(
                ClassLikeConfig::create('^phpseclib3\\'),
            ),
            $symfony = Layer::withName('Symfony infrastructure')->collectors(
                ClassLikeConfig::create('^Symfony\\Component\\DependencyInjection\\'),
                ClassLikeConfig::create('^Symfony\\Component\\EventDispatcher\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Filesystem\\'),
                ClassLikeConfig::create('^Symfony\\Component\\HttpFoundation\\'),
                ClassLikeConfig::create('^Symfony\\Component\\HttpKernel\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Mailer\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Mercure\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Messenger\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Mime\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Process\\'),
                ClassLikeConfig::create('^Symfony\\Component\\Routing\\'),
            ),
            $twig = Layer::withName('Twig infrastructure')->collectors(
                ClassLikeConfig::create('^Twig\\'),
            ),
            $twilio = Layer::withName('Twilio infrastructure')->collectors(
                ClassLikeConfig::create('^Twilio\\'),
            ),
            $phpCodeSniffer = Layer::withName('PHP_CodeSniffer')->collectors(
                ClassLikeConfig::create('^PHP_CodeSniffer\\'),
            ),
            $slevomat = Layer::withName('Slevomat')->collectors(
                ClassLikeConfig::create('^SlevomatCodingStandard\\'),
            ),
            $slim = Layer::withName('Slim infrastructure')->collectors(
                ClassLikeConfig::create('^Slim\\'),
            ),
            $laravel = Layer::withName('Laravel infrastructure')->collectors(
                ClassLikeConfig::create('^Illuminate\\'),
            ),
            $codeIgniter = Layer::withName('CodeIgniter infrastructure')->collectors(
                ClassLikeConfig::create('^CodeIgniter\\'),
            ),
            $yii = Layer::withName('Yii infrastructure')->collectors(
                ClassLikeConfig::create('^Yiisoft\\'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($domain)->accesses($phpInternals),
            Ruleset::forLayer($application)->accesses($domain, $phpInternals, $psr, $cronExpression),
            Ruleset::forLayer($adapter)->accesses(
                $application,
                $domain,
                $phpInternals,
                $psr,
                $doctrine,
                $guzzle,
                $jwt,
                $flysystem,
                $phpseclib,
                $symfony,
                $twig,
                $twilio,
                $slim,
                $laravel,
                $codeIgniter,
                $yii,
            ),
            Ruleset::forLayer($standards)->accesses($phpInternals, $phpCodeSniffer, $slevomat),
            Ruleset::forLayer($phpInternals),
            Ruleset::forLayer($psr),
            Ruleset::forLayer($cronExpression),
            Ruleset::forLayer($doctrine),
            Ruleset::forLayer($guzzle),
            Ruleset::forLayer($jwt),
            Ruleset::forLayer($flysystem),
            Ruleset::forLayer($phpseclib),
            Ruleset::forLayer($symfony),
            Ruleset::forLayer($twig),
            Ruleset::forLayer($twilio),
            Ruleset::forLayer($phpCodeSniffer),
            Ruleset::forLayer($slevomat),
            Ruleset::forLayer($slim),
            Ruleset::forLayer($laravel),
            Ruleset::forLayer($codeIgniter),
            Ruleset::forLayer($yii),
        )
    ;
};
