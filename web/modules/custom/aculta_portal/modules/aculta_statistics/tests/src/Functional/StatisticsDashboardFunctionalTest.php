<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Painel de estatísticas: acesso, período escolhido e exportação CSV.
 */
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class StatisticsDashboardFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'aculta_statistics',
    // Portal registration and access services consume the User consent ledger.
    'aculta_user',
    // Portal installs a Search API server configuration entity at install.
    // Keep its provider modules explicit in this isolated site installation.
    'search_api',
    'search_api_db',
  ];

  /**
   * Exige um site de teste com URL base; nunca aponta para o Runtime.
   *
   * @see docs/operations/BANCO-DE-TESTE.md
   */
  protected function setUp(): void {
    if (getenv('SIMPLETEST_BASE_URL') === FALSE || getenv('SIMPLETEST_BASE_URL') === '') {
      $this->markTestSkipped(
        'Sem SIMPLETEST_BASE_URL: teste funcional pulado. Não usar o Runtime para isso.',
      );
    }
    parent::setUp();
  }

  /**
   * Anônimo não entra; administrador vê o período e baixa o CSV.
   */
  public function testAccessAndExport(): void {
    $this->drupalGet('/painel-administrativo/aculta/estatisticas');
    $this->assertSession()->statusCodeEquals(403);

    $admin = $this->drupalCreateUser(['administer aculta_statistics']);
    $this->drupalLogin($admin);

    $this->drupalGet('/painel-administrativo/aculta/estatisticas', [
      'query' => [
        'metrica' => 'wiki.entries.read',
        'dias' => 14,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', 'select[name="dias"] option[value="14"][selected]');
    $this->assertSession()->pageTextContains('no período (14 dias)');

    $this->drupalGet('/painel-administrativo/aculta/estatisticas/exportar', [
      'query' => [
        'metrica' => 'wiki.entries.read',
        'dias' => 7,
      ],
    ]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderContains('Content-Type', 'text/csv');
    $this->assertSession()->responseHeaderContains('Content-Disposition', 'wiki.entries.read-7d.csv');
  }

  /**
   * Área inexistente responde 404 para o administrador.
   */
  public function testUnknownAreaIsNotFound(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer aculta_statistics']));
    $this->drupalGet('/painel-administrativo/aculta/estatisticas/area/inexistente');
    $this->assertSession()->statusCodeEquals(404);
  }

}
