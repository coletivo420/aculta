<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pdf_viewer\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Spike da migração de identidade (0.1.0-dev.2) com fixtures de dois módulos.
 *
 * `aculta_mig_old` expõe `aculta_mig_formatter`. `aculta_mig_new` expõe o
 * mesmo ID e também `aculta_mig_formatter_v2`. Os testes medem o
 * comportamento real do Core ao desinstalar o provider antigo.
 */
#[Group('aculta_pdf_viewer')]
#[RunTestsInSeparateProcesses]
final class DocumentMigrationSpikeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'entity_test', 'aculta_mig_old'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'field']);
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'entity_test',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Texto',
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_text', ['type' => 'aculta_mig_formatter'])->save();
  }

  /**
   * Achado: com o mesmo formatter ID em dois módulos, o provider antigo vence.
   */
  public function testSharedFormatterIdKeepsOldProvider(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_mig_new']));
    $definitions = $this->container->get('plugin.manager.field.formatter')->getDefinitions();
    $this->assertSame('aculta_mig_old', $definitions['aculta_mig_formatter']['provider']);
  }

  /**
   * Controle: desinstalar o provider antigo sem migrar o display.
   *
   * Achado: o componente não some, mas volta ao formatter padrão do tipo
   * (`string`). Num display público de PDF isso seria uma falha silenciosa.
   */
  public function testUninstallWithoutMigrationFallsBackToDefaultFormatter(): void {
    $this->container->get('module_installer')->uninstall(['aculta_mig_old']);
    $display = EntityViewDisplay::load('entity_test.entity_test.default');
    $component = $display->getComponent('field_text');
    $this->assertNotNull($component);
    $this->assertSame('string', $component['type'], 'Sem migração, o formatter cai para o padrão do tipo.');
  }

  /**
   * Estratégia: trocar o tipo do componente para o ID novo, recalcular a
   * dependência e só então desinstalar o antigo.
   */
  public function testRewriteComponentTypeBeforeUninstallKeepsComponent(): void {
    $installer = $this->container->get('module_installer');
    $this->assertTrue($installer->install(['aculta_mig_new']));

    $display = EntityViewDisplay::load('entity_test.entity_test.default');
    $component = $display->getComponent('field_text');
    $component['type'] = 'aculta_mig_formatter_v2';
    $display->setComponent('field_text', $component);
    $display->calculateDependencies();
    $display->save();

    $installer->uninstall(['aculta_mig_old']);

    $display = EntityViewDisplay::load('entity_test.entity_test.default');
    $component = $display->getComponent('field_text');
    $this->assertNotNull($component, 'Com troca prévia do tipo, o componente permanece.');
    $this->assertSame('aculta_mig_formatter_v2', $component['type']);
    $this->assertNotContains('aculta_mig_old', $display->getDependencies()['module'] ?? []);
  }

}
