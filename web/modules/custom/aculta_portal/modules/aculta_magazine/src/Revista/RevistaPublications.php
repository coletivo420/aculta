<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Revista;

/**
 * Tipos de conteúdo que são publicações da Revista.
 *
 * Todo tipo listado aqui segue as mesmas regras do aculta_magazine: chamada (subtítulo), resumo (descrição e
 * Open Graph), SEO (metatag com [node:editorial-summary]), URL por sessão (/[sessão]/[título]), listagens por
 * sessão e caixa de autores. Ao criar um tipo novo (ex.: vídeo, galeria), adicione-o aqui e siga o docs/README.md.
 * Notícia (article) cobre também o conteúdo do PodPlant, que é uma sessão da Revista.
 */
final class RevistaPublications {

  /**
   * Machine names dos tipos de publicação da Revista.
   */
  public const BUNDLES = ['article', 'blog'];

  /**
   * Indica se o tipo de conteúdo é publicação da Revista.
   */
  public static function isPublication(string $bundle): bool {
    return in_array($bundle, self::BUNDLES, TRUE);
  }

}
