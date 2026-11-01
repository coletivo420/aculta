<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Game;

use Drupal\user\UserInterface;
use Drupal\aculta_messenger\Hook\BadgeAwardNoticeEventProducer;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserDataInterface;

/**
 * Moves pending Game award notices to the shared Message-backed center. */
final class GameAchievementNoticeMigration {

  private const LEGACY_KEY = 'game_achievement_notice_queue';

  public function __construct(
    private readonly Connection $database,
    private readonly UserDataInterface $userData,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly BadgeService $badges,
  ) {}

  /**
   * Migrates one bounded batch of account-owned pending award IDs.
   *
   * @param int $lastUid
   *   UID cursor, advanced only after a user's queue is fully handled.
   * @param int $limit
   *   Maximum number of accounts to process.
   *
   * @return int
   *   Number of account rows visited.
   */
  public function migrateBatch(int &$lastUid, int $limit = 100): int {
    $uids = $this->database->select('users_data', 'ud')
      ->fields('ud', ['uid'])
      ->condition('module', 'aculta_user')
      ->condition('name', self::LEGACY_KEY)
      ->condition('uid', $lastUid, '>')
      ->distinct()
      ->orderBy('uid', 'ASC')
      ->range(0, min(500, max(1, $limit)))
      ->execute()
      ->fetchCol();

    foreach ($uids as $uidValue) {
      $uid = (int) $uidValue;
      $pending = array_values(array_unique(array_filter(array_map(
        'intval',
        (array) $this->userData->get('aculta_user', $uid, self::LEGACY_KEY),
      ), static fn(int $id): bool => $id > 0)));
      $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
      foreach ($pending as $awardId) {
        $award = $this->entityTypeManager->getStorage('aculta_badge_award')->loadUnchanged($awardId);
        if (!$account instanceof UserInterface || !$account->isActive()
          || !$this->isAward($award) || (int) $award->get('uid')->target_id !== $uid
          || !$award->get('revogado_em')->isEmpty()) {
          continue;
        }
        if (!isset($this->badges->definitions()[(string) $award->get('badge')->value])) {
          continue;
        }
        $timestamp = (int) $award->get('concedido_em')->value;
        if ($timestamp < 1) {
          continue;
        }
        $this->publisher->publish(BadgeAwardNoticeEventProducer::event(
          (string) $award->uuid(),
          (int) $award->id(),
          $uid,
          $timestamp,
        ));
      }
      $this->userData->delete('aculta_user', $uid, self::LEGACY_KEY);
      $lastUid = $uid;
    }
    return count($uids);
  }

  /**
   * Confirms the canonical achievement entity type before reading fields.
   */
  private function isAward(mixed $entity): bool {
    return $entity instanceof ContentEntityInterface
      && $entity->getEntityTypeId() === 'aculta_badge_award';
  }

}
