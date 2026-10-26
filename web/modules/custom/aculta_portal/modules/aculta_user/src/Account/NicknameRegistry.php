<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Account;

use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Maintains the rebuildable, transactional UID-to-public-slug projection.
 */
final class NicknameRegistry {

  /**
   * UIDs currently being synchronized to prevent recursive Profile saves.
   *
   * @var array<int, bool>
   */
  private array $synchronizing = [];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Reserves a canonical public slug for one UID.
   *
   * Call from a Profile entity presave hook. Drupal's SQL entity storage wraps
   * this hook and the Profile row write in one database transaction.
   */
  public function claim(int $uid, string $slug): void {
    if ($uid <= 0 || !AccountNames::isValid($slug) || AccountNames::publicRouteSlug($slug) !== $slug) {
      throw new \InvalidArgumentException('A valid canonical nickname slug and UID are required.');
    }

    $owner = $this->database->select('aculta_user_nickname', 'n')
      ->fields('n', ['uid'])
      ->condition('slug', $slug)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    if ($owner !== FALSE && (int) $owner !== $uid) {
      throw new NicknameClaimConflictException('This public nickname is already in use.');
    }

    $existing = $this->database->select('aculta_user_nickname', 'n')
      ->fields('n', ['uid'])
      ->condition('uid', $uid)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    try {
      if ($existing !== FALSE) {
        $this->database->update('aculta_user_nickname')
          ->fields(['slug' => $slug])
          ->condition('uid', $uid)
          ->execute();
        return;
      }
      $this->database->insert('aculta_user_nickname')
        ->fields(['uid' => $uid, 'slug' => $slug])
        ->execute();
    }
    catch (IntegrityConstraintViolationException) {
      // A concurrent claimant can pass the preflight read. The unique SQL
      // indexes remain the final authority; never expose the driver exception.
      throw new NicknameClaimConflictException('This public nickname is already in use.');
    }
  }

  /**
   * Checks whether another UID owns the given canonical slug.
   */
  public function isClaimedByOther(string $slug, ?int $excludeUid = NULL): bool {
    $query = $this->database->select('aculta_user_nickname', 'n')
      ->fields('n', ['uid'])
      ->condition('slug', $slug)
      ->range(0, 1);
    if ($excludeUid !== NULL) {
      $query->condition('uid', $excludeUid, '<>');
    }
    return $query->execute()->fetchField() !== FALSE;
  }

  /**
   * Resolves the owner of a current slug, or NULL when no claim exists.
   */
  public function uidForSlug(string $slug): ?int {
    $uid = $this->database->select('aculta_user_nickname', 'n')
      ->fields('n', ['uid'])
      ->condition('slug', $slug)
      ->range(0, 1)
      ->execute()
      ->fetchField();
    return $uid === FALSE ? NULL : (int) $uid;
  }

  /**
   * Synchronizes same-UID role Profiles and their current slug claim.
   */
  public function synchronizeProfile(ProfileInterface $profile): void {
    if (!PerfilDoPapel::ehTipoDePapel($profile) || !$profile->hasField('field_nickname')) {
      return;
    }
    $uid = (int) $profile->getOwnerId();
    $nickname = trim((string) $profile->get('field_nickname')->value);
    if ($uid <= 0 || isset($this->synchronizing[$uid])) {
      return;
    }
    $slug = AccountNames::publicRouteSlug($nickname);
    if ($nickname === '') {
      if ($profile->isNew()) {
        return;
      }
      $original = $this->entityTypeManager->getStorage('profile')->loadUnchanged($profile->id());
      $originalNickname = $original instanceof ProfileInterface && $original->hasField('field_nickname')
        ? trim((string) $original->get('field_nickname')->value)
        : '';
      if ($originalNickname === '') {
        return;
      }
      $this->synchronizing[$uid] = TRUE;
      try {
        $this->syncSiblingProfiles($profile, $uid, '');
        $this->database->delete('aculta_user_nickname')->condition('uid', $uid)->execute();
      }
      finally {
        unset($this->synchronizing[$uid]);
      }
      return;
    }
    if ($slug === NULL) {
      throw new \InvalidArgumentException('The profile nickname does not have a valid public slug.');
    }
    $this->claim($uid, $slug);
    $this->synchronizing[$uid] = TRUE;
    try {
      $this->syncSiblingProfiles($profile, $uid, $nickname);
    }
    finally {
      unset($this->synchronizing[$uid]);
    }
  }

  /**
   * Saves the current display nickname to the UID's other role Profiles.
   */
  private function syncSiblingProfiles(ProfileInterface $profile, int $uid, string $nickname): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('type', PerfilDoPapel::TIPOS, 'IN')
      ->execute();
    foreach ($storage->loadMultiple($ids) as $sibling) {
      if (!$sibling instanceof ProfileInterface
        || (string) $sibling->id() === (string) $profile->id()
        || !$sibling->hasField('field_nickname')
        || trim((string) $sibling->get('field_nickname')->value) === $nickname) {
        continue;
      }
      $sibling->set('field_nickname', $nickname);
      $sibling->save();
    }
  }

  /**
   * Removes a UID claim after its final role Profile is deleted.
   */
  public function removeDeletedProfile(ProfileInterface $profile): void {
    if (!PerfilDoPapel::ehTipoDePapel($profile)) {
      return;
    }
    $uid = (int) $profile->getOwnerId();
    if ($uid <= 0) {
      return;
    }
    $ids = $this->entityTypeManager->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('type', PerfilDoPapel::TIPOS, 'IN')
      ->execute();
    if ($ids === []) {
      $this->database->delete('aculta_user_nickname')->condition('uid', $uid)->execute();
    }
  }

  /**
   * Rebuilds the derived index after validating existing Profiles.
   *
   * @return array{uids: int, profiles: int, same_uid_conflicts: int, reconciled_profiles: int}
   *   Aggregate counts only; no nickname or personal data is returned.
   */
  public function rebuild(): array {
    $profilesByUid = [];
    $nicknameCandidates = [];
    $slugOwners = [];
    $profileCount = 0;
    $slugConflicts = 0;
    $invalid = 0;
    $storage = $this->entityTypeManager->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', PerfilDoPapel::TIPOS, 'IN')
      ->execute();

    foreach ($storage->loadMultiple($ids) as $profile) {
      if (!$profile instanceof ProfileInterface) {
        continue;
      }
      $profileCount++;
      $uid = (int) $profile->getOwnerId();
      if ($uid <= 0 || !$profile->hasField('field_nickname')) {
        $invalid++;
        continue;
      }
      $profilesByUid[$uid][] = $profile;
      $nickname = trim((string) $profile->get('field_nickname')->value);
      // Empty values do not vote against an explicit nickname. If every
      // Profile for this UID is empty, the UID has no public nickname claim.
      if ($nickname === '') {
        continue;
      }
      $slug = AccountNames::publicRouteSlug($nickname);
      if ($slug === NULL) {
        $invalid++;
        continue;
      }
      $nicknameCandidates[$uid][] = [
        'nickname' => $nickname,
        'slug' => $slug,
        'changed' => (int) $profile->getChangedTime(),
        'id' => (int) $profile->id(),
      ];
    }

    $uids = [];
    $canonicalNicknames = [];
    $sameUidConflicts = 0;
    foreach ($nicknameCandidates as $uid => $candidates) {
      $distinctNicknames = array_unique(array_column($candidates, 'nickname'));
      if (count($distinctNicknames) > 1) {
        $sameUidConflicts++;
      }
      // Latest changed timestamp wins. Profile ID is a stable tie-breaker
      // because changed timestamps have one-second precision.
      usort($candidates, static function (array $left, array $right): int {
        return ($right['changed'] <=> $left['changed']) ?: ($right['id'] <=> $left['id']);
      });
      $winner = $candidates[0];
      $slug = $winner['slug'];
      if (isset($slugOwners[$slug]) && $slugOwners[$slug] !== (int) $uid) {
        $slugConflicts++;
        continue;
      }
      $uids[$uid] = $slug;
      $canonicalNicknames[$uid] = $winner['nickname'];
      $slugOwners[$slug] = (int) $uid;
    }

    if ($invalid > 0 || $slugConflicts > 0) {
      throw new \RuntimeException(sprintf(
        'Nickname index rebuild blocked: invalid=%d, same-UID conflicts=%d, cross-UID conflicts=%d.',
        $invalid,
        $sameUidConflicts,
        $slugConflicts,
      ));
    }

    $reconciledProfiles = 0;
    $transaction = $this->database->startTransaction();
    try {
      $this->database->delete('aculta_user_nickname')->execute();
      foreach ($uids as $uid => $slug) {
        $this->database->insert('aculta_user_nickname')
          ->fields(['uid' => $uid, 'slug' => $slug])
          ->execute();
      }
      foreach ($canonicalNicknames as $uid => $nickname) {
        $this->synchronizing[(int) $uid] = TRUE;
        try {
          foreach ($profilesByUid[$uid] as $profile) {
            if (trim((string) $profile->get('field_nickname')->value) === $nickname) {
              continue;
            }
            $profile->set('field_nickname', $nickname);
            $profile->save();
            $reconciledProfiles++;
          }
        }
        finally {
          unset($this->synchronizing[(int) $uid]);
        }
      }
      $transaction->commitOrRelease();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    return [
      'uids' => count($uids),
      'profiles' => $profileCount,
      'same_uid_conflicts' => $sameUidConflicts,
      'reconciled_profiles' => $reconciledProfiles,
    ];
  }

}
