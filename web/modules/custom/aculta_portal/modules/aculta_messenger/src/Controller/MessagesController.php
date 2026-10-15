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
use Drupal\aculta_messenger\Service\MessengerCounts;
use Drupal\aculta_messenger\Service\UnreadThreadCounter;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\user\UserInterface;
use Drupal\site_alert\GetAlertsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/** Pages for the account communication center. */
final class MessagesController extends ControllerBase {

  private const PRIVATE_MESSAGE_TEMPLATE = 'aculta_msg_private_notification';

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly EntityTypeManagerInterface $entityTypes,
    private readonly AccountInterface $account,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly UnreadThreadCounter $unreadThreadCounter,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly GetAlertsInterface $siteAlerts,
    private readonly FlagServiceInterface $flagService,
    private readonly TranslationInterface $translation,
    private readonly FormBuilderInterface $forms,
    private readonly RendererInterface $renderer,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly RequestStack $requestStack,
    private readonly MessengerCounts $counts,
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
      $container->get('request_stack'),
      $container->get('aculta_messenger.counts'),
    );
  }

  /** Returns the inbox preview used by the existing drawer fetch. */
  public function preview(): Response {
    $build = $this->buildConversationList();
    // "Iniciar nova conversa" is the first action of the first window.
    // A button element: links and html_tag are rendered as strings in their pre-render, outside the theme
    // hooks that map data-portal-ui tokens; the button goes through the input theme, which does.
    $build = ['new_conversation' => [
      '#type' => 'button',
      '#value' => $this->translation->translate('Iniciar nova conversa'),
      '#attributes' => [
        'type' => 'button',
        'data-aculta-conversation-url' => Url::fromRoute('aculta_messenger.new_conversation')->toString(),
        'data-portal-ui' => 'new-conversation-link',
      ],
      '#weight' => -100,
    ]] + $build;
    // The native clear-history action remains a Drupal confirmation form.
    $build['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /** Returns the conversation list fragment for navigation inside the drawer. */
  public function drawerConversations(): Response {
    $build = $this->buildConversationList();
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /** Returns a member-authorized thread fragment for the open drawer. */
  public function drawerConversation(PrivateMessageThreadInterface $private_message_thread): Response {
    $thread = $private_message_thread;
    $access = $thread->access('view', $this->account, TRUE);
    if (!$access->isAllowed() || !$thread->isMember($this->account->id())) {
      throw new NotFoundHttpException();
    }

    // Rendering the thread is what marks it as read in Private Message's view builder. Cross-site
    // requests and prefetches (which can carry the session cookie) must not render it, so they get an
    // empty response and the conversation stays unread.
    if (!$this->isMemberInitiatedRequest()) {
      return new Response('', Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
    }
    $threadBuild = $this->entityTypes->getViewBuilder('private_message_thread')->view($thread, 'full');
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['aculta-messenger-drawer-conversation'],
        'data-aculta-conversation-view' => '',
        'data-aculta-thread-id' => (string) $thread->id(),
      ],
      'thread' => $threadBuild,
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    (new CacheableMetadata())->addCacheableDependency($thread)->addCacheableDependency($access)->applyTo($build);
    return $this->buildDrawerResponse($build, [
      'X-Aculta-Messages-Count' => (string) ($this->unreadThreadCounter->count() + count($this->friendshipManager->getIncomingRequests())),
    ]);
  }

  /** Builds the authorized conversation list for drawer AJAX navigation. */
  private function buildConversationList(): array {
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
      $access = $thread->access('view', $this->account, TRUE);
      $cacheability->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $cacheability->addCacheableDependency($thread);
      $isUnread = $this->unreadThreadCounter->isUnread($thread);
      $sender = $this->getConversationSender($thread);
      $senderName = $sender?->getDisplayName() ?: (string) $this->translation->translate('Membro');
      $clearAccess = $thread->access('clear_personal_history', $this->account, TRUE);
      $cacheability->addCacheableDependency($clearAccess);
      $clearHistoryUrl = NULL;
      if ($clearAccess->isAllowed()
        && $this->account->hasPermission('use private messaging system')
        && $this->account->hasPermission('access user profiles')) {
        $clearHistoryUrl = $this->domainPurposeManager->routeUrl(
          'account',
          'entity.private_message_thread.clear_personal_history_form',
          ['private_message_thread' => $thread->id()],
        );
      }
      if ($sender !== NULL) {
        $cacheability->addCacheableDependency($sender);
      }
      $drawerConversationUrl = Url::fromRoute('aculta_messenger.drawer_conversation', [
        'private_message_thread' => $thread->id(),
      ]);
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
      $openLink = [
        '#type' => 'link',
        '#title' => $linkTitle,
        // Conversations are read in the bar drawer only; without JavaScript the link opens the same drawer route.
        '#url' => $drawerConversationUrl,
        '#attributes' => [
          'class' => ['aculta-messenger-sender__link'],
          'data-aculta-conversation-url' => $drawerConversationUrl->toString(),
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
          $avatarAccess = $sender->access('view', $this->account, TRUE);
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
    return $build;
  }

  /** Selects the latest incoming author, or the conversation partner. */
  private function getConversationSender(PrivateMessageThreadInterface $thread): ?UserInterface {
    $messages = array_values($thread->getMessages());
    $latestMessage = $messages === [] ? NULL : $messages[array_key_last($messages)];
    $sender = $latestMessage?->getOwner();
    if ($sender !== NULL && (int) $sender->id() !== (int) $this->account->id()) {
      return $sender;
    }

    foreach ($thread->getMembers() as $member) {
      if ((int) $member->id() !== (int) $this->account->id()) {
        return $member;
      }
    }

    return $sender;
  }

  /** Displays currently active native Site Alerts in the same-origin modal. */
  public function alertsPreview(): Response {
    $storage = $this->entityTypes->getStorage('site_alert');
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['active_site_alerts'])
      ->addCacheTags($this->entityTypes->getDefinition('site_alert')->getListCacheTags())
      ->setCacheMaxAge($storage->getCacheMaxAge());
    if ($this->account->isAuthenticated()) {
      $cacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
    }
    $items = [];

    // Same criteria as the header bar, from the shared service.
    $activeAlertCount = $this->counts->visibleActiveAlertCount($this->account, $cacheability);
    $dismissalFlag = $this->flagService->getFlagById('aculta_site_alert_dismissed');
    foreach ($this->siteAlerts->getActiveAlerts() as $alert) {
      $access = $alert->access('view', $this->account, TRUE);
      $cacheability->addCacheableDependency($alert)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      if ($dismissalFlag instanceof FlagInterface && $this->flagService->getFlagging($dismissalFlag, $alert, $this->account) !== NULL) {
        continue;
      }
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
      'X-Aculta-Alerts-Count' => (string) ($activeAlertCount + $this->counts->unreadPersistentNoticeCount($this->account)),
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

  /** Builds authorized notice render arrays and native per-member Flag actions. */
  private function buildPersistentNoticeItems(int $limit, CacheableMetadata $cacheability): array {
    $ids = $this->entityTypes->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'aculta_notice')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, $limit)
      ->execute();
    $nodes = $this->entityTypes->getStorage('node')->loadMultiple($ids);
    $flag = $this->flagService->getFlagById('aculta_notice_read');
    $items = [];
    foreach ($nodes as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $access = $node->access('view', $this->account, TRUE);
      $cacheability->addCacheableDependency($node)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      $read = FALSE;
      if ($flag instanceof FlagInterface) {
        $flagging = $this->flagService->getFlagging($flag, $node, $this->account);
        $read = $flagging !== NULL;
        $cacheability->addCacheTags($this->entityTypes->getDefinition('flagging')->getListCacheTags());
      }
      $item = $this->entityTypes->getViewBuilder('node')->view($node, 'full');
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
    if (!$flag instanceof FlagInterface || !$this->account->isAuthenticated()) {
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

  /** A same-site, non-prefetch request, i.e. one the member opened on this site. */
  private function isMemberInitiatedRequest(): bool {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return FALSE;
    }
    if ($request->headers->get('Sec-Fetch-Site') === 'cross-site') {
      return FALSE;
    }
    $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose') ?? ''));
    return !str_contains($purpose, 'prefetch') && !str_contains($purpose, 'prerender');
  }

}
