<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\aculta_messenger\Form\FriendRequestsForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\message\MessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_user\Friendship\FriendshipManagerInterface;
use Drupal\user\UserInterface;
use Drupal\site_alert\GetAlertsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

/** Pages for the account communication center. */
final class MessagesController extends ControllerBase {

  private const PRIVATE_MESSAGE_TEMPLATE = 'aculta_messenger_private_message_notification';

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly UnreadThreadCounter $unreadThreadCounter,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly GetAlertsInterface $siteAlerts,
    private readonly FlagServiceInterface $flagService,
    private readonly TranslationInterface $translation,
    private readonly FormBuilderInterface $formBuilder,
    private readonly RendererInterface $renderer,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('private_message.service'),
      $container->get('aculta_messenger.unread_thread_counter'),
      $container->get('aculta_user.friendship_manager'),
      $container->get('site_alert.get_alerts'),
      $container->get('flag'),
      $container->get('string_translation'),
      $container->get('form_builder'),
      $container->get('renderer'),
      $container->get('date.formatter'),
    );
  }

  /** Shows the communication entry points and native unread-thread count. */
  public function overview(): array {
    $count = $this->unreadThreadCounter->count();
    $summary = $count === 1
      ? $this->translation->translate('Você tem 1 conversa não lida.')
      : $this->translation->translate('Conversas não lidas: @count.', ['@count' => $count]);

    return [
      'summary' => ['#plain_text' => (string) $summary],
      'links' => [
        '#theme' => 'item_list',
        '#items' => [
          ['#type' => 'link', '#title' => $this->translation->translate('Conversas'), '#url' => Url::fromRoute('aculta_messenger.conversations')],
          ['#type' => 'link', '#title' => $this->translation->translate('Avisos'), '#url' => Url::fromRoute('aculta_messenger.notices')],
          ['#type' => 'link', '#title' => $this->translation->translate('Notificações'), '#url' => Url::fromRoute('aculta_messenger.notifications')],
        ],
      ],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
  }

  /** Sends members to the native Private Message inbox. */
  public function conversations(): TrustedRedirectResponse {
    $url = $this->domainPurposeManager->routeUrl('account', 'private_message.private_message_page');
    if ($url === NULL) {
      throw new NotFoundHttpException();
    }

    return new TrustedRedirectResponse($url->toString());
  }

  /**
   * Builds a compact view of the current member's native inbox for the bar modal.
   *
   * The endpoint is same-origin on every public purpose. It does not create a
   * purpose-specific mailbox; conversation links are canonicalized to ACCOUNT.
   */
  public function preview(): Response {
    $threads = $this->privateMessageService->getThreadsForUser(50)['threads'];
    $items = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->setCacheMaxAge(0);

    // Private Message already returns threads newest first. Keep that order
    // explicit here because each sender is positioned by their latest
    // conversation.
    usort($threads, static fn ($a, $b): int => (int) $b->getUpdatedTime() <=> (int) $a->getUpdatedTime());
    $senderBoxes = [];

    foreach ($threads as $thread) {
      if (!$thread instanceof PrivateMessageThreadInterface) {
        continue;
      }
      $access = $thread->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $cacheability->addCacheableDependency($thread);
      $isUnread = $this->unreadThreadCounter->isUnread($thread);
      $sender = $this->getConversationSender($thread);
      $senderName = $sender?->getDisplayName() ?: (string) $this->translation->translate('Membro');
      $clearAccess = $thread->access('clear_personal_history', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($clearAccess);
      $clearHistoryUrl = NULL;
      if ($clearAccess->isAllowed()
        && $this->currentUser->hasPermission('use private messaging system')
        && $this->currentUser->hasPermission('access user profiles')) {
        $clearHistoryUrl = $this->domainPurposeManager->routeUrl(
          'account',
          'entity.private_message_thread.clear_personal_history_form',
          ['private_message_thread' => $thread->id()],
        );
      }
      if ($sender !== NULL) {
        $cacheability->addCacheableDependency($sender);
      }
      $conversationUrl = $this->domainPurposeManager->routeUrl(
        'account',
        'aculta_messenger.conversation_dialog',
        ['private_message_thread' => $thread->id()],
      );
      $lastMessageTimestamp = (int) $thread->getNewestMessageCreationTimestamp();
      $sentDate = $this->dateFormatter->format(
        $lastMessageTimestamp > 0 ? $lastMessageTimestamp : (int) $thread->getUpdatedTime(),
        'custom',
        'd/m/Y H:i',
      );
      $linkTitle = Markup::create(
        '<span class="aculta-messenger-sender__date">' . Html::escape($sentDate) . '</span>'
        . '<span class="aculta-messenger-sender__name">' . Html::escape($senderName) . '</span>'
      );
      $openLink = $conversationUrl === NULL ? NULL : [
        '#type' => 'link',
        '#title' => $linkTitle,
        '#url' => $conversationUrl,
        '#attributes' => [
          'class' => ['use-ajax', 'aculta-messenger-sender__link'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => json_encode([
            'width' => 760,
            'classes' => ['ui-dialog' => 'aculta-messenger-conversation-dialog'],
          ], JSON_THROW_ON_ERROR),
          'data-aculta-thread-id' => (string) $thread->id(),
          'data-aculta-thread-unread' => $isUnread ? 'true' : 'false',
          'data-aculta-sent-date' => $sentDate,
          'aria-label' => (string) $this->translation->translate('Abrir conversa com @name. Última mensagem em @date.', [
            '@name' => $senderName,
            '@date' => $sentDate,
          ]),
        ],
      ];
      $senderId = $sender?->id() ?? 'thread-' . $thread->id();
      $senderKey = (string) $senderId;
      if (!isset($senderBoxes[$senderKey])) {
        $avatar = NULL;
        if ($sender instanceof UserInterface && $sender->hasField('user_picture') && !$sender->get('user_picture')->isEmpty()) {
          $avatarAccess = $sender->access('view', $this->currentUser, TRUE);
          $cacheability->addCacheableDependency($avatarAccess);
          if ($avatarAccess->isAllowed()) {
            $avatar = $sender->get('user_picture')->view('default');
          }
        }
        $actions = [];
        if ($clearHistoryUrl !== NULL) {
          $actions = [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-messenger-conversation__actions']],
          'trigger' => [
            '#type' => 'button',
            '#value' => '⋮',
            '#attributes' => [
              'class' => ['aculta-messenger-conversation__menu-trigger'],
              'aria-label' => (string) $this->translation->translate('Ações da conversa com @name', ['@name' => $senderName]),
              'aria-haspopup' => 'menu',
              'aria-expanded' => 'false',
              'type' => 'button',
            ],
          ],
          'menu' => [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['aculta-messenger-conversation__menu'],
              'role' => 'menu',
              'hidden' => TRUE,
            ],
            'clear_history' => [
              '#type' => 'link',
              '#title' => $this->translation->translate('Excluir meu histórico desta conversa'),
              '#url' => $clearHistoryUrl,
              '#attributes' => [
                'class' => ['use-ajax'],
                'role' => 'menuitem',
                'data-dialog-type' => 'modal',
                'data-dialog-options' => json_encode(['width' => 520], JSON_THROW_ON_ERROR),
              ],
            ],
          ],
          ];
        }
        $senderBoxes[$senderKey] = [
          'updated' => (int) $thread->getUpdatedTime(),
          'thread_id' => (string) $thread->id(),
          'name' => $senderName,
          'sender' => $sender,
          'avatar' => $avatar,
          'open' => $openLink,
          'unread' => $isUnread,
          'actions' => $actions,
        ];
      }
    }

    // The groups were first encountered at their newest thread; sort anyway
    // so this remains true if the upstream mapper changes its order later.
    uasort($senderBoxes, static fn (array $a, array $b): int => $b['updated'] <=> $a['updated']);
    foreach ($senderBoxes as $senderBox) {
      $box = [
        '#type' => 'container',
        '#attributes' => [
          'class' => array_values(array_filter([
            'aculta-messenger-sender',
            'aculta-messenger-conversation',
            $senderBox['unread'] ? 'is-unread' : NULL,
          ])),
          'data-aculta-thread-id' => $senderBox['thread_id'],
          'data-aculta-thread-unread' => $senderBox['unread'] ? 'true' : 'false',
          'tabindex' => '0',
        ],
        'avatar' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-messenger-sender__avatar'], 'aria-hidden' => 'true'],
          'image' => $senderBox['avatar'] ?? ['#plain_text' => mb_substr($senderBox['name'], 0, 1)],
        ],
        'name' => $senderBox['open'] ?? ['#plain_text' => $senderBox['name']],
        'actions' => $senderBox['actions'],
      ];
      if ($senderBox['sender'] instanceof UserInterface) {
        $cacheability->addCacheableDependency($senderBox['sender']);
        $box['avatar']['image']['#cache']['contexts'][] = 'user';
      }
      $items[] = $box;
    }

    if ($items === []) {
      $items[] = ['#plain_text' => (string) $this->translation->translate('Você ainda não tem conversas.')];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'messenger-preview'],
      'threads' => ['#theme' => 'item_list', '#items' => $items],
      '#cache' => ['max-age' => 0],
    ];
    $cacheability->applyTo($build);
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /** Renders the native thread and reply form for the account message modal. */
  public function conversationDialog(PrivateMessageThreadInterface $private_message_thread): array {
    $thread = $private_message_thread;
    $build = $this->entityTypeManager->getViewBuilder('private_message_thread')->view($thread, 'full');

    // Private Message normally adds its native reply form from the entity view
    // display. Keep that form when configured; provide the same native Form API
    // form if a site's display configuration omitted the component.
    if (!isset($build['private_message_form'])) {
      $hasActiveRecipient = FALSE;
      foreach ($thread->getMembers() as $member) {
        if ($member->isActive() && (int) $member->id() !== (int) $this->currentUser->id()) {
          $hasActiveRecipient = TRUE;
          break;
        }
      }

      if ($hasActiveRecipient) {
        $message = $this->entityTypeManager->getStorage('private_message')->create([]);
        $formObject = $this->entityTypeManager->getFormObject('private_message', 'add')
          ->setEntity($message);
        $build['private_message_form'] = $this->formBuilder->getForm($formObject, $thread);
      }
      else {
        $build['no_active_users'] = ['#theme' => 'private_message_no_active_users_notice'];
      }
    }

    return $build;
  }

  /** Selects the latest incoming author, or the conversation partner. */
  private function getConversationSender(PrivateMessageThreadInterface $thread): ?UserInterface {
    $messages = array_values($thread->getMessages());
    $latestMessage = $messages === [] ? NULL : $messages[array_key_last($messages)];
    $sender = $latestMessage?->getOwner();
    if ($sender !== NULL && (int) $sender->id() !== (int) $this->currentUser->id()) {
      return $sender;
    }

    foreach ($thread->getMembers() as $member) {
      if ((int) $member->id() !== (int) $this->currentUser->id()) {
        return $member;
      }
    }

    return $sender;
  }

  /** Displays currently active native Site Alerts in the same-origin modal. */
  public function alertsPreview(): Response {
    $storage = $this->entityTypeManager->getStorage('site_alert');
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['active_site_alerts'])
      ->addCacheTags($this->entityTypeManager->getDefinition('site_alert')->getListCacheTags())
      ->setCacheMaxAge($storage->getCacheMaxAge());
    if ($this->currentUser->isAuthenticated()) {
      $cacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
    }
    $items = [];

    $activeAlertCount = 0;
    $dismissalFlag = $this->flagService->getFlagById('aculta_site_alert_dismissed');
    foreach ($this->siteAlerts->getActiveAlerts() as $alert) {
      $access = $alert->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($alert)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      if ($dismissalFlag instanceof FlagInterface && $this->flagService->getFlagging($dismissalFlag, $alert, $this->currentUser) !== NULL) {
        continue;
      }
      $activeAlertCount++;
      // Match the public render contract of SiteAlertBlock; formatted text and
      // severity remain owned by the contrib entity and its theme hook.
      $items[] = [
        'content' => [
          '#theme' => 'site_alert',
          '#alert' => [
            'severity' => $alert->getSeverity(),
            'label' => $alert->getLabel(),
            'message' => ['#type' => 'markup', '#markup' => $alert->getMessage()],
          ],
        ],
        'dismiss' => $this->buildDismissalLink($dismissalFlag, $alert),
      ];
    }

    foreach ($this->buildPersistentNoticeItems(50, $cacheability) as $item) {
      $items[] = $item;
    }

    if ($items === []) {
      $items[] = ['#plain_text' => (string) $this->translation->translate('Não há avisos ativos neste momento.')];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'messenger-preview'],
      'alerts' => ['#theme' => 'item_list', '#items' => $items],
    ];
    $cacheability->applyTo($build);
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Alerts-Count' => (string) ($activeAlertCount + $this->countUnreadPersistentNotices()),
    ]);
  }

  /** Renders a same-origin, uncached HTML fragment for the Bootstrap drawers. */
  private function buildDrawerResponse(array $build, array $headers = []): Response {
    $content = $this->renderer->renderInIsolation($build);
    return new Response((string) $content, Response::HTTP_OK, $headers + [
      'Content-Type' => 'text/html; charset=UTF-8',
      'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
      'X-Content-Type-Options' => 'nosniff',
    ]);
  }

  /** Counts every visible unread persistent notice, beyond the preview limit. */
  private function countUnreadPersistentNotices(): int {
    $flag = $this->flagService->getFlagById('aculta_notice_read');
    if (!$flag instanceof FlagInterface) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1)
      ->execute();
    $count = 0;
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($ids) as $node) {
      if ($node instanceof NodeInterface
        && $node->access('view', $this->currentUser)
        && $this->flagService->getFlagging($flag, $node, $this->currentUser) === NULL) {
        $count++;
      }
    }
    return $count;
  }

  /** Lists published persistent notices visible to this member, with Flag state. */
  public function notices(): array {
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->addCacheTags($this->entityTypeManager->getDefinition('node')->getListCacheTags())
      ->setCacheMaxAge(0);
    $items = $this->buildPersistentNoticeItems(50, $cacheability);
    if ($items === []) {
      $items[] = ['#plain_text' => (string) $this->translation->translate('Não há avisos institucionais disponíveis para sua conta.')];
    }
    $build = ['#theme' => 'item_list', '#items' => $items];
    $cacheability->applyTo($build);
    return $build;
  }

  /** Builds authorized notice render arrays and native per-member Flag actions. */
  private function buildPersistentNoticeItems(int $limit, CacheableMetadata $cacheability): array {
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($ids);
    $flag = $this->flagService->getFlagById('aculta_notice_read');
    $items = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $access = $node->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($node)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $read = FALSE;
      if ($flag instanceof FlagInterface) {
        $flagging = $this->flagService->getFlagging($flag, $node, $this->currentUser);
        $read = $flagging !== NULL;
        $cacheability->addCacheTags($this->entityTypeManager->getDefinition('flagging')->getListCacheTags());
      }
      $item = $this->entityTypeManager->getViewBuilder('node')->view($node, 'full');
      if ($flag instanceof FlagInterface) {
        $item['aculta_notice_read_action'] = $this->buildDismissalLink($flag, $node);
      }
      $item['aculta_notice_read_state'] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => $read ? 'notice-read-status' : 'notice-unread-status',
          'aria-label' => $read
            ? (string) $this->translation->translate('Lido')
            : (string) $this->translation->translate('Não lido'),
        ],
        'label' => ['#plain_text' => $read
          ? (string) $this->translation->translate('Lido')
          : (string) $this->translation->translate('Não lido')],
      ];
      $items[] = $item;
    }
    return $items;
  }

  /** Builds the native per-user flag link used to dismiss a notice. */
  private function buildDismissalLink(?FlagInterface $flag, EntityInterface $entity): array {
    if (!$flag instanceof FlagInterface || !$this->currentUser->isAuthenticated()) {
      return [];
    }
    $link = $flag->getLinkTypePlugin()->getAsFlagLink($flag, $entity, 'default');
    $link['#title'] = ['#markup' => '×'];
    $link['#attributes']['class'][] = 'aculta-alert-dismiss';
    $link['#attributes']['data-aculta-alert-dismiss'] = 'true';
    $link['#attributes']['aria-label'] = (string) $this->translation->translate('Dispensar aviso');
    $link['#attributes']['title'] = (string) $this->translation->translate('Dispensar aviso');
    return $link;
  }

  /** Lists only delivered private-message notices owned by the current user. */
  public function notifications(): array {
    $uid = (int) $this->currentUser->id();
    if ($uid < 1) {
      throw new NotFoundHttpException();
    }

    // Message has no owner-scoped access handler. This query deliberately
    // disables generic access checks and constrains the result to the session UID.
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::PRIVATE_MESSAGE_TEMPLATE)
      ->condition('uid', $uid)
      ->condition('field_aculta_messenger_delivery_status', 'sent')
      ->sort('created', 'DESC')
      ->range(0, 25)
      ->execute();
    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    $items = [
      'friend_requests' => $this->formBuilder->getForm(FriendRequestsForm::class),
      'messages' => [],
    ];
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->addCacheTags($this->entityTypeManager->getDefinition('message')->getListCacheTags());
    foreach ($messages as $message) {
      if (!$message instanceof MessageInterface || (int) $message->getOwnerId() !== $uid) {
        continue;
      }
      $items['messages'][] = $this->entityTypeManager->getViewBuilder('message')->view($message, 'mail_body', 'pt-br');
      $cacheability->addCacheableDependency($message);
    }

    if ($items['messages'] === []) {
      $items['messages'][] = ['#plain_text' => (string) $this->translation->translate('Você ainda não recebeu notificações de mensagens.')];
    }

    $build = [
      'friend_requests' => $items['friend_requests'],
      'messages' => ['#theme' => 'item_list', '#items' => $items['messages']],
      '#cache' => ['max-age' => 0],
    ];
    $cacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
    $cacheability->applyTo($build);
    return $build;
  }

}
