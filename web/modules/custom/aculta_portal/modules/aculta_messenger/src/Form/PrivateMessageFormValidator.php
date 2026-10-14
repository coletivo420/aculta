<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;

/** Applies block and send-frequency rules to the native Private Message form. */
final class PrivateMessageFormValidator {

  private const EVENT = 'aculta_messenger.private_message_send';

  private const LIMIT = 10;

  private const WINDOW = 3600;

  public function __construct(
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $translation,
    private readonly PrivateMessageBanManagerInterface $banManager,
  ) {}

  /** Rejects recipients who have blocked the current sender. */
  public function validateBlockedRecipients(array &$form, FormStateInterface $formState): void {
    if ($formState->hasAnyErrors()) {
      return;
    }

    $thread = $formState->get('thread') ?? $formState->get('pmt_entity');
    if (!$thread instanceof PrivateMessageThreadInterface) {
      return;
    }

    foreach ($thread->getMembersId() as $memberId) {
      $memberId = (int) $memberId;
      if ($memberId !== (int) $this->currentUser->id()
        && $this->banManager->isCurrentUserBannedByUser($memberId)) {
        // Do not disclose whether the recipient exists or who blocked whom.
        $formState->setErrorByName('members', $this->translation->translate('Não foi possível enviar a mensagem para esse destinatário.'));
        return;
      }
    }
  }

  /**
   * Validates and registers one message submission in the Core flood backend.
   *
   * Invalid submissions are not counted. Ten valid submissions are allowed
   * per account in a rolling one-hour window; the next submission is rejected.
   */
  public function validateRateLimit(array &$form, FormStateInterface $formState): void {
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
