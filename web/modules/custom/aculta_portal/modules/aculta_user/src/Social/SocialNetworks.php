<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\profile\Entity\ProfileInterface;

/**
 * Redes sociais do perfil: Instagram, Facebook e Reddit.
 *
 * Os links ficam no campo field_redes_sociais (link, múltiplo). A rede é
 * reconhecida pelo host do link.
 * Não há campo novo por rede. O módulo não emite classes de design:
 * o ícone usa o token account-social-icon-<rede> do ACULTA420.
 */
final class SocialNetworks implements SocialNetworksInterface {

  /**
   * Backward-compatible access to the shared network definitions.
   */
  public const NETWORKS = SocialNetworksInterface::NETWORKS;

  /**
   * Chave da rede de um link, ou NULL se não for de uma rede suportada.
   */
  public function keyForUri(string $uri): ?string {
    $scheme = strtolower((string) parse_url($uri, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], TRUE)
      || filter_var($uri, FILTER_VALIDATE_URL) === FALSE) {
      return NULL;
    }
    $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    foreach (SocialNetworksInterface::NETWORKS as $key => $definition) {
      foreach ($definition['hosts'] as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
          return $key;
        }
      }
    }
    return NULL;
  }

  /**
   * Links das redes do perfil, por chave de rede (primeiro link de cada rede).
   *
   * @return array<string, string>
   *   Network machine name to URL.
   */
  public function linksOf(ProfileInterface $profile): array {
    $links = [];
    if (!$profile->hasField('field_redes_sociais')) {
      return $links;
    }
    foreach ($profile->get('field_redes_sociais') as $item) {
      $uri = (string) $item->uri;
      $key = $uri !== '' ? $this->keyForUri($uri) : NULL;
      if ($key !== NULL && !isset($links[$key])) {
        $links[$key] = $uri;
      }
    }
    return $links;
  }

  /**
   * Normaliza a URL de uma rede social suportada.
   *
   * Vazio devolve NULL; entrada inválida lança exceção.
   *
   * @throws \InvalidArgumentException
   */
  public function normalize(string $key, string $input): ?string {
    if (!isset(SocialNetworksInterface::NETWORKS[$key])) {
      throw new \InvalidArgumentException('Rede desconhecida.');
    }
    $input = trim($input);
    if ($input === '') {
      return NULL;
    }
    if (!preg_match('~^https?://~i', $input)) {
      $input = 'https://' . $input;
    }
    // Recusa dois esquemas colados, como uma URL dentro de outra.
    if (preg_match('~https?://.*https?://~i', $input)
      || filter_var($input, FILTER_VALIDATE_URL) === FALSE
      || $this->keyForUri($input) !== $key) {
      throw new \InvalidArgumentException(sprintf(
        'Informe um link do %s.',
        SocialNetworksInterface::NETWORKS[$key]['label'],
      ));
    }
    return $input;
  }

  /**
   * Grava ou remove o link de uma rede, mantendo os demais itens do campo.
   */
  public function setLink(ProfileInterface $profile, string $key, ?string $uri): void {
    $items = [];
    foreach ($profile->get('field_redes_sociais') as $item) {
      $existing = (string) $item->uri;
      if ($existing !== '' && $this->keyForUri($existing) === $key) {
        continue;
      }
      $items[] = ['uri' => $existing, 'title' => (string) $item->title];
    }
    if ($uri !== NULL) {
      $items[] = ['uri' => $uri, 'title' => ''];
    }
    $profile->set('field_redes_sociais', $items);
  }

}
