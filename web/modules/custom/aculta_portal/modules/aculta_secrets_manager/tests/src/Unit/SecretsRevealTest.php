<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_secrets_manager\Unit;

use Drupal\Core\Render\Markup;
use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\aculta_secrets_manager\Controller\SecretsController;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Revelação (popup do olho) com valor fictício: escape, cache e log sem valor.
 */
#[\PHPUnit\Framework\Attributes\Group('aculta_secrets_manager')]
final class SecretsRevealTest extends UnitTestCase {

  private const FAKE = 'valor<ficticio>&"123';

  /**
   * Temporary directory for credential files.
   */
  private string $base;

  /**
   * Creates a temporary credential directory.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->base = sys_get_temp_dir() . '/aculta-reveal-' . bin2hex(random_bytes(6));
    mkdir($this->base . '/web', 0700, TRUE);
    mkdir($this->base . '/config', 0700, TRUE);
    mkdir($this->base . '/secrets', 0700, TRUE);
    file_put_contents($this->base . '/config/secrets-contract.json', json_encode([
      'variables' => [
        [
          'name' => 'BREVO_API_KEY',
          'key_id' => 'brevo_api_key',
          'environments' => ['production', 'test'],
          'required_in' => [],
        ],
      ],
    ]));
    file_put_contents($this->base . '/secrets/aculta.secrets.env', "BREVO_API_KEY=" . self::FAKE . "\n");
  }

  /**
   * Removes the temporary credential directory.
   */
  protected function tearDown(): void {
    @unlink($this->base . '/secrets/aculta.secrets.env');
    @unlink($this->base . '/config/secrets-contract.json');
    @rmdir($this->base . '/secrets');
    @rmdir($this->base . '/config');
    @rmdir($this->base . '/web');
    @rmdir($this->base);
    parent::tearDown();
  }

  /**
   * Escapes revealed values and records no credential value in logs.
   */
  public function testRevealShowsEscapedValueNoCacheAndLogsOnlyName(): void {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('notice')->with(
      $this->stringContains('@name'),
      $this->callback(fn(array $ctx): bool => ($ctx['@name'] ?? NULL) === 'BREVO_API_KEY' && !str_contains(serialize($ctx), self::FAKE)),
    );
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $settings = new Settings([
      'aculta_secrets_file' => $this->base . '/secrets/aculta.secrets.env',
      'aculta_secrets_environment' => 'test',
      'aculta_secrets_storage' => 'file',
    ]);
    $manager = new SecretsManager($this->createMock(KeyRepositoryInterface::class), $settings, $factory, $this->base . '/web');
    $account = $this->createMock(AccountInterface::class);
    $account->method('getAccountName')->willReturn('qa_segredos');
    $controller = new SecretsController($manager, $this->createMock(FormBuilderInterface::class), $account, $this->getStringTranslationStub());

    $build = $controller->show('BREVO_API_KEY');
    $this->assertSame(['max-age' => 0, 'contexts' => ['user']], $build['#cache']);
    $html = (string) Markup::create(htmlspecialchars(self::FAKE, ENT_QUOTES));
    $this->assertSame($html, (string) $build['value']['text']['#value']);
    $this->assertStringContainsString('&lt;ficticio&gt;', (string) $build['value']['text']['#value']);
    $this->assertStringNotContainsString('<ficticio>', (string) $build['value']['text']['#value']);
  }

}
