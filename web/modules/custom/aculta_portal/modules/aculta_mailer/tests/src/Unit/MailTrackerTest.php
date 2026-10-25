<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Api\PublicUrlInterface;
use Drupal\aculta_mailer\Tracking\MailTracker;
use Drupal\aculta_mailer\Tracking\TrackingConsentChecker;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Rastreamento: só com consentimento e recurso ligado; links só do próprio host; assinaturas válidas.
 */
#[Group('aculta_mailer')]
final class MailTrackerTest extends UnitTestCase {

  private const CORR = '0410e60e-f014-4eb9-bd0f-e3d9b638d42d';

  /**
   * Rastreador com o recurso ligado ou não, e o consentimento informado. O hash_salt pode ser trocado (vazio testa a falha fechada).
   */
  private function tracker(bool $enabled, bool $consent, string $salt = 'segredo-de-teste'): MailTracker {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('tracking_enabled')->willReturn($enabled);
    $configs = $this->createMock(ConfigFactoryInterface::class);
    $configs->method('get')->willReturn($config);
    $urls = $this->createMock(PublicUrlInterface::class);
    $urls->method('absolute')->willReturn('https://example.invalid/');
    $checker = new class($consent) implements TrackingConsentChecker {

      public function __construct(private readonly bool $consent) {}

      /**
       * {@inheritdoc}
       */
      public function hasConsent(string $recipient): bool {
        return $this->consent;
      }

    };
    return new MailTracker($configs, new Settings(['hash_salt' => $salt]), $urls, [$checker]);
  }

  /**
   * Sem consentimento, ou com o recurso desligado, o HTML sai inalterado.
   */
  public function testNoChangeWithoutConsentOrWhenDisabled(): void {
    $html = '<p><a href="https://example.invalid/conta">Conta</a></p></body>';
    $this->assertSame($html, $this->tracker(TRUE, FALSE)->decorate($html, self::CORR, 'a@example.invalid'));
    $this->assertSame($html, $this->tracker(FALSE, TRUE)->decorate($html, self::CORR, 'a@example.invalid'));
  }

  /**
   * Com consentimento: pixel antes do fim do corpo e só o link do próprio host é reescrito.
   */
  public function testDecorateAddsPixelAndRewritesOwnLinksOnly(): void {
    $html = '<p><a href="https://example.invalid/conta">Conta</a> <a href="https://externo.example.invalid/x">Externo</a></p></body>';
    $out = $this->tracker(TRUE, TRUE)->decorate($html, self::CORR, 'a@example.invalid');
    $this->assertStringContainsString('/aculta-mailer/t/o/' . self::CORR . '?s=', $out);
    $this->assertStringContainsString('/aculta-mailer/t/c/' . self::CORR . '?s=', $out);
    $this->assertStringContainsString('href="https://externo.example.invalid/x"', $out, 'link externo não muda');
    $this->assertLessThan(strpos($out, '</body>'), strpos($out, 'aculta-mailer/t/o/'));
  }

  /**
   * Sem hash_salt: o rastreamento fica indisponível e nenhuma assinatura é aceita (a chave vazia seria previsível).
   */
  public function testWithoutSaltTrackingIsUnavailableAndNothingVerifies(): void {
    $html = '<p><a href="https://example.invalid/conta">Conta</a></p></body>';
    $tracker = $this->tracker(TRUE, TRUE, '');
    $this->assertFalse($tracker->enabled());
    $this->assertSame($html, $tracker->decorate($html, self::CORR, 'a@example.invalid'));
    $forged = hash_hmac('sha256', 'o|' . self::CORR, '');
    $this->assertFalse($tracker->verify('o|' . self::CORR, $forged));
  }

  /**
   * Assinatura: correta para o mesmo dado, falha para dado alterado.
   */
  public function testSignatureVerifies(): void {
    $tracker = $this->tracker(TRUE, TRUE);
    $sig = $tracker->sign('o|' . self::CORR);
    $this->assertTrue($tracker->verify('o|' . self::CORR, $sig));
    $this->assertFalse($tracker->verify('o|outra', $sig));
    $this->assertFalse($tracker->verify('o|' . self::CORR, 'assinatura-falsa'));
  }

}
