<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Wiki;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_mailer\Message\MessageIdFactory;
use Drupal\aculta_messenger\Integration\Wiki\Mail\WikiEditorialReviewMailType;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;

/**
 * Queues the administrative Wiki review email after revalidating the revision.
 *
 * The configuration moved from `aculta_wiki.settings` to the central Messenger
 * settings. Recipients are editorial mailboxes, not personal preferences, so
 * this channel never becomes an account opt-in. Delivery is deduplicated by
 * entry revision and recipient; the Mailer remains the only transport.
 */
final class EditorialReviewNotice implements EditorialReviewNoticeInterface {

  use StringTranslationTrait;

  /**
   * Central communication settings.
   */
  public const SETTINGS = 'aculta_messenger.settings';

  /**
   * Key that turns the editorial review email on.
   */
  public const ENABLED_KEY = 'wiki_review_notifications';

  /**
   * Key with the editorial mailboxes.
   */
  public const RECIPIENTS_KEY = 'wiki_review_recipients';

  /**
   * Editorial state that makes the revision eligible.
   */
  public const REVIEW_STATE = 'review';

  /**
   * Deduplication collection, kept for one retention window.
   */
  public const DEDUPE_COLLECTION = 'aculta_messenger.wiki_editorial_review';

  /**
   * Deduplication TTL in seconds (90 days, matching mailer recipient logs).
   */
  private const DEDUPE_TTL = 7776000;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly KeyValueExpirableFactoryInterface $dedupeStorage,
    private readonly LockBackendInterface $lock,
    TranslationInterface $stringTranslation,
    private readonly ?object $queue = NULL,
    private readonly ?object $publicUrl = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public function notifyReview(string $entryUuid, int $revisionId): int {
    if (!$this->isEnabled() || $this->queue === NULL || !method_exists($this->queue, 'enqueue')) {
      return 0;
    }
    $revision = $this->entityTypeManager->getStorage('node')->loadRevision($revisionId);
    if (!$revision instanceof NodeInterface
      || $revision->uuid() !== $entryUuid
      || $revision->bundle() !== 'wiki_entry'
      || !$revision->isDefaultRevision()
      || !$revision->hasField('moderation_state')
      || (string) $revision->get('moderation_state')->value !== self::REVIEW_STATE) {
      return 0;
    }
    $recipients = self::parseRecipients((string) $this->configFactory->get(self::SETTINGS)->get(self::RECIPIENTS_KEY));
    if ($recipients === []) {
      return 0;
    }

    $context = $this->context($revision);
    $store = $this->dedupeStorage->get(self::DEDUPE_COLLECTION);
    $queued = 0;
    foreach ($recipients as $recipient) {
      $key = implode(':', ['wiki_editorial_review', $entryUuid, (string) $revisionId, $recipient]);
      $lockId = 'aculta_messenger:wiki_editorial_review:' . hash('sha256', $key);
      if (!$this->lock->acquire($lockId, 30.0)) {
        throw new \RuntimeException('The Wiki editorial review notice is currently being queued.');
      }
      try {
        if (!$store->setWithExpireIfNotExists($key, TRUE, self::DEDUPE_TTL)) {
          continue;
        }
        try {
          // Autoload of the queue only happens with aculta_mailer enabled.
          $this->queue->enqueue(new MailMessage(
            WikiEditorialReviewMailType::ID,
            $recipient,
            'pt-br',
            $context,
            MessageIdFactory::correlationIdFor($key),
          ));
        }
        catch (\Throwable $exception) {
          // Release the mark so a retry can queue the same logical delivery.
          $store->delete($key);
          throw $exception;
        }
        $queued++;
      }
      finally {
        $this->lock->release($lockId);
      }
    }
    return $queued;
  }

  /**
   * Whether the central configuration enables the editorial mailbox notice.
   */
  public function isEnabled(): bool {
    return (bool) $this->configFactory->get(self::SETTINGS)->get(self::ENABLED_KEY);
  }

  /**
   * Valid editorial mailboxes, lowercased and unique, in input order.
   *
   * @param string $raw
   *   Text with one or more addresses separated by comma, semicolon or space.
   *
   * @return list<string>
   *   Valid addresses.
   */
  public static function parseRecipients(string $raw): array {
    $valid = [];
    foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $candidate) {
      $address = strtolower(trim($candidate));
      if (filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE) {
        continue;
      }
      $valid[$address] = $address;
    }
    return array_values($valid);
  }

  /**
   * Values exposed to the mail template; authorship is intentionally absent.
   *
   * @return array<string, string>
   *   Template context.
   */
  private function context(NodeInterface $verbete): array {
    return [
      'verbete' => $verbete->label() ?? '',
      'estado' => (string) $this->t('Em revisão'),
      'link' => $this->editUrl($verbete),
    ];
  }

  /**
   * Absolute edit URL, or an empty string when the site cannot resolve a host.
   */
  private function editUrl(NodeInterface $verbete): string {
    if ($this->publicUrl === NULL || !method_exists($this->publicUrl, 'absolute')) {
      return '';
    }
    return (string) $this->publicUrl->absolute('/node/' . $verbete->id() . '/edit');
  }

}
