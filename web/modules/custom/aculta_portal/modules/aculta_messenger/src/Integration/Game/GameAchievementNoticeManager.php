<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Game;

use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;

/**
 * Adapts BadgeAward records into the Messenger notice center. */
final class GameAchievementNoticeManager implements GameAchievementNoticeManagerInterface {

  public const TEMPLATE = 'aculta_msg_game_award';

  public const SOURCE_FIELD = 'field_aculta_game_award_uuid';

  public const READ_FIELD = 'field_aculta_game_read';

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BadgeService $badges,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * {@inheritdoc} */
  public function record(MessengerNoticeEvent $event): ?MessageInterface {
    if (!$this->isCanonicalEvent($event)) {
      return NULL;
    }
    $recipient = $this->entityTypeManager->getStorage('user')->loadUnchanged($event->recipientUid());
    $award = $this->awardByUuid($event->sourceEntityUuid());
    if (!$recipient instanceof UserInterface || !$recipient->isActive()
      || !$this->isAward($award)
      || (int) $award->id() !== (int) $event->sourceRevisionOrVersion()
      || (int) $award->get('uid')->target_id !== $event->recipientUid()
      || !$award->get('revogado_em')->isEmpty()
      || !isset($this->badges->definitions()[(string) $award->get('badge')->value])) {
      return NULL;
    }

    $lockId = 'aculta_messenger:game_award_notice:' . hash('sha256', $event->idempotencyKey());
    if (!$this->lock->acquire($lockId, 30.0)) {
      $this->lock->wait($lockId, 30);
      if (!$this->lock->acquire($lockId, 30.0)) {
        throw new \RuntimeException('Achievement notice is currently being recorded.');
      }
    }
    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $event->recipientUid())
        ->condition(self::SOURCE_FIELD, $event->sourceEntityUuid())
        ->range(0, 1)
        ->execute();
      if ($ids !== []) {
        $existing = $storage->loadUnchanged((int) reset($ids));
        return $existing instanceof MessageInterface ? $existing : NULL;
      }
      $message = $storage->create([
        'template' => self::TEMPLATE,
        'uid' => $event->recipientUid(),
        self::SOURCE_FIELD => $event->sourceEntityUuid(),
        self::READ_FIELD => 0,
        'created' => $event->occurredAt()->getTimestamp(),
      ]);
      $message->save();
      return $message instanceof MessageInterface ? $message : NULL;
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    $this->addPersonalNoticesPage($items, $account, $filters, 10, 0);
  }

  /**
   * {@inheritdoc} */
  public function addPersonalNoticesPage(array &$items, AccountInterface $account, array $filters, int $limit, int $offset): bool {
    if (!$account->isAuthenticated()
      || !in_array($filters['leitura'] ?? '', ['', 'read', 'unread'], TRUE)) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $page = EligibleNoticePage::collect(
      function (int $scanOffset, int $batchSize) use ($storage, $account, $filters): array {
        $query = $storage->getQuery()->accessCheck(FALSE)
          ->condition('template', self::TEMPLATE)
          ->condition('uid', (int) $account->id());
        NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
        return $query->sort('created', 'DESC')->sort('id', 'DESC')
          ->range($scanOffset, $batchSize)->execute();
      },
      static fn(array $ids): array => array_values($storage->loadMultiple($ids)),
      fn(mixed $message): ?array => $message instanceof MessageInterface
        ? $this->resolve($message, $account)
        : NULL,
      $limit,
      $offset,
    );
    foreach ($page['items'] as $entry) {
      $message = $entry['entity'];
      $notice = $entry['resolved'];
      $read = (bool) $message->get(self::READ_FIELD)->value;
      $url = $this->domainPurposeManager->routeUrl('account', 'aculta_user.atividade', ['aba' => 'conquistas']);
      if ($url === NULL) {
        continue;
      }
      $content = [
        '#type' => 'container',
        'title' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'notice-summary-title']),
          '#value' => $notice['title'],
        ],
        'state' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute([
            'data-portal-ui' => $read ? 'read-label' : 'read-label read-label-unread',
          ]),
          '#value' => $read ? $this->t('Lido') : $this->t('Não lido'),
        ],
      ];
      $items[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'notice-summary notice-summary-persistent',
          'data-aculta-notice-origin' => 'conquistas',
          'data-aculta-notice-read' => $read ? 'read' : 'unread',
          'data-aculta-notice-recency' => (string) $message->getCreatedTime(),
          'data-aculta-notice-message-id' => (string) $message->id(),
        ],
        'link' => PortalLinkElement::build($content, $url, ['data-portal-ui' => 'notice-summary-link']),
      ];
    }
    return $page['has_next'];
  }

  /**
   * {@inheritdoc} */
  public function unreadCount(int $uid): int {
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::READ_FIELD, 0)
      ->execute();
    $count = 0;
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface && $this->resolve($message, $account) !== NULL) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * {@inheritdoc} */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if ($message->bundle() !== self::TEMPLATE || !$account->isAuthenticated()
      || (int) $message->getOwnerId() !== (int) $account->id()
      || $this->resolve($message, $account) === NULL) {
      return FALSE;
    }
    $message->set(self::READ_FIELD, $read ? 1 : 0)->save();
    return TRUE;
  }

  /**
   * Revalidates ownership, grant status, definition and active recipient. */
  private function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if ($message->bundle() !== self::TEMPLATE || (int) $message->getOwnerId() !== (int) $account->id()) {
      return NULL;
    }
    $recipient = $this->entityTypeManager->getStorage('user')->loadUnchanged((int) $account->id());
    $awardUuid = (string) ($message->get(self::SOURCE_FIELD)->value ?? '');
    $award = $awardUuid === '' ? NULL : $this->awardByUuid($awardUuid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()
      || !$this->isAward($award)
      || (int) $award->get('uid')->target_id !== (int) $account->id()
      || !$award->get('revogado_em')->isEmpty()) {
      return NULL;
    }
    $definition = $this->badges->definitions()[(string) $award->get('badge')->value] ?? NULL;
    if (!is_array($definition) || !is_string($definition['label'] ?? NULL)) {
      return NULL;
    }
    return ['title' => (string) $this->t('Você conquistou: @label', ['@label' => $definition['label']])];
  }

  /**
   * Loads a grant from the immutable UUID source. */
  private function awardByUuid(string $uuid): ?ContentEntityInterface {
    $matches = $this->entityTypeManager->getStorage('aculta_badge_award')->loadByProperties(['uuid' => $uuid]);
    $award = reset($matches);
    if (!$this->isAward($award) || $award->id() === NULL) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage('aculta_badge_award')->loadUnchanged((int) $award->id());
  }

  /**
   * Checks a source entity loaded from the award storage. */
  private function isAward(mixed $entity): bool {
    return $entity instanceof ContentEntityInterface
      && $entity->getEntityTypeId() === 'aculta_badge_award';
  }

  /**
   * Validates the immutable producer key used by new events and migrations. */
  private function isCanonicalEvent(MessengerNoticeEvent $event): bool {
    return $event->sourceModule() === 'aculta_game'
      && $event->sourceEntityType() === 'aculta_badge_award'
      && $event->eventType() === 'game.achievement_awarded'
      && $event->purpose() === 'account'
      && $event->sourceRevisionOrVersion() !== NULL
      && ctype_digit($event->sourceRevisionOrVersion())
      && ($event->metadata()['notice_type'] ?? NULL) === 'achievement'
      && hash_equals(implode(':', [
        'game', 'achievement_awarded', $event->sourceEntityUuid(),
        $event->sourceRevisionOrVersion(), (string) $event->recipientUid(),
      ]), $event->idempotencyKey());
  }

}
