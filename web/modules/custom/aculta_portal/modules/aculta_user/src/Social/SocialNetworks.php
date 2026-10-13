<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\profile\Entity\ProfileInterface;

/**
 * Redes sociais do perfil: Instagram, Facebook e Reddit.
 *
 * Os links ficam no campo field_redes_sociais (link, múltiplo). A rede é reconhecida pelo host do link
 * Não há campo novo por rede. O módulo não emite classes de design:
 * o ícone de cada rede é um token (account-social-icon-<rede>) mapeado no ACULTA420.
 */
final class SocialNetworks {

  /**
   * Definições: rótulo, tipo de campo e hosts reconhecidos.
   */
  public const NETWORKS = [
    'instagram' => ['label' => 'Instagram', 'type' => 'url', 'hosts' => ['instagram.com']],
    'facebook' => ['label' => 'Facebook', 'type' => 'url', 'hosts' => ['facebook.com', 'fb.com', 'fb.me']],
    'reddit' => ['label' => 'Reddit', 'type' => 'url', 'hosts' => ['reddit.com', 'redd.it']],
  ];

  /**
   * Chave da rede de um link, ou NULL se não for de uma rede suportada.
   */
  public function keyForUri(string $uri): ?string {
    $host = strtolower((string) parse_url($uri, PHP_URL_HOST));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    foreach (self::NETWORKS as $key => $definition) {
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
   * Valida e normaliza a entrada de uma rede. Vazio devolve NULL (remover). Entrada inválida lança exceção.
   *
   * @throws \InvalidArgumentException
   */
  public function normalize(string $key, string $input): ?string {
    if (!isset(self::NETWORKS[$key])) {
      throw new \InvalidArgumentException('Rede desconhecida.');
    }
    $input = trim($input);
    if ($input === '') {
      return NULL;
    }
    if (!preg_match('~^https?://~i', $input)) {
      $input = 'https://' . $input;
    }
    // Um único endereço: recusa dois esquemas colados (ex.: uma URL repetida dentro de outra).
    if (preg_match('~https?://.*https?://~i', $input) || filter_var($input, FILTER_VALIDATE_URL) === FALSE || $this->keyForUri($input) !== $key) {
      throw new \InvalidArgumentException(sprintf('Informe um link do %s.', self::NETWORKS[$key]['label']));
    }
    return $input;
  }

  /**
   * Grava (ou remove, com NULL) o link de uma rede, mantendo os demais itens do campo.
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
