<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_domain_config\Kernel;

use Drupal\domain_config_entity_ui\Entity\DomainAwareConfigEntityStorageInterface;
use Drupal\domain_config_ui\DomainConfigEditContextInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prova a leitura do override de bloco pelo storage domain-aware.
 */
#[Group('aculta_domain_config')]
#[RunTestsInSeparateProcesses]
final class BlockOverrideReadKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'block',
    'domain',
    'domain_config',
    'domain_config_ui',
    'domain_config_entity_ui',
    'domain_config_switcher',
    'aculta_domain_config',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('domain');
    $this->installEntitySchema('user');
    $this->installEntitySchema('block');
    $this->installConfig([
      'field',
      'system',
      'block',
      'domain',
      'domain_config',
      'domain_config_ui',
      'domain_config_entity_ui',
    ]);

    $this->container->get('config.factory')->getEditable('domain_config_entity_ui.settings')
      ->set('covered_entity_types', ['block'])
      ->save();

    $this->container->get('entity_type.manager')->getStorage('domain')->create([
      'id' => 'test_domain',
      'hostname' => 'test.example',
      'name' => 'Teste',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ])->save();

    $block = $this->container->get('entity_type.manager')->getStorage('block')->create([
      'id' => 'aculta_test_block',
      'theme' => 'stark',
      'plugin' => 'system_powered_by_block',
      'region' => 'content',
      'settings' => ['label' => 'Base'],
      'status' => TRUE,
    ]);
    $block->save();

    $this->container->get('config.factory')->getEditable('domain_config_ui.settings')
      ->set('overridable_configurations', [
        [
          'name' => 'block.block.aculta_test_block',
          'domains' => ['test_domain'],
        ],
      ])
      ->save();

    $this->container->get('config.storage')
      ->createCollection('domain.test_domain')
      ->write('block.block.aculta_test_block', [
        'settings' => ['label' => 'Override'],
      ]);
  }

  /**
   * loadOverrideFree() devolve o valor do domínio; a base fica intacta.
   */
  public function testOverrideFreeReadFoldsTheDomainLayer(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('block');
    self::assertInstanceOf(DomainAwareConfigEntityStorageInterface::class, $storage);

    $context = $this->container->get(DomainConfigEditContextInterface::class);
    $context->setEditingDomain('test_domain', ['block.block.aculta_test_block']);

    $block = $storage->loadOverrideFree('aculta_test_block');
    self::assertSame('Override', $block->label());

    $base = $this->container->get('config.storage')
      ->read('block.block.aculta_test_block');
    self::assertSame('Base', $base['settings']['label']);
  }

}
