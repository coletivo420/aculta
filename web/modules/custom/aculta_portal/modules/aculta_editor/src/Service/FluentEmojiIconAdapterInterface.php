<?php

declare(strict_types=1);

namespace Drupal\aculta_editor\Service;

/**
 * Contrato para adaptador entre UI Icons e o FluentEmojiCatalog.
 */
interface FluentEmojiIconAdapterInterface {

  public const ICON_PACK = 'aculta_fluent_emoji';

  /**
   * Converte ID de reação ou emoji em ID completo do UI Icons (ex: 'aculta_fluent_emoji:herb').
   */
  public function toIconId(string $id): ?string;

  /**
   * Extrai o ID do emoji a partir do ID completo do UI Icons.
   */
  public function fromIconId(string $iconFullId): ?string;

  /**
   * Obtém o caractere Unicode nativo a partir do ID do emoji ou do UI Icons.
   */
  public function getUnicode(string $idOrIconFullId): ?string;

  /**
   * Valida se um identificador pertence exclusivamente ao icon pack permitido.
   */
  public function isValid(string $idOrIconFullId): bool;

  /**
   * Retorna os itens do catálogo organizados por categoria com metadados para seletores.
   *
   * @return array<string, array<string, mixed>>
   */
  public function getCategories(): array;

  /**
   * Busca itens por texto (nome ou palavras-chave).
   *
   * @return array<string, array<string, mixed>>
   */
  public function search(string $query): array;

}
