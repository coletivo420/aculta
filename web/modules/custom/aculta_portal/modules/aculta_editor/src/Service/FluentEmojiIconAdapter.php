<?php

declare(strict_types=1);

namespace Drupal\aculta_editor\Service;

use Drupal\aculta_editor\FluentEmojiCatalog;
use Drupal\Core\Theme\Icon\IconDefinition;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;

/**
 * Adaptador centralizado entre UI Icons e FluentEmojiCatalog.
 */
final class FluentEmojiIconAdapter implements FluentEmojiIconAdapterInterface {

  public function __construct(
    private readonly FluentEmojiCatalog $catalog,
    private readonly IconPackManagerInterface $iconPackManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function toIconId(string $id): ?string {
    $cleanId = $this->fromIconId($id) ?? $id;
    $entry = $this->catalog->get($cleanId);
    if ($entry === NULL) {
      return NULL;
    }
    return self::ICON_PACK . ':' . $entry['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function fromIconId(string $iconFullId): ?string {
    if (str_contains($iconFullId, ':')) {
      $data = IconDefinition::getIconDataFromId($iconFullId);
      if (!$data || $data['pack_id'] !== self::ICON_PACK) {
        return NULL;
      }
      return $data['icon_id'];
    }
    return $this->catalog->get($iconFullId) !== NULL ? $iconFullId : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnicode(string $idOrIconFullId): ?string {
    $id = $this->fromIconId($idOrIconFullId) ?? $idOrIconFullId;
    $entry = $this->catalog->get($id);
    return $entry['glyph'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(string $idOrIconFullId): bool {
    return $this->fromIconId($idOrIconFullId) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCategories(): array {
    $categories = [];
    foreach ($this->catalog->all() as $id => $item) {
      $cat = $item['category'] ?? 'outros';
      $item['icon_full_id'] = self::ICON_PACK . ':' . $id;
      $categories[$cat][$id] = $item;
    }
    return $categories;
  }

  /**
   * {@inheritdoc}
   */
  public function search(string $query): array {
    $trimmed = trim(mb_strtolower($query));
    if ($trimmed === '') {
      return $this->catalog->all();
    }

    $results = [];
    foreach ($this->catalog->all() as $id => $item) {
      $name = mb_strtolower((string) $item['name']);
      if (str_contains($name, $trimmed) || str_contains($id, $trimmed)) {
        $item['icon_full_id'] = self::ICON_PACK . ':' . $id;
        $results[$id] = $item;
        continue;
      }
      if (!empty($item['keywords'])) {
        foreach ($item['keywords'] as $kw) {
          if (str_contains(mb_strtolower((string) $kw), $trimmed)) {
            $item['icon_full_id'] = self::ICON_PACK . ':' . $id;
            $results[$id] = $item;
            break;
          }
        }
      }
    }
    return $results;
  }

}
