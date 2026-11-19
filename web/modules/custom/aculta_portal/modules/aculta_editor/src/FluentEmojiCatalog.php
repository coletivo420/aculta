<?php

declare(strict_types=1);

namespace Drupal\aculta_editor;

/**
 * Catálogo curado de emojis Fluent do ACULTA.
 *
 * Fonte única de dados para o seletor do CKEditor 5 e para o filtro
 * fluent_emoji. O arquivo data/fluent-emoji-catalog.json guarda o glifo
 * Unicode nativo (o que vai para o banco), o rótulo em português, a categoria,
 * as palavras-chave de busca e o nome do SVG colorido do tema.
 */
final class FluentEmojiCatalog {

  /**
   * Caminho padrão do catálogo, relativo ao diretório do módulo.
   */
  private const DEFAULT_PATH = __DIR__ . '/../data/fluent-emoji-catalog.json';

  /**
   * Entradas carregadas, indexadas pelo id.
   *
   * @var array<string, array{id: string, name: string, glyph: string, category: string, keywords: list<string>, svg: string}>|null
   */
  private ?array $entries = NULL;

  /**
   * Constrói o catálogo.
   *
   * @param string|null $path
   *   Caminho do JSON. Nulo usa o catálogo do módulo.
   */
  public function __construct(private readonly ?string $path = NULL) {}

  /**
   * Devolve as entradas do catálogo, indexadas pelo id.
   *
   * @return array<string, array{id: string, name: string, glyph: string, category: string, keywords: list<string>, svg: string}>
   *   Entradas do catálogo.
   */
  public function all(): array {
    if ($this->entries !== NULL) {
      return $this->entries;
    }

    $contents = file_get_contents($this->path ?? self::DEFAULT_PATH);
    $decoded = $contents === FALSE ? NULL : json_decode($contents, TRUE);
    if (!is_array($decoded)) {
      throw new \RuntimeException('Catálogo de emojis Fluent ausente ou inválido.');
    }

    $entries = [];
    foreach ($decoded as $entry) {
      if (!is_array($entry) || !isset($entry['id'], $entry['glyph'])) {
        continue;
      }
      $entry['svg'] = $entry['svg'] ?? $entry['id'] . '.svg';
      $entries[(string) $entry['id']] = $entry;
    }

    return $this->entries = $entries;
  }

  /**
   * Obtém uma entrada pelo id.
   *
   * @param string $id
   *   ID do emoji.
   *
   * @return array{id: string, name: string, glyph: string, category: string, keywords: list<string>, svg: string}|null
   *   A entrada encontrada ou nulo.
   */
  public function get(string $id): ?array {
    return $this->all()[$id] ?? null;
  }

  /**
   * Mapeia glifos para entradas, incluindo variantes sem U+FE0F.
   *
   * O seletor de emojis do sistema operacional e o copiar/colar nem sempre
   * preservam o seletor de variação U+FE0F. As duas formas apontam para o mesmo
   * SVG.
   *
   * @return array<string, array{id: string, name: string, glyph: string, category: string, keywords: list<string>, svg: string}>
   *   Entradas indexadas pelo glifo.
   */
  public function byGlyph(): array {
    $map = [];
    foreach ($this->all() as $entry) {
      $glyph = (string) $entry['glyph'];
      $map[$glyph] = $entry;
      $withoutVariation = str_replace("\u{FE0F}", '', $glyph);
      if ($withoutVariation !== $glyph) {
        $map[$withoutVariation] = $entry;
      }
    }
    return $map;
  }

}
