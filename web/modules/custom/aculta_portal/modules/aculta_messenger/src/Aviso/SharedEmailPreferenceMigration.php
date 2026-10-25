<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Core\Database\Connection;
use Drupal\user\UserDataInterface;

/**
 * Migrates legacy global digest choices to Messenger email categories. */
final class SharedEmailPreferenceMigration {

  private const LEGACY_GLOBAL = 'avisos_resumo_email';

  private const LEGACY_FORUM_KEYS = [
    'forum_resposta' => 'avisos_resumo_forum_resposta',
    'forum_atualizacao' => 'avisos_resumo_forum_atualizacao',
  ];

  /**
   * Shared notice categories in migration order.
   *
   * @var list<string>
   */
  private const CATEGORIES = [
    'forum_resposta',
    'forum_atualizacao',
    'aprendizagem',
    'wiki',
    'loja',
    'doacao',
  ];

  public function __construct(
    private readonly UserDataInterface $userData,
    private readonly Connection $database,
  ) {}

  /**
   * Migrates one bounded batch, preserving explicit Messenger choices.
   *
   * @param int $lastUid
   *   UID cursor, updated to the last visited account.
   * @param int $limit
   *   Maximum accounts to process.
   *
   * @return int
   *   Number of accounts found in this batch.
   */
  public function migrateBatch(int &$lastUid, int $limit = 500): int {
    $limit = min(500, max(1, $limit));
    $query = $this->database->select('users_data', 'ud')
      ->fields('ud', ['uid'])
      ->condition('module', AvisoPreferencias::MODULO)
      ->condition('name', [self::LEGACY_GLOBAL, ...array_values(self::LEGACY_FORUM_KEYS)], 'IN')
      ->condition('uid', $lastUid, '>')
      ->distinct()
      ->orderBy('uid', 'ASC')
      ->range(0, $limit);
    $uids = $query->execute()->fetchCol();

    foreach ($uids as $uid) {
      $uid = (int) $uid;
      $globalOptIn = (bool) $this->userData->get(AvisoPreferencias::MODULO, $uid, self::LEGACY_GLOBAL);
      foreach (self::CATEGORIES as $category) {
        $key = 'avisos_email_mode_' . $category;
        if ($this->userData->get(AvisoPreferencias::MODULO, $uid, $key) === NULL) {
          $mode = $globalOptIn ? AvisoPreferencias::EMAIL_DIGEST : AvisoPreferencias::EMAIL_OFF;
          $legacyForumKey = self::LEGACY_FORUM_KEYS[$category] ?? NULL;
          if ($globalOptIn && $legacyForumKey !== NULL
            && $this->userData->get(AvisoPreferencias::MODULO, $uid, $legacyForumKey) !== NULL
            && !(bool) $this->userData->get(AvisoPreferencias::MODULO, $uid, $legacyForumKey)) {
            $mode = AvisoPreferencias::EMAIL_OFF;
          }
          $this->userData->set(AvisoPreferencias::MODULO, $uid, $key, $mode);
        }
      }
      $this->userData->delete(AvisoPreferencias::MODULO, $uid, self::LEGACY_GLOBAL);
      foreach (self::LEGACY_FORUM_KEYS as $legacyKey) {
        $this->userData->delete(AvisoPreferencias::MODULO, $uid, $legacyKey);
      }
      $lastUid = $uid;
    }

    return count($uids);
  }

}
