<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_messenger\Form\FriendRequestsForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\site_alert\GetAlertsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Pages for the account communication center. */
final class MessagesController extends ControllerBase {

  private const PRIVATE_MESSAGE_TEMPLATE = 'aculta_messenger_private_message_notification';

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly GetAlertsInterface $siteAlerts,
    private readonly FlagServiceInterface $flagService,
    private readonly TranslationInterface $translation,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('private_message.service'),
      $container->get('site_alert.get_alerts'),
      $container->get('flag'),
      $container->get('string_translation'),
      $container->get('form_builder'),
    );
  }

  /** Shows the communication entry points and native unread-thread count. */
  public function overview(): array {
    $count = $this->privateMessageService->getUnreadThreadCount();
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
  public function preview(): array {
    $threads = $this->privateMessageService->getThreadsForUser(50)['threads'];
    $items = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->setCacheMaxAge(0);
    $viewBuilder = $this->entityTypeManager->getViewBuilder('private_message_thread');

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
      $item = $viewBuilder->view($thread, 'inbox');
      $this->canonicalizePrivateMessageLinks($item);
      $items[] = $item;
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

  /** Rewrites Private Message thread actions to the canonical account purpose. */
  private function canonicalizePrivateMessageLinks(array &$build): void {
    foreach ($build as &$element) {
      if (!is_array($element)) {
        continue;
      }
      $url = $element['#url'] ?? NULL;
      if ($url instanceof Url) {
        $routeName = $url->getRouteName();
        if (is_string($routeName)
          && (str_starts_with($routeName, 'private_message.')
            || str_starts_with($routeName, 'entity.private_message.')
            || str_starts_with($routeName, 'entity.private_message_thread.'))
          && ($canonical = $this->domainPurposeManager->routeUrl('account', $routeName, $url->getRouteParameters())) !== NULL) {
          $element['#url'] = $canonical;
        }
      }
      $this->canonicalizePrivateMessageLinks($element);
    }
    unset($element);
  }

  /** Displays currently active native Site Alerts in the same-origin modal. */
  public function alertsPreview(): array {
    $storage = $this->entityTypeManager->getStorage('site_alert');
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['active_site_alerts'])
      ->addCacheTags($this->entityTypeManager->getDefinition('site_alert')->getListCacheTags())
      ->setCacheMaxAge($storage->getCacheMaxAge());
    if ($this->currentUser->isAuthenticated()) {
      $cacheability->addCacheContexts(['user'])->setCacheMaxAge(0);
    }
    $items = [];

    foreach ($this->siteAlerts->getActiveAlerts() as $alert) {
      $access = $alert->access('view', $this->currentUser, TRUE);
      $cacheability->addCacheableDependency($alert)->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }
      // Match the public render contract of SiteAlertBlock; formatted text and
      // severity remain owned by the contrib entity and its theme hook.
      $items[] = [
        '#theme' => 'site_alert',
        '#alert' => [
          'severity' => $alert->getSeverity(),
          'label' => $alert->getLabel(),
          'message' => ['#type' => 'markup', '#markup' => $alert->getMessage()],
        ],
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
    return $build;
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
      $read = $flag instanceof FlagInterface
        && $this->flagService->getFlagging($flag, $node, $this->currentUser) !== NULL;
      if ($flag instanceof FlagInterface) {
        $cacheability->addCacheTags($this->entityTypeManager->getDefinition('flagging')->getListCacheTags());
      }
      $item = $this->entityTypeManager->getViewBuilder('node')->view($node, 'full');
      if ($flag instanceof FlagInterface) {
        $item['aculta_notice_read_action'] = $flag->getLinkTypePlugin()->getAsFlagLink($flag, $node);
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
