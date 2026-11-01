<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_contributions\Kernel;

use Drupal\aculta_contributions\Adapter\ContributionAdapterRegistry;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies a clean install of the Contributions module and its owned storage.
 */
#[Group('aculta_contributions')]
#[RunTestsInSeparateProcesses]
final class ContributionModuleInstallKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'options', 'views'];

  /**
   * The module installs its config, entity storage, and lifecycle services.
   */
  public function testCleanModuleInstall(): void {
    self::assertTrue($this->container->get('module_installer')->install(['aculta_contributions'], FALSE));

    $container = \Drupal::getContainer();
    self::assertTrue($container->get('module_handler')->moduleExists('aculta_contributions'));
    self::assertInstanceOf(ContributionAdapterRegistry::class, $container->get('aculta_contributions.adapter_registry'));
    self::assertSame(10, $container->get('config.factory')->get('aculta_contributions.settings')->get('flood_limit'));
    self::assertSame(3600, $container->get('config.factory')->get('aculta_contributions.settings')->get('flood_window'));

    $definition = $container->get('entity_type.manager')->getDefinition('aculta_contribution');
    self::assertTrue($definition->isRevisionable());
    self::assertTrue($container->get('database')->schema()->tableExists('aculta_contribution'));
    self::assertTrue($container->get('database')->schema()->tableExists('aculta_contribution_revision'));
  }

}
