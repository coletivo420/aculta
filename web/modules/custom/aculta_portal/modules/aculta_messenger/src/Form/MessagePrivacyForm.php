<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;

/**
 * Reads and saves who may start a conversation with the member (the "all" or "friends" preference).
 *
 * The value is enforced by PrivateMessageFormValidator; this service is the only writer of that preference.
 */
final class MessagePrivacyForm {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly UserDataInterface $userData,
  ) {}

  /** Current preference of the signed-in member; defaults to all members. */
  public function currentPreference(): string {
    return $this->userData->get('aculta_messenger', (int) $this->currentUser->id(), 'message_privacy') === 'friends'
      ? 'friends'
      : 'all';
  }

  /** Submit callback added to the Privacidade form. */
  public function submit(array &$form, FormStateInterface $formState): void {
    $preference = $formState->getValue('message_privacy') === 'friends' ? 'friends' : 'all';
    $this->userData->set('aculta_messenger', (int) $this->currentUser->id(), 'message_privacy', $preference);
  }

}
