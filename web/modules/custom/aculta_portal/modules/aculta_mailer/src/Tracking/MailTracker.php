<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Tracking;

use Drupal\aculta_mailer\Api\PublicUrlInterface;
use Drupal\aculta_mailer\Delivery\DeliverySettings;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;

/**
 * Rastreamento próprio de aberturas e cliques, só para quem consentiu e só com o recurso ligado.
 *
 * Abertura: imagem de 1 pixel com a assinatura da correlação. Clique: links do próprio site passam por um endereço
 * assinado, que redireciona para o mesmo link. Links externos e o restante do HTML não são alterados. Nada de IP ou de
 * navegador é guardado: só contadores e datas no registro.
 */
final class MailTracker {

  /**
   * Início das rotas de rastreamento, relativo à base pública.
   */
  public const PATH = 'aculta-mailer/t/';

  /**
   * Rastreador de aberturas e cliques.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuração de entrega.
   * @param \Drupal\Core\Site\Settings $settings
   *   Configurações do site (hash_salt).
   * @param \Drupal\aculta_mailer\Api\PublicUrlInterface $publicUrls
   *   Base pública do ambiente.
   * @param iterable $consents
   *   Donos do consentimento (tag aculta_mailer.tracking_consent).
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Settings $settings,
    private readonly PublicUrlInterface $publicUrls,
    private readonly iterable $consents,
  ) {}

  /**
   * Indica se o rastreamento está ligado na configuração de entrega (desligado por padrão).
   */
  public function enabled(): bool {
    return $this->configFactory->get(DeliverySettings::CONFIG)->get('tracking_enabled') === TRUE;
  }

  /**
   * HTML com pixel e links rastreados, quando o recurso está ligado e o destinatário consentiu. Senão, o HTML original.
   */
  public function decorate(string $html, string $correlationId, string $recipient): string {
    if ($html === '' || !$this->enabled() || !$this->consented($recipient)) {
      return $html;
    }
    $base = rtrim($this->publicUrls->absolute('/'), '/');
    $host = (string) parse_url($base, PHP_URL_HOST);
    if ($base === '' || $host === '') {
      return $html;
    }
    $html = (string) preg_replace_callback('/(<a\b[^>]*\bhref=")([^"]+)(")/i', function (array $m) use ($base, $host, $correlationId): string {
      $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);
      if (parse_url($url, PHP_URL_HOST) !== $host || !preg_match('#^https?://#i', $url)) {
        return $m[0];
      }
      $target = $base . '/' . self::PATH . 'c/' . $correlationId . '?s=' . $this->sign('c|' . $correlationId . '|' . $url) . '&u=' . rawurlencode($url);
      return $m[1] . htmlspecialchars($target, ENT_QUOTES) . $m[3];
    }, $html);
    $pixel = '<img src="' . htmlspecialchars($base . '/' . self::PATH . 'o/' . $correlationId . '?s=' . $this->sign('o|' . $correlationId), ENT_QUOTES) . '" width="1" height="1" alt="" style="display:block;border:0;">';
    return stripos($html, '</body>') !== FALSE
      ? (string) preg_replace('/<\/body>/i', $pixel . '</body>', $html, 1)
      : $html . $pixel;
  }

  /**
   * Assinatura de um dado do rastreamento, com o hash_salt do ambiente.
   */
  public function sign(string $data): string {
    return hash_hmac('sha256', $data, (string) $this->settings->get('hash_salt', ''));
  }

  /**
   * Confere a assinatura em tempo constante.
   */
  public function verify(string $data, string $signature): bool {
    return hash_equals($this->sign($data), $signature);
  }

  /**
   * Destinatário com consentimento. Sem dono do consentimento, FALSE.
   */
  private function consented(string $recipient): bool {
    foreach ($this->consents as $checker) {
      if ($checker->hasConsent($recipient)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
