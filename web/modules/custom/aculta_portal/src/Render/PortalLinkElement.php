<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Render;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;

/**
 * Link do Portal que recebe tokens de design do tema (data-portal-ui).
 *
 * Um #type link passa pelo LinkGenerator, que não tem template de tema: o tema não consegue trocar o token pelas
 * classes. Este helper monta o link como elemento "aculta_portal_element" (tag a), que é um template do tema. O href
 * e a cacheabilidade da URL vêm de Url::toString(TRUE), como no link comum.
 */
final class PortalLinkElement {

  /**
   * @param array<string, string> $attributes
   *   Atributos do link (data-portal-ui e outros). O href é gerado a partir de $url.
   */
  public static function build(mixed $title, Url $url, array $attributes): array {
    $generated = $url->toString(TRUE);
    $attributes = ['href' => $generated->getGeneratedUrl()] + $attributes;
    return [
      '#theme' => 'aculta_portal_element',
      '#tag' => 'a',
      '#value' => $title,
      '#element_attributes' => new Attribute($attributes),
      '#cache' => [
        'contexts' => $generated->getCacheContexts(),
        'tags' => $generated->getCacheTags(),
        'max-age' => $generated->getCacheMaxAge(),
      ],
    ];
  }

}
