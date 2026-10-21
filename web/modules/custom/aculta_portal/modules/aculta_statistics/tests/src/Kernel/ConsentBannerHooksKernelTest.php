<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Hook\ConsentBannerHooks;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Banner de consentimento: anexos só quando habilitado, cache invalidado pela configuração.
 */
#[CoversClass(ConsentBannerHooks::class)]
#[Group('aculta_statistics')]
#[RunTestsInSeparateProcesses]
final class ConsentBannerHooksKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_statistics'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['aculta_statistics']);
  }

  /**
   * Desabilitado (padrão): nenhuma biblioteca nem configuração é anexada.
   */
  public function testDisabledAttachesNothing(): void {
    $attachments = [];
    $this->hook()->pageAttachments($attachments);

    $this->assertArrayNotHasKey('library', $attachments['#attached'] ?? []);
    $this->assertContains('config:aculta_statistics.settings', $attachments['#cache']['tags']);
  }

  /**
   * Habilitado: biblioteca do consentimento e configuração de cookie e Consent Mode.
   */
  public function testEnabledAttachesLibraryAndSettings(): void {
    $this->config('aculta_statistics.settings')->set('consent_banner.enabled', TRUE)->save();

    $attachments = [];
    $this->hook()->pageAttachments($attachments);

    $this->assertContains('aculta_statistics/consent', $attachments['#attached']['library']);
    $consent = $attachments['#attached']['drupalSettings']['acultaStatistics']['consent'];
    $this->assertSame(ConsentBannerHooks::COOKIE_NAME, $consent['cookieName']);
    $this->assertSame(ConsentBannerHooks::COOKIE_VERSION, $consent['version']);
    $this->assertSame('/politica-de-privacidade', $consent['privacyUrl']);
  }

  /**
   * Cria o hook com as dependências do container de teste.
   */
  private function hook(): ConsentBannerHooks {
    return new ConsentBannerHooks($this->container->get('config.factory'));
  }

}
