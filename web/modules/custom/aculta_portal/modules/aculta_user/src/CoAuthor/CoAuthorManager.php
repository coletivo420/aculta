<?php

declare(strict_types=1);

namespace Drupal\aculta_user\CoAuthor;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Normalizes references to ACULTA node co-authors without granting access.
 *
 * The module-contributed co_authors field is intentionally never read. Its
 * removal and the activation of the new field are one operational cutover.
 */
final class CoAuthorManager implements CoAuthorManagerInterface {

  public const FIELD_NAME = 'aculta_coauthors';

  /**
   * {@inheritdoc}
   */
  public function getCoAuthorIds(NodeInterface $node): array {
    if (!$node->hasField(self::FIELD_NAME)) {
      return [];
    }

    $ownerId = (int) $node->getOwnerId();
    $ids = [];
    /** @var array<int, array<string, mixed>> $rawValues */
    $rawValues = (array) $node->get(self::FIELD_NAME)->getValue();

    foreach ($rawValues as $item) {
      if (!isset($item['target_id']) || !is_scalar($item['target_id'])) {
        continue;
      }
      $value = (string) $item['target_id'];
      if (!ctype_digit($value)) {
        continue;
      }
      $uid = (int) $value;
      if ($uid <= 0 || $uid === $ownerId) {
        continue;
      }
      $ids[$uid] = $uid;
    }
    return array_values($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function isCoAuthor(NodeInterface $node, AccountInterface $account): bool {
    if (!$account->isAuthenticated()) {
      return FALSE;
    }

    $uid = (int) $account->id();
    return $uid > 0 && in_array($uid, $this->getCoAuthorIds($node), TRUE);
  }

}
