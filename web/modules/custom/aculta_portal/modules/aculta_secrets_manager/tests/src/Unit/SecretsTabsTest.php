<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_secrets_manager\Unit;

use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\aculta_secrets_manager\Controller\SecretsController;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests tabs and access isolation between environments.
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_secrets_manager')]
final class SecretsTabsTest extends UnitTestCase {

  /**
   * Temporary directory for credential files.
   */
  private string $base;

  /**
   * Creates a temporary credential directory.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-tabs-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/config', 0700, TRUE);
    mkdir($this->base . '/secrets', 0700, TRUE);
    file_put_contents($this->base . '/config/secrets-contract.json', json_encode([
      'variables' => [
        [
          'name' => 'GOOGLE_OAUTH_CLIENT_ID',
          'key_id' => 'g_id',
          'environments' => ['production', 'test'],
          'required_in' => ['production', 'test'],
        ],
        [
          'name' => 'TURNSTILE_KEYS_JSON',
          'key_id' => 'turnstile',
          'environments' => ['production'],
          'required_in' => ['production'],
        ],
        [
          'name' => 'EXEMPLO_SENHA',
          'key_id' => 'smtp_pw',
          'environments' => ['test'],
          'required_in' => [],
        ],
      ],
    ]));
  }

  /**
   * Removes the temporary credential directory.
   */
  protected function tearDown(): void {
    foreach (glob($this->base . '/secrets/*') ?: [] as $f) {
      @unlink($f);
    }
    @rmdir($this->base . '/secrets');
    @unlink($this->base . '/config/secrets-contract.json');
    @rmdir($this->base . '/config');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  /**
   * Activates the tab for the current server environment.
   */
  public function testActiveEnvironmentOpensItsOwnTab(): void {
    $page = $this->controller('test', "TURNSTILE_KEYS_JSON=segredo-ficticio\n")->page();
    $buttons = $page['tabs']['list'];
    $this->assertSame('true', $buttons['test']['#attributes']['aria-selected']);
    $this->assertSame('false', $buttons['production']['#attributes']['aria-selected']);
  }

  /**
   * Omits values and actions for the inactive environment.
   */
  public function testOtherEnvironmentPanelHasNoValuesOrActions(): void {
    $page = $this->controller('test', "TURNSTILE_KEYS_JSON=segredo-ficticio\n")->page();
    $rows = $page['tabs']['section_production']['table_wrapper']['table']['#rows'];
    $this->assertNotEmpty($rows);
    foreach ($rows as $row) {
      $this->assertSame('Não consultado neste servidor', (string) $row[3]['data']['#plain_text']);
      $this->assertArrayNotHasKey('eye', $row[3]['data']);
    }
    $this->assertStringNotContainsString('segredo-ficticio', serialize($page));
  }

  /**
   * Denies credential actions outside the current environment.
   */
  public function testOutOfScopeCredentialCannotBeRevealedEditedOrCleared(): void {
    $controller = $this->controller('test', "TURNSTILE_KEYS_JSON=segredo-ficticio\n");
    $this->expectException(AccessDeniedHttpException::class);
    $controller->show('TURNSTILE_KEYS_JSON');
  }

  /**
   * Denies editing a credential outside the current environment.
   */
  public function testOutOfScopeEditIsDenied(): void {
    $controller = $this->controller('test', '');
    $this->expectException(AccessDeniedHttpException::class);
    $controller->edit('TURNSTILE_KEYS_JSON');
  }

  /**
   * Denies clearing a credential outside the current environment.
   */
  public function testOutOfScopeClearIsDenied(): void {
    $controller = $this->controller('test', '');
    $this->expectException(AccessDeniedHttpException::class);
    $controller->clear('TURNSTILE_KEYS_JSON');
  }

  /**
   * Keeps panel styles available when Claro does not run the ACULTA420 hook.
   */
  public function testClaroAdminPanelCssPairsEveryTokenWithItsClass(): void {
    $css = file_get_contents(dirname(__DIR__, 3) . '/css/secrets-tabs.css');
    $this->assertIsString($css);
    $pairs = [
      'secrets-section' => 'aculta-portal-secrets-section',
      'secret-value' => 'aculta-secret-value',
      'secret-value-text' => 'aculta-secret-value__text',
      'secret-value-eye' => 'aculta-secret-value__eye',
      'secret-value-edit' => 'aculta-secret-value__edit',
      'secret-value-clear' => 'aculta-secret-value__clear',
      'secrets-tab' => 'secrets-tabs__tab',
    ];
    foreach ($pairs as $token => $class) {
      $pattern = '/\.' . preg_quote($class, '/') . '\s*,\s*\[data-portal-ui~="' . preg_quote($token, '/') . '"\]/';
      $this->assertMatchesRegularExpression($pattern, $css, $token);
    }
    foreach (['secret-value-eye', 'secret-value-edit', 'secret-value-clear', 'secrets-tab'] as $token) {
      $this->assertMatchesRegularExpression(
        '/\[data-portal-ui~="' . preg_quote($token, '/') . '"\]:focus-visible/',
        $css,
        $token,
      );
    }
    $this->assertStringContainsString('min-inline-size: 44px', $css);
    $this->assertStringContainsString('min-block-size: 44px', $css);
    $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|rgba?\(/i', $css);
  }

  /**
   * Creates a controller scoped to one environment.
   */
  private function controller(string $environment, string $store): SecretsController {
    file_put_contents($this->base . '/secrets/aculta.secrets.env', $store);
    $keys = $this->createMock(KeyRepositoryInterface::class);
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $this->base . '/secrets/aculta.secrets.env',
      'aculta_secrets_environment' => $environment,
      // Database storage does not require a container for the test panel links.
      'aculta_secrets_storage' => 'database',
    ]);
    $manager = new SecretsManager($keys, $settings, $factory, $this->base . '/web');
    return new SecretsController(
      $manager,
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(AccountInterface::class),
      $this->getStringTranslationStub(),
    );
  }

}
