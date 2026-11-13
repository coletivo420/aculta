<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Kernel;

use Drupal\aculta_statistics\Hook\ConsentBannerHooks;
use Drupal\Core\Session\AccountProxyInterface;
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
  protected static $modules = ['aculta_statistics', 'aculta_file_uploader', 'file' ];

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
    $this->assertSame('/sobre/politica-de-privacidade', $consent['privacyUrl']);
  }

  /**
   * Enabled anonymous visitors receive the SDC banner in page_bottom.
   */
  public function testEnabledAddsConsentBannerComponent(): void {
    $this->config('aculta_statistics.settings')->set('consent_banner.enabled', TRUE)->save();
    $pageBottom = [];

    $this->hook()->pageBottom($pageBottom);

    $this->assertSame('component', $pageBottom['aculta_statistics_consent_banner']['#type']);
    $this->assertSame('aculta420_v2:consent-banner', $pageBottom['aculta_statistics_consent_banner']['#component']);
    $this->assertSame('/sobre/politica-de-privacidade', $pageBottom['aculta_statistics_consent_banner']['#props']['privacy_url']);
  }

  /**
   * Authenticated visitors do not receive the anonymous consent prompt.
   */
  public function testAuthenticatedVisitorDoesNotReceiveConsentBanner(): void {
    $this->config('aculta_statistics.settings')->set('consent_banner.enabled', TRUE)->save();
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('isAnonymous')->willReturn(FALSE);
    $pageBottom = [];

    $this->hook($user)->pageBottom($pageBottom);

    $this->assertArrayNotHasKey('aculta_statistics_consent_banner', $pageBottom);
  }

  /**
   * Cria o hook com as dependências do container de teste.
   */
  private function hook(?AccountProxyInterface $currentUser = NULL): ConsentBannerHooks {
    return new ConsentBannerHooks($this->container->get('config.factory'), $currentUser ?? $this->container->get('current_user'));
  }

}
