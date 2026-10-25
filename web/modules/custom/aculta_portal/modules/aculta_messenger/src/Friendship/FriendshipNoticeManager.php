<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Friendship;

use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;

/**
 * Adapts friendship Message records into the shared site notice center.
 */
final class FriendshipNoticeManager implements FriendshipNoticeManagerInterface {

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_friendship';

  private const EVENT_FIELD = 'field_aculta_friendship_event';

  private const READ_FIELD = FriendshipNoticeSubscriber::READ_FIELD;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    $this->addPersonalNoticesPage($items, $account, $filters, 10, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function addPersonalNoticesPage(array &$items, AccountInterface $account, array $filters, int $limit, int $offset): bool {
    if (!$account->isAuthenticated()) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $page = EligibleNoticePage::collect(
      function (int $scanOffset, int $batchSize) use ($storage, $account, $filters): array {
        $query = $storage->getQuery()->accessCheck(FALSE)
          ->condition('template', self::TEMPLATE)
          ->condition('uid', (int) $account->id())
          ->condition(self::EVENT_FIELD, ['requested', 'accepted'], 'IN');
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
      $unread = !(bool) $message->get(self::READ_FIELD)->value;
      $heading = [
        '#type' => 'container',
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $notice['title']],
        'excerpt' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $notice['summary']],
        'state' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => $unread ? 'read-label read-label-unread' : 'read-label']),
          '#value' => $unread ? $this->t('Não lido') : $this->t('Lido'),
        ],
      ];
      $items[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'notice-summary notice-summary-persistent',
          'data-aculta-notice-origin' => 'amizade',
          'data-aculta-notice-recency' => (string) $message->getCreatedTime(),
          'data-aculta-notice-read' => $unread ? 'unread' : 'read',
          'data-aculta-notice-message-id' => (string) $message->id(),
        ],
        'link' => PortalLinkElement::build(
          $heading,
          Url::fromRoute('aculta_messenger.avisos', ['aba' => 'mensagens']),
          ['data-portal-ui' => 'notice-summary-link'],
        ),
      ];
    }

    return $page['has_next'];
  }

  /**
   * {@inheritdoc}
   */
  public function unreadCount(int $uid): int {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return 0;
    }
    return (int) $this->entityTypeManager->getStorage('message')->getQuery()->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::EVENT_FIELD, ['requested', 'accepted'], 'IN')
      ->condition(self::READ_FIELD, 0)
      ->count()
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if (!$this->resolve($message, $account)) {
      return FALSE;
    }
    if ((bool) $message->get(self::READ_FIELD)->value !== $read) {
      $message->set(self::READ_FIELD, $read ? 1 : 0)->save();
    }
    return TRUE;
  }

  /**
   * Resolves the privacy-minimal presentation for an owned notice.
   *
   * @return array{title: \Drupal\Core\StringTranslation\TranslatableMarkup, summary: \Drupal\Core\StringTranslation\TranslatableMarkup}|null
   *   Safe presentation, or NULL for another owner or an unsupported event.
   */
  private function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if (!$account->isAuthenticated() || (int) $message->getOwnerId() !== (int) $account->id()
      || $message->bundle() !== self::TEMPLATE) {
      return NULL;
    }
    return match ((string) $message->get(self::EVENT_FIELD)->value) {
      'requested' => [
        'title' => $this->t('Novo pedido de amizade'),
        'summary' => $this->t('Alguém enviou um pedido de amizade. Acesse Mensagens para responder.'),
      ],
      'accepted' => [
        'title' => $this->t('Pedido de amizade aceito'),
        'summary' => $this->t('Seu pedido de amizade foi aceito. Acesse Mensagens para continuar.'),
      ],
      default => NULL,
    };
  }

}
