<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Functional;

use Drupal\domain\Entity\Domain;
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

    // Em host compartilhado, o login canônico é redirecionado para o host de
    // Conta, que não serve este site de teste. Exigir origem isolada evita
    // exercitar o Runtime por acidente.
    $host = (string) parse_url((string) getenv('SIMPLETEST_BASE_URL'), PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost'], TRUE)) {
      $this->markTestSkipped('Functional exige origem HTTP isolada; o host compartilhado redireciona o login para o domínio Conta.');
    }

    // O Domain principal acompanha a origem real do site de teste; fixar
    // 127.0.0.1 quebra a sessão quando a base é servida por outro host.
    $baseUrl = (string) getenv('SIMPLETEST_BASE_URL');
    Domain::create([
      'id' => 'aculta_org',
      'hostname' => (string) parse_url($baseUrl, PHP_URL_HOST),
      'name' => 'ACULTA MAIN functional test',
      'scheme' => (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http'),
      'status' => TRUE,
      'is_default' => TRUE,
    ])->save();

    // O login canônico acontece no host de Conta. No site de teste, gravar o
    // Domain diretamente na configuração permite o mesmo host do MAIN, sem o
    // redirect para um host que não serve o site de teste.
    \Drupal::configFactory()->getEditable('domain.record.conta_aculta_org')
      ->setData([
        'uuid' => \Drupal::service('uuid')->generate(),
        'langcode' => 'en',
        'status' => TRUE,
        'dependencies' => [],
        'id' => 'conta_aculta_org',
        'domain_id' => 1,
        'hostname' => (string) parse_url($baseUrl, PHP_URL_HOST),
        'name' => 'ACULTA Conta functional test',
        'scheme' => (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http'),
        'weight' => 2,
        'is_default' => FALSE,
        'path_prefix' => '',
      ])
      ->save();
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
