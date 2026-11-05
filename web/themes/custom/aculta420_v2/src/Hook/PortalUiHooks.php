<?php

declare(strict_types=1);

namespace Drupal\aculta420_v2\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;

/**
 * Traduz data-portal-ui em classes do ACULTA420 V2.
 *
 * O módulo marca o elemento com data-portal-ui="token" e não escreve classe de
 * apresentação. Este hook troca cada token pelas classes correspondentes e remove
 * o atributo. Token desconhecido não gera classe.
 *
 * Mapa inicial: só os tokens migrados para o V2. Os demais continuam no mapa do
 * V1 (web/themes/custom/aculta420/src/Hook/PortalUiHooks.php) até a migração,
 * registrada em docs/architecture.md, seção 7.
 */
final class PortalUiHooks {

  public const ATTRIBUTE = 'data-portal-ui';

  /**
   * Mapa token → classes do V2. A ordem dos tokens define a ordem das classes.
   */
  public const TOKENS = [
    'surface-list-edit-action' => ['button', 'button--small'],
  ];

  #[Hook('preprocess_link')]
  public function preprocessLink(array &$variables): void {
    if (($variables['attributes'] ?? NULL) instanceof Attribute) {
      $this->apply($variables['attributes']);
    }
  }

  private function apply(Attribute $attributes): void {
    if (!$attributes->offsetExists(self::ATTRIBUTE)) {
      return;
    }
    $tokens = preg_split('/\s+/', trim((string) $attributes[self::ATTRIBUTE]->value())) ?: [];
    $attributes->removeAttribute(self::ATTRIBUTE);
    foreach ($tokens as $token) {
      foreach (self::TOKENS[$token] ?? [] as $class) {
        $attributes->addClass($class);
      }
    }
  }

}
