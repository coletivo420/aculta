<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\message\MessageInterface;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserDataInterface;

/**
 * Supplies privacy-minimal weekly summaries of pending private messages. */
final class PrivateMessageDigestProvider implements AvisoDigestProviderIdentityInterface, AvisoIndependentDigestProviderInterface {

  use StringTranslationTrait;

  private const TEMPLATE = 'aculta_msg_private_notification';

  private const SOURCE_FIELD = 'field_aculta_messenger_source_pm';

  private const DIGEST_FIELD = 'field_aculta_pm_digest_sent';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UserDataInterface $userData,
    private readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'private_message';
  }

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    $ids = $this->pendingQuery()->execute();
    $recipients = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface) {
        continue;
      }
      $uid = (int) $message->getOwnerId();
      $mode = $this->preferences->emailMode($uid, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES);
      if ($mode === AvisoPreferencias::EMAIL_DIGEST && $this->digestIsDue($uid)) {
        $recipients[$uid] = TRUE;
      }
      elseif ($mode !== AvisoPreferencias::EMAIL_DIGEST) {
        $this->discardDigest($uid);
      }
    }
    return array_keys($recipients);
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    if ($this->preferences->emailMode($uid, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES) !== AvisoPreferencias::EMAIL_DIGEST
      || !$this->digestIsDue($uid)) {
      $this->discardDigest($uid);
      return [];
    }
    $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()) {
      $this->discardDigest($uid);
      return [];
    }
    if (!$recipient->hasPermission('use private messaging system')
      || !$recipient->hasPermission('access user profiles')) {
      $this->discardDigest($uid);
      return [];
    }
    $groups = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($this->pendingQuery($uid)->execute()) as $notice) {
      if (!$notice instanceof MessageInterface || (int) $notice->getOwnerId() !== $uid) {
        continue;
      }
      $pmId = (int) ($notice->get(self::SOURCE_FIELD)->target_id ?? 0);
      $privateMessage = $this->entityTypeManager->getStorage('private_message')->load($pmId);
      if (!$privateMessage instanceof PrivateMessageInterface) {
        $this->markRecordsDone([$notice]);
        continue;
      }
      $senderId = (int) $privateMessage->getOwnerId();
      $thread = $this->privateMessageService->getThreadFromMessage($privateMessage);
      if (!$thread instanceof PrivateMessageThreadInterface
        || !$thread->access('view', $recipient, TRUE)->isAllowed()
        || !in_array($uid, array_map('intval', $thread->getMembersId()), TRUE)
        || $this->isBlockedPair($senderId, $uid)
        || (is_numeric($thread->getLastDeleteTimestamp($recipient))
          && (int) $thread->getLastDeleteTimestamp($recipient) >= $privateMessage->getCreatedTime())) {
        $this->markRecordsDone([$notice]);
        continue;
      }
      $sender = $this->entityTypeManager->getStorage('user')->load($senderId);
      if (!$sender instanceof UserInterface || !$sender->isActive()) {
        $this->markRecordsDone([$notice]);
        continue;
      }
      $groups[$senderId]['name'] = $sender->getDisplayName();
      $groups[$senderId]['delivery_ids'][] = (int) $notice->id();
    }
    ksort($groups, SORT_NUMERIC);
    $rows = [];
    foreach ($groups as $group) {
      $deliveryIds = array_values(array_unique($group['delivery_ids']));
      sort($deliveryIds, SORT_NUMERIC);
      $count = count($deliveryIds);
      $title = $count === 1
        ? $this->t('Você tem uma nova mensagem de @name.', ['@name' => $group['name']])
        : $this->t('Você tem @count novas mensagens de @name.', [
          '@count' => $count,
          '@name' => $group['name'],
        ]);
      $rows[] = [
        'tipo' => AvisoPreferencias::EMAIL_PRIVATE_MESSAGES,
        'titulo' => (string) $title,
        'quantidade' => $count,
        'delivery_id' => reset($deliveryIds),
        'delivery_ids' => $deliveryIds,
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $notices = $this->entityTypeManager->getStorage('message')->loadMultiple($deliveryIds);
    $this->markRecordsDone($notices, $uid, 'sent');
    if ($deliveryIds !== []) {
      $this->userData->set('aculta_messenger', $uid, 'private_message_digest_last_sent', $this->time->getRequestTime());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $ids = $this->pendingQuery($uid)->execute();
    $notices = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    $this->markRecordsDone($notices, $uid, 'cancelled');
  }

  /**
   * Marks selected records complete without advancing the seven-day window.
   */
  private function markRecordsDone(iterable $notices, ?int $uid = NULL, string $status = 'cancelled'): void {
    foreach ($notices as $notice) {
      if ($notice instanceof MessageInterface && $notice->bundle() === self::TEMPLATE
        && ($uid === NULL || (int) $notice->getOwnerId() === $uid)) {
        $notice->set(self::DIGEST_FIELD, 1);
        $notice->set(self::DELIVERY_FIELD, $status);
        $notice->save();
      }
    }
  }

  /**
   * Builds a query for digest records that have not been acknowledged.
   */
  private function pendingQuery(?int $uid = NULL): QueryInterface {
    $query = $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition(self::DIGEST_FIELD, 0);
    if ($uid !== NULL) {
      $query->condition('uid', $uid);
    }
    return $query;
  }

  /**
   * Checks the global start date and seven-day recipient interval.
   */
  private function digestIsDue(int $uid): bool {
    $anchor = (string) $this->configFactory->get('aculta_messenger.settings')->get('private_message_digest_anchor');
    $timezone = (string) ($this->configFactory->get('system.date')?->get('timezone.default') ?: 'UTC');
    try {
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $anchor, new \DateTimeZone($timezone));
    }
    catch (\Throwable) {
      return FALSE;
    }
    // An invalid or malformed administrative date must not silently turn the
    // configured start into the Unix epoch and enable weekly delivery early.
    if ($date === FALSE || $date->format('Y-m-d') !== $anchor) {
      return FALSE;
    }
    $anchorTime = $date->getTimestamp();
    $lastSent = (int) $this->userData->get('aculta_messenger', $uid, 'private_message_digest_last_sent');
    return $this->time->getRequestTime() >= max($anchorTime, $lastSent + 7 * 86400);
  }

  /**
   * Checks both directions of a private-message block.
   */
  private function isBlockedPair(int $senderId, int $recipientId): bool {
    $storage = $this->entityTypeManager->getStorage('private_message_ban');
    foreach ([[$senderId, $recipientId], [$recipientId, $senderId]] as [$owner, $target]) {
      $blocked = $storage->getQuery()->accessCheck(FALSE)->condition('owner', $owner)
        ->condition('target', $target)->range(0, 1)->execute();
      if ($blocked !== []) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
