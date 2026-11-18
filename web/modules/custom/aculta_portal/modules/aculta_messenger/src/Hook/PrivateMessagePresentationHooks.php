<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\aculta_messenger\Entity\PrivateMessageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Prepares privacy-aware, theme-neutral sender data for message rendering. */
final class PrivateMessagePresentationHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'aculta_user.avatar_resolver')]
    private readonly AvatarResolver $avatars,
    #[Autowire(service: 'module_handler')]
    private readonly ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Prepares the private-message notification block presentation variables.
   *
   * @param array<string, mixed> $variables
   *   The variables supplied by the theme system.
   */
  #[Hook('preprocess_private_message_notification_block')]
  public function preprocessNotificationBlock(array &$variables): void {
    $rawCount = $variables['new_items_count'] ?? 0;
    $parsedCount = is_int($rawCount) || is_string($rawCount)
      ? filter_var($rawCount, FILTER_VALIDATE_INT)
      : FALSE;
    $count = is_int($parsedCount) ? max(0, $parsedCount) : 0;
    $countMethod = ($variables['count_method'] ?? 'threads') === 'messages' ? 'messages' : 'threads';
    $variables['unread_notification_class'] = 'unread-' . $countMethod;

    $request = $this->requestStack->getCurrentRequest();
    $basePath = $request?->getBasePath() ?? '';
    if ($basePath === '' && $request !== NULL) {
      $scriptNameValue = $request->server->get('SCRIPT_NAME', '/index.php');
      $scriptName = is_string($scriptNameValue) ? $scriptNameValue : '/index.php';
      $directory = dirname($scriptName);
      $basePath = $directory === '/' || $directory === '.' ? '' : $directory;
    }
    $module = $this->moduleHandler->getModule('aculta_messenger');
    $variables['notification_image_path'] = rtrim($basePath, '/') . '/' . trim($module->getPath(), '/') . '/images/private-message-notification-icon.png';

    $linkTitle = $this->translation->formatPlural(
      $count,
      'You have 1 unread private message',
      'You have @count unread private messages',
    );
    $url = Url::fromRoute('private_message.private_message_page');
    $url->setOption('attributes', [
      'aria-label' => $linkTitle,
      'title' => $linkTitle,
    ]);
    $variables['private_message_page_link'] = [
      '#type' => 'link',
      '#title' => $count > 99 ? '99+' : (string) $count,
      '#url' => $url,
    ];
  }

  /**
   * Adds the sender avatar and direction state without changing message data.
   *
   * @param array<string, mixed> $variables
   *   O valor informado para $variables.
   */
  #[Hook('preprocess_private_message')]
  public function preprocessPrivateMessage(array &$variables): void {
    $message = $variables['private_message'] ?? NULL;
    if (!$message instanceof PrivateMessageInterface) {
      $elements = $variables['elements'] ?? NULL;
      $message = is_array($elements) ? ($elements['#private_message'] ?? NULL) : NULL;
    }
    if (!$message instanceof PrivateMessageInterface) {
      return;
    }

    $variables['aculta_messenger_outgoing'] = (int) $message->getOwnerId() === (int) $this->currentUser->id();
    $sender = $message->getOwner();
    $access = $sender->access('view', $this->currentUser, TRUE);
    if (!$access->isAllowed()) {
      return;
    }

    // Mesma regra do avatar público: foto, depois o acervo ilustrativo, que é o
    // fallback final. A inicial do nome não é usada.
    $name = (string) $sender->getDisplayName();
    $cacheability = (new CacheableMetadata())
      ->addCacheableDependency($sender)
      ->addCacheContexts(['user'])
      ->addCacheTags(CacheableMetadata::createFromObject($access)->getCacheTags());
    $avatar = $this->avatars->build($sender, $name, TRUE, $cacheability);
    if ($avatar === NULL) {
      return;
    }
    $cacheability->applyTo($avatar);
    $variables['aculta_messenger_sender_avatar'] = $avatar;
  }

}
