<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\private_message\Service\PrivateMessageServiceInterface;

/** Adds the Messenger section to the Portal's neutral account shell contract. */
final class AccountSectionsHooks {

  public function __construct(
    #[Autowire(service: 'private_message.service')]
    private readonly PrivateMessageServiceInterface $privateMessageService,
    private readonly TranslationInterface $translation,
  ) {}

  /** Adds the navigation entry only when the account has native PM access. */
  #[Hook('aculta_portal_account_sections_alter')]
  public function addMessagesSection(array &$sections, AccountInterface $account): void {
    if (!$account->hasPermission('access aculta portal')
      || !$account->hasPermission('use private messaging system')
      || !$account->hasPermission('access user profiles')) {
      return;
    }

    $unread = $this->privateMessageService->getUnreadThreadCount();
    $messageSection = [
      'title' => $this->translation->translate('Mensagens'),
      'route' => 'aculta_messenger.messages',
      'icon' => 'chat-dots',
      'badge_count' => $unread,
    ];
    $orderedSections = [];
    foreach ($sections as $key => $section) {
      if ($key === 'view.commerce_user_orders.order_page') {
        $orderedSections['aculta_messenger.messages'] = $messageSection;
      }
      $orderedSections[$key] = $section;
    }
    if (!isset($orderedSections['aculta_messenger.messages'])) {
      $orderedSections['aculta_messenger.messages'] = $messageSection;
    }
    $sections = $orderedSections;
  }

  /** Maps native subpages to the account shell's single Messages section. */
  #[Hook('aculta_portal_account_parent_sections_alter')]
  public function addMessagesParentRoutes(array &$parentSections): void {
    foreach ([
      'aculta_messenger.conversations',
      'aculta_messenger.notices',
      'aculta_messenger.notifications',
      'private_message.private_message_page',
      'private_message.private_message_create',
      'entity.private_message.canonical',
      'entity.private_message.delete',
      'entity.private_message_thread.canonical',
      'entity.private_message_thread.delete_form',
      'entity.private_message_thread.clear_personal_history_form',
      'private_message.ban_page',
      'private_message.ban_user_form',
      'private_message.unban_user_form',
    ] as $route) {
      $parentSections[$route] = 'aculta_messenger.messages';
    }
  }

}
