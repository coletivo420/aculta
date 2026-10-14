<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Controller;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\message\MessageInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
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
    private readonly TranslationInterface $translation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('private_message.service'),
      $container->get('string_translation'),
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

  /** Shows the future persistent-notice collection without inventing storage. */
  public function notices(): array {
    return [
      'empty' => ['#plain_text' => (string) $this->translation->translate('Não há avisos institucionais disponíveis nesta área.')],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
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
    $items = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user'])
      ->addCacheTags($this->entityTypeManager->getDefinition('message')->getListCacheTags());
    foreach ($messages as $message) {
      if (!$message instanceof MessageInterface || (int) $message->getOwnerId() !== $uid) {
        continue;
      }
      $items[] = $this->entityTypeManager->getViewBuilder('message')->view($message, 'mail_body', 'pt-br');
      $cacheability->addCacheableDependency($message);
    }

    if ($items === []) {
      $items[] = ['#plain_text' => (string) $this->translation->translate('Você ainda não recebeu notificações de mensagens.')];
    }

    $build = ['items' => ['#theme' => 'item_list', '#items' => $items], '#cache' => ['max-age' => 0]];
    $cacheability->applyTo($build);
    return $build;
  }

}
