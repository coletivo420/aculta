<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\user\UserDataInterface;
use Drupal\aculta_messenger\Service\ConversationPolicy;
use Drupal\aculta_messenger\Service\ConversationWriteLock;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\private_message\Entity\PrivateMessageThreadInterface;
use Drupal\private_message\Service\PrivateMessageBanManagerInterface;

/**
 * Applies block and send-frequency rules to the native Private Message form. */
final class PrivateMessageFormValidator {

  private const EVENT = 'aculta_messenger.private_message_send';


  private const WINDOW = 3600;

  private const RATE_LIMIT_LOCK_LEASE_SECONDS = 180.0;

  /**
   * Lock held from flood validation until the native submit persists the send.
   */
  private ?string $rateLimitLockName = NULL;

  /**
   * Thread lock held until the submitted message is persisted.
   */
  private ?int $conversationLockThreadId = NULL;

  public function __construct(
    private readonly FloodInterface $flood,
    private readonly AccountProxyInterface $currentUser,
    private readonly TranslationInterface $translation,
    private readonly PrivateMessageBanManagerInterface $banManager,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly UserDataInterface $userData,
    private readonly LockBackendInterface $lock,
    private readonly ConversationPolicy $policy,
    private readonly ConversationWriteLock $conversationWriteLock,
  ) {}

  /**
   * Rejects recipients who have blocked the current sender. */
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
      // A sender block prevents sending; the conversation remains listed so
      // the member can unblock through its options.
      if ($memberId !== (int) $this->currentUser->id() && $this->banManager->isBanned($memberId)) {
        $formState->setErrorByName('members', $this->translation->translate('Você bloqueou este membro. Desbloqueie-o nas opções da conversa.'));
        return;
      }
      if ($memberId !== (int) $this->currentUser->id()
        && $this->banManager->isCurrentUserBannedByUser($memberId)) {
        // Do not disclose whether the recipient exists or who blocked whom.
        $formState->setErrorByName('members', $this->translation->translate('Não foi possível enviar a mensagem para esse destinatário.'));
        return;
      }
    }
  }

  /**
   * Restricts new threads according to the target member's preference. */
  public function validateNewConversationPrivacy(array &$form, FormStateInterface $formState): void {
    if ($formState->hasAnyErrors()
      || $formState->get('thread') !== NULL
      || $formState->get('existing_conversation') === TRUE) {
      return;
    }

    $thread = $formState->get('pmt_entity');
    if (!$thread instanceof PrivateMessageThreadInterface) {
      return;
    }

    if (!$this->canStartNewConversation($thread)) {
      $formState->setErrorByName('members', $this->translation->translate('Não foi possível iniciar uma conversa com este destinatário.'));
    }
  }

  /**
   * Checks recipient privacy without changing Form API validation state.
   */
  public function canStartNewConversation(PrivateMessageThreadInterface $thread): bool {
    // Staff roles may start new conversations despite the target preference.
    // The preceding block validation always remains in force.
    if ($this->currentUser->hasRole('administrator') || $this->currentUser->hasRole('content_editor')) {
      return TRUE;
    }

    foreach ($thread->getMembersId() as $memberId) {
      $recipientId = (int) $memberId;
      if ($recipientId < 1 || $recipientId === (int) $this->currentUser->id()) {
        continue;
      }
      $preference = $this->userData->get('aculta_messenger', $recipientId, 'message_privacy');
      if ($preference === 'friends' && !$this->friendshipManager->areFriendsWith($recipientId)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Forces the native long-text field to persist using Drupal's plain format. */
  public function enforcePlainTextFormat(array &$form, FormStateInterface $formState): void {
    $formState->setValue(['message', 0, 'format'], 'plain_text');
  }

  /**
   * Hides text-format controls and generic guidelines from the chat composer. */
  public function hidePlainTextGuidelines(array $form, FormStateInterface $formState): array {
    if (isset($form['message']['widget'][0]['format'])) {
      $form['message']['widget'][0]['format']['#access'] = FALSE;
    }
    return $form;
  }

  /**
   * Validates one message submission against the Core flood backend.
   *
   * Ten valid submissions are allowed per account in a rolling one-hour
   * window; the next submission is rejected. Registration happens only after
   * the message is persisted.
   */
  public function validateRateLimit(array &$form, FormStateInterface $formState): void {
    if ($formState->hasAnyErrors()) {
      return;
    }

    $identifier = 'uid:' . $this->currentUser->id();
    $lockName = 'aculta_messenger.private_message_flood.' . $this->currentUser->id();
    if ($this->rateLimitLockName === NULL) {
      if (!$this->lock->acquire($lockName, self::RATE_LIMIT_LOCK_LEASE_SECONDS)) {
        $formState->setErrorByName('message', $this->translation->translate('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.'));
        return;
      }
      $this->rateLimitLockName = $lockName;
    }
    try {
      $allowed = $this->flood->isAllowed(self::EVENT, $this->policy->sendLimitPerHour(), self::WINDOW, $identifier);
    }
    catch (\Throwable $exception) {
      $this->releaseRateLimitLock();
      throw $exception;
    }
    if (!$allowed) {
      $this->releaseRateLimitLock();
      $formState->setErrorByName('message', $this->translation->translate('Você atingiu o limite temporário de envio de mensagens. Tente novamente mais tarde.'));
      return;
    }

    $thread = $formState->get('thread');
    if ($thread instanceof PrivateMessageThreadInterface && (int) $thread->id() > 0) {
      $freshThread = $this->acquireThreadWriteLock($thread, $formState);
      if ($freshThread instanceof PrivateMessageThreadInterface && !$formState->hasAnyErrors()) {
        try {
          // Block state may have changed after the first validation. Recheck
          // against the freshly loaded thread immediately before submission.
          $this->validateBlockedRecipients($form, $formState);
        }
        catch (\Throwable $exception) {
          $this->releaseSubmissionLocks();
          throw $exception;
        }
        if ($formState->hasAnyErrors()) {
          $this->releaseSubmissionLocks();
        }
      }
    }

  }

  /**
   * Locks and refreshes an existing conversation before a new message is saved.
   *
   * Called by the custom first-message workflow after it resolves an existing
   * thread; native replies acquire the same lock in validateRateLimit().
   *
   * @return \Drupal\private_message\Entity\PrivateMessageThreadInterface|null
   *   Fresh thread when locked, or NULL when another operation owns it or it
   *   was deleted before the lock could be acquired.
   */
  public function acquireThreadWriteLock(
    PrivateMessageThreadInterface $thread,
    FormStateInterface $formState,
  ): ?PrivateMessageThreadInterface {
    $threadId = (int) $thread->id();
    if ($threadId < 1) {
      return $thread;
    }
    if ($this->conversationLockThreadId === $threadId) {
      return $thread;
    }

    $freshThread = $this->conversationWriteLock->acquire($thread);
    if (!$freshThread instanceof PrivateMessageThreadInterface) {
      $this->releaseRateLimitLock();
      $formState->setErrorByName('message', $this->translation->translate('Esta conversa está sendo atualizada. Tente novamente.'));
      return NULL;
    }

    $this->conversationLockThreadId = $threadId;
    $formState->set('thread', $freshThread);
    return $freshThread;
  }

  /**
   * Registers a successfully persisted message in the Core flood backend.
   */
  public function registerRateLimit(array &$form, FormStateInterface $formState): void {
    $identifier = 'uid:' . $this->currentUser->id();
    try {
      $this->flood->register(self::EVENT, self::WINDOW, $identifier);
    }
    finally {
      $this->releaseRateLimitLock();
    }
  }

  /**
   * Releases submission locks when persistence fails before the Flood callback.
   */
  public function releaseSubmissionLocks(): void {
    $this->releaseRateLimitLock();
  }

  /**
   * Releases this request's flood lock, if validation acquired it.
   */
  private function releaseRateLimitLock(): void {
    if ($this->conversationLockThreadId !== NULL) {
      $this->conversationWriteLock->release($this->conversationLockThreadId);
      $this->conversationLockThreadId = NULL;
    }
    if ($this->rateLimitLockName !== NULL) {
      $this->lock->release($this->rateLimitLockName);
      $this->rateLimitLockName = NULL;
    }
  }

}
