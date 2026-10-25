<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Publishes saved Revista facts to the shared ACULTA Messenger API. */
final class MagazineNoticeEventPublisher {

  private const FOLLOW_FLAG = 'seguir_publicacao';

  public function __construct(
    private readonly FlagServiceInterface $flagService,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  /**
 * Publishes a first publication after its node has been saved. */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'revista'
      || !$entity->isPublished() || !$this->isPublishedModerationState($entity)
      || !$entity->isDefaultRevision() || $this->isSynchronizing($entity)) {
      return;
    }
    $this->publishPublication($entity, 'magazine.publication_published');
  }

  /**
 * Publishes a publication transition or a new published revision. */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'revista'
      || !$entity->isPublished() || !$this->isPublishedModerationState($entity)
      || !$entity->isDefaultRevision() || $this->isSynchronizing($entity)) {
      return;
    }
    $original = $entity->getOriginal();
    if (!$original instanceof NodeInterface || $original->bundle() !== 'revista') {
      return;
    }
    $wasPublished = $original->isPublished() && $this->isPublishedModerationState($original);
    if (!$wasPublished) {
      $this->publishPublication($entity, 'magazine.publication_published');
      return;
    }
    if ((int) $original->getRevisionId() !== (int) $entity->getRevisionId()) {
      $this->publishPublication($entity, 'magazine.publication_updated');
    }
  }

  /**
   * Publishes to active authors, coauthors and followers with current access. */
  private function publishPublication(NodeInterface $node, string $eventType): void {
    $config = $this->configFactory->get('aculta_messenger.settings');
    $setting = $eventType === 'magazine.publication_published' ? 'published' : 'updated';
    if ($config->get('magazine_events.' . $setting) !== TRUE) {
      return;
    }
    $uuid = $node->uuid();
    $revisionId = (int) $node->getRevisionId();
    if ($uuid === '' || $revisionId < 1) {
      return;
    }

    $authorUid = method_exists($node, 'getRevisionUserId')
      ? (int) $node->getRevisionUserId()
      : (int) $node->getOwnerId();
    if ($authorUid < 1) {
      $authorUid = (int) $node->getOwnerId();
    }
    $recipients = [];
    $ownerUid = (int) $node->getOwnerId();
    if ($ownerUid > 0) {
      $recipients[$ownerUid] = TRUE;
    }
    if ($node->hasField('co_authors')) {
      foreach ($node->get('co_authors')->getValue() as $item) {
        $uid = (int) ($item['target_id'] ?? 0);
        if ($uid > 0) {
          $recipients[$uid] = TRUE;
        }
      }
    }
    $flag = $this->flagService->getFlagById(self::FOLLOW_FLAG);
    if ($flag !== NULL) {
      foreach ($this->flagService->getEntityFlaggings($flag, $node) as $flagging) {
        $uid = (int) $flagging->getOwnerId();
        if ($uid > 0) {
          $recipients[$uid] = TRUE;
        }
      }
    }

    foreach (array_keys($recipients) as $uid) {
      if ($uid === $authorUid || !$this->recipientCanView($node, $uid)) {
        continue;
      }
      $this->publisher->publish(new MessengerNoticeEvent(
        $eventType,
        'aculta_magazine',
        'node',
        $uuid,
        (string) $revisionId,
        $uid,
        'magazine',
        new \DateTimeImmutable('@' . $this->time->getRequestTime()),
        $eventType . ':' . $uuid . ':' . $revisionId . ':' . $uid,
        ['notice_type' => $setting, 'mail_policy' => 'user_preference'],
      ));
    }
  }

  /**
   * Revalidates recipient account and node access within Magazine purpose. */
  private function recipientCanView(NodeInterface $node, int $uid): bool {
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return FALSE;
    }
    $allowed = $this->domainPurposes->runInPurpose('magazine', static fn(): bool =>
      $node->access('view', $account, TRUE)->isAllowed()
    );
    return $allowed === TRUE;
  }

  /**
   * Returns whether the saved revision is in the public workflow state. */
  private function isPublishedModerationState(NodeInterface $node): bool {
    return !$node->hasField('moderation_state')
      || $node->get('moderation_state')->value === 'published';
  }

  /**
   * Excludes synchronization/import operations from user-facing notices. */
  private function isSynchronizing(NodeInterface $node): bool {
    return $node instanceof SynchronizableInterface && $node->isSyncing();
  }

}
