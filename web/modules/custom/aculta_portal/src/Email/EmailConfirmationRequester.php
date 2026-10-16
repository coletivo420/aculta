<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Url;
use Drupal\email_confirmer\EmailConfirmationInterface;
use Drupal\email_confirmer\EmailConfirmerManagerInterface;
use Drupal\user\UserInterface;
use Throwable;

/** Reliably requests a new registration confirmation without invalidating a usable link on failure. */
final class EmailConfirmationRequester {

  public const REALM = 'aculta_registration';

  private const FLOOD_EVENT = 'aculta_portal.email_confirmation_request';

  private const RESEND_WINDOW = 900;

  public function __construct(
    private readonly EmailConfirmerManagerInterface $manager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FloodInterface $flood,
    private readonly LockBackendInterface $lock,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Requests a confirmation for an account.
   *
   * ACCEPTED means Email Confirmer's MailManager call returned success. It does
   * not establish final delivery to the recipient's mailbox.
   */
  public function request(UserInterface $account): EmailConfirmationRequestResult {
    $uid = (int) $account->id();
    $email = trim((string) $account->getEmail());
    if ($account->isNew() || $uid <= 0 || $email === '') {
      return EmailConfirmationRequestResult::INVALID_ACCOUNT;
    }

    $lockName = 'aculta_portal.email_confirmation_requester.' . $uid;
    if (!$this->lock->acquire($lockName, 60.0)) {
      return EmailConfirmationRequestResult::BUSY;
    }

    try {
      $identifier = (string) $uid;
      if (!$this->flood->isAllowed(self::FLOOD_EVENT, 1, self::RESEND_WINDOW, $identifier)) {
        return EmailConfirmationRequestResult::RATE_LIMITED;
      }
      // Count failed attempts too, so a failing transport cannot be abused by
      // repeatedly submitting the same account's request.
      $this->flood->register(self::FLOOD_EVENT, self::RESEND_WINDOW, $identifier);

      $storage = $this->entityTypeManager->getStorage('email_confirmer_confirmation');
      $confirmation = $this->manager->createConfirmation($email);
      $confirmation->setRealm(self::REALM)
        ->setProperty('user', $uid)
        ->setPrivate()
        ->setResponseUrl(Url::fromRoute('aculta_portal.dashboard'), 'confirm');

      // Persist first: the email must never contain a token for an entity that
      // failed to save. The previous usable confirmation remains untouched.
      $confirmation->save();

      try {
        $accepted = $confirmation->sendRequest();
      }
      catch (Throwable $exception) {
        $this->logFailure('The email confirmation backend threw while sending.', $exception);
        // An exception does not prove that an external transport rejected the
        // message. Keep this persisted token usable and preserve prior tokens.
        return EmailConfirmationRequestResult::FAILED;
      }

      if (!$accepted) {
        $this->logger->error('The email confirmation MailManager result was rejected.');
        try {
          $confirmation->delete();
        }
        catch (Throwable $exception) {
          $this->logFailure('An unsent email confirmation could not be removed.', $exception);
        }
        return EmailConfirmationRequestResult::FAILED;
      }

      // sendRequest() sets the sent timestamp only when MailManager accepts it.
      // The entity already exists, so a timestamp-save failure cannot make the
      // accepted email's confirmation URL point to a missing entity.
      try {
        $confirmation->save();
      }
      catch (Throwable $exception) {
        $this->logFailure('An accepted email confirmation timestamp could not be saved.', $exception);
        return EmailConfirmationRequestResult::ACCEPTED;
      }

      // The new request is already accepted: a failure to list or cancel the old tokens must not report it as failed.
      try {
        $pending = $this->pendingFor($email, $uid);
      }
      catch (Throwable $exception) {
        $this->logFailure('The previous email confirmations could not be listed.', $exception);
        return EmailConfirmationRequestResult::ACCEPTED;
      }
      foreach ($pending as $old) {
        if ($old->id() === $confirmation->id()) {
          continue;
        }
        try {
          $old->cancel();
          $old->save();
        }
        catch (Throwable $exception) {
          // SMTP is already outside the database transaction. Keep the accepted
          // new request and report only its acceptance, even if an old token
          // could not be invalidated.
          $this->logFailure('A previous email confirmation could not be cancelled.', $exception);
        }
      }

      return EmailConfirmationRequestResult::ACCEPTED;
    }
    catch (Throwable $exception) {
      $this->logFailure('The email confirmation request failed before acceptance.', $exception);
      return EmailConfirmationRequestResult::FAILED;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /** @return \Drupal\email_confirmer\EmailConfirmationInterface[] */
  private function pendingFor(string $email, int $uid): array {
    $storage = $this->entityTypeManager->getStorage('email_confirmer_confirmation');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('realm', self::REALM)
      ->condition('email', $email)
      ->condition('status', EmailConfirmationInterface::ACTIVE)
      ->condition('confirmed', EmailConfirmationInterface::UNCONFIRMED)
      ->sort('created', 'DESC')
      ->execute();

    return array_values(array_filter(
      $storage->loadMultiple($ids),
      static fn(EmailConfirmationInterface $confirmation): bool => $confirmation->isPending()
        && (int) $confirmation->getProperty('user') === $uid,
    ));
  }

  private function logFailure(string $message, Throwable $exception): void {
    // The exception message may contain an address or transport payload. Log
    // only its class so operational logs do not acquire message PII.
    $this->logger->error('@message Exception type: @type', [
      '@message' => $message,
      '@type' => $exception::class,
    ]);
  }

}
