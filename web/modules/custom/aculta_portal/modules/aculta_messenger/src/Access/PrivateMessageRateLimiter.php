<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Access;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;

/** Applies Drupal Flood limits to messages sent through Private Message. */
final class PrivateMessageRateLimiter {

  private const EVENT = 'aculta_messenger.private_message_send';

  private const LIMIT = 10;

  private const WINDOW = 3600;

  public function __construct(
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $translation,
  ) {}

  /**
   * Validates and registers one message submission in the Core flood backend.
   *
   * This callback runs after Private Message's recipient and ban validators.
   * Invalid submissions are not counted. Ten valid submissions are allowed
   * per account in a rolling one-hour window; the next submission is rejected.
   */
  public function validate(array &$form, FormStateInterface $formState): void {
    if ($formState->hasAnyErrors()) {
      return;
    }

    $identifier = 'uid:' . $this->currentUser->id();
    if (!$this->flood->isAllowed(self::EVENT, self::LIMIT, self::WINDOW, $identifier)) {
      $formState->setErrorByName('message', $this->translation->translate('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.'));
      return;
    }

    $this->flood->register(self::EVENT, self::WINDOW, $identifier);
  }

}
