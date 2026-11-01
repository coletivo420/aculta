<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Slug;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Gera slugs públicos em português, minúsculos e sem acentos.
 *
 * O slug é gerado na criação e não muda quando o título muda. Títulos repetidos
 * recebem sufixo numérico (-2, -3).
 */
final class EventSlugGenerator {

  /**
   * Slug usado quando o título não gera nenhum caractere válido.
   */
  private const FALLBACK = 'evento';

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Converte um título em slug, sem consultar o banco.
   */
  public static function fromTitle(string $titulo): string {
    $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $titulo);
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', (string) $ascii), '-');
    return $slug === '' ? self::FALLBACK : $slug;
  }

  /**
   * Slug único entre eventos, ignorando o ID informado.
   */
  public function uniqueFor(string $titulo, ?int $ignorarId = NULL): string {
    $base = self::fromTitle($titulo);
    $candidato = $base;
    $sufixo = 2;
    while ($this->emUso($candidato, $ignorarId)) {
      $candidato = $base . '-' . $sufixo;
      $sufixo++;
    }
    return $candidato;
  }

  /**
   * Indica se o slug já pertence a outro evento.
   */
  private function emUso(string $slug, ?int $ignorarId): bool {
    $consulta = $this->entityTypeManager->getStorage('aculta_event')->getQuery()
      ->condition('field_slug', $slug)
      ->accessCheck(FALSE);
    if ($ignorarId !== NULL) {
      $consulta->condition('id', $ignorarId, '<>');
    }
    return $consulta->count()->execute() > 0;
  }

}
