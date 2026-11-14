<?php

declare(strict_types=1);

use DrupalRector\Services\DrupalRectorSettings;
use DrupalRector\Set\Drupal10SetList;
use DrupalRector\Set\Drupal11SetList;
use DrupalRector\Set\Drupal12SetList;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/web/modules/custom/aculta_mcp',
        __DIR__ . '/web/modules/custom/aculta_mcp_client',
        __DIR__ . '/web/modules/custom/aculta_portal',
        __DIR__ . '/web/themes/custom/aculta420',
    ]);

    $rectorConfig->sets([
        Drupal10SetList::DRUPAL_10,
        Drupal11SetList::DRUPAL_11,
        Drupal12SetList::DRUPAL_12,
    ]);

    $rectorConfig->singleton(DrupalRectorSettings::class, static fn (): DrupalRectorSettings => (new DrupalRectorSettings())
        ->enableBackwardCompatibility()
        ->setMinimumCoreVersionSupported('11.0.0')
        ->setDrupalVersion('12.0.0'));

    $phpstanDrupalExtension = __DIR__ . '/vendor/mglaman/phpstan-drupal/extension.neon';
    if (file_exists($phpstanDrupalExtension)) {
        $rectorConfig->phpstanConfigs([$phpstanDrupalExtension]);
    }

    $drupalRoot = __DIR__ . '/web';
    $rectorConfig->autoloadPaths([
        $drupalRoot . '/core',
        $drupalRoot . '/modules',
        $drupalRoot . '/profiles',
        $drupalRoot . '/themes',
    ]);

    $rectorConfig->skip([
        '*/tests/*',
        '*/upgrade_status/tests/modules/*',
    ]);
    $rectorConfig->fileExtensions(['php', 'module', 'theme', 'install', 'profile', 'inc', 'engine']);
};
