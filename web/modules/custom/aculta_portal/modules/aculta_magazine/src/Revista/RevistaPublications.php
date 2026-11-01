<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Revista;

/**
 * Tipos de conteúdo que são publicações da Revista.
 *
 * As publicações usam os mesmos campos de chamada, resumo e SEO, URLs por
 * sessão, listagens e caixa de autores. Todas usam o bundle `revista`.
 */
final class RevistaPublications {

  /**
   * Machine names dos tipos de publicação da Revista.
   */
  public const CANONICAL_BUNDLE = 'revista';

  /**
   * Único bundle editorial da Revista.
   *
   * @var string[]
   */
  public const BUNDLES = [self::CANONICAL_BUNDLE];

  /**
   * Indica se o tipo de conteúdo é publicação da Revista.
   */
  public static function isPublication(string $bundle): bool {
    return in_array($bundle, self::BUNDLES, TRUE);
  }

}
