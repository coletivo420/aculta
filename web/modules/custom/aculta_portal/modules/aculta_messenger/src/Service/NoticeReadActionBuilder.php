<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Service;

use Drupal\aculta_messenger\Form\NoticeReadStateForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;

/**
 * Adds read-state forms to authorized personal notice render items in batches.
 */
final class NoticeReadActionBuilder {

  private const SUPPORTED_BUNDLES = [
    'aculta_msg_lms_notice',
    'aculta_msg_wiki_notice',
    'aculta_msg_commerce_order_notice',
    'aculta_msg_donation_notice',
    'aculta_msg_friendship',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Adds forms only to owned items backed by supported Messages.
   */
  public function attach(array $items, AccountInterface $account): array {
    if (!$account->isAuthenticated()) {
      return $items;
    }

    $ids = [];
    foreach ($items as $item) {
      $attributes = $item['#attributes'] ?? [];
      $id = is_array($attributes) ? filter_var($attributes['data-aculta-notice-message-id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
      if ($id !== FALSE && $id > 0 && in_array($attributes['data-aculta-notice-read'] ?? NULL, ['read', 'unread'], TRUE)) {
        $ids[$id] = TRUE;
      }
    }
    if ($ids === []) {
      return $items;
    }

    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple(array_keys($ids));
    foreach ($items as &$item) {
      $attributes = $item['#attributes'] ?? [];
      $id = is_array($attributes) ? filter_var($attributes['data-aculta-notice-message-id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
      $readState = is_array($attributes) ? ($attributes['data-aculta-notice-read'] ?? NULL) : NULL;
      $message = $id === FALSE ? NULL : ($messages[$id] ?? NULL);
      if (!$message instanceof MessageInterface
        || (int) $message->getOwnerId() !== (int) $account->id()
        || !in_array($message->bundle(), self::SUPPORTED_BUNDLES, TRUE)
        || !in_array($readState, ['read', 'unread'], TRUE)) {
        continue;
      }
      $item['read_state_action'] = $this->formBuilder->getForm(
        NoticeReadStateForm::class,
        (int) $message->id(),
        $readState === 'unread',
      );
    }
    unset($item);
    return $items;
  }

}
