<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Functional;

use Drupal\Core\Session\AccountInterface;
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
    // Keep its provider modules explicit in this isolated site.
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

    $baseUrl = (string) getenv('SIMPLETEST_BASE_URL');
    $host = (string) parse_url($baseUrl, PHP_URL_HOST);
    $scheme = (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http');

    Domain::create([
      'id' => 'aculta_org',
      'hostname' => $host,
      'name' => 'ACULTA MAIN functional test',
      'scheme' => $scheme,
      'status' => TRUE,
      'is_default' => TRUE,
    ])->save();

    // O login canônico pertence ao host de Conta. O par de Domains e o
    // cookie_domain de testing.services.yml mantêm a sessão nos dois hosts.
    Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => 'conta.' . $host,
      'name' => 'ACULTA Conta functional test',
      'scheme' => $scheme,
      'status' => TRUE,
      'weight' => 2,
      'is_default' => FALSE,
    ])->save();
  }

  /**
   * Entra pelo formulário canônico no host de Conta.
   */
  protected function drupalLogin(AccountInterface $account) {
    $base = (string) getenv('SIMPLETEST_BASE_URL');
    $parts = parse_url($base);
    $accountUrl = $parts['scheme'] . '://conta.' . $parts['host']
      . (isset($parts['port']) ? ':' . $parts['port'] : '') . '/entrar';
    $this->drupalGet($accountUrl);
    $this->getSession()->getPage()->fillField('name', $account->getAccountName());
    $this->getSession()->getPage()->fillField('pass', $account->passRaw);
    $button = $this->getSession()->getPage()->find('css', 'input[type="submit"], button[type="submit"]');
    $this->assertNotNull($button, 'Botão de login ausente.');
    $button->click();
    $name = \Drupal::service('session_configuration')->getOptions(\Drupal::request())['name'];
    $cookie = $this->getSession()->getCookie($name);
    $account->sessionId = $cookie;
    $this->assertTrue($this->drupalUserIsLoggedIn($account), 'Login no host de Conta falhou.');
    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
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
