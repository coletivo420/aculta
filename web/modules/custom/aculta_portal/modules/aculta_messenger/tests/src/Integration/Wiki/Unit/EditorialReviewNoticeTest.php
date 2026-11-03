<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Integration\Wiki\Unit;

use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Integration\Wiki\EditorialReviewNotice;
use Drupal\aculta_messenger\Integration\Wiki\Mail\WikiEditorialReviewMailType;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers the administrative Wiki review email owned by Messenger.
 */
#[CoversClass(EditorialReviewNotice::class)]
#[Group('aculta_messenger')]
final class EditorialReviewNoticeTest extends UnitTestCase {

  private const UUID = '22222222-2222-4222-8222-222222222222';

  /**
   * Messages queued through the mailer queue fixture.
   *
   * @var list<MailMessage>
   */
  private array $messages = [];

  /**
   * Deduplication marks shared by the storage fixture.
   *
   * @var array<string, bool>
   */
  private array $marks = [];

  /**
   * Queues one email per valid editorial recipient.
   */
  public function testQueuesOneEmailPerRecipient(): void {
    $notice = $this->notice([
      'wiki_review_notifications' => TRUE,
      'wiki_review_recipients' => 'Curadoria@Example.invalid, revisao@example.invalid',
    ]);

    $this->assertSame(2, $notice->notifyReview(self::UUID, 10));
    $this->assertCount(2, $this->messages);
    $this->assertSame(WikiEditorialReviewMailType::ID, $this->messages[0]->type);
    $this->assertSame('curadoria@example.invalid', $this->messages[0]->to);
    $this->assertSame('Cannabis medicinal', $this->messages[0]->context['verbete']);
  }

  /**
   * Replaying the same revision and recipient does not queue a duplicate.
   */
  public function testReplayDoesNotDuplicate(): void {
    $notice = $this->notice([
      'wiki_review_notifications' => TRUE,
      'wiki_review_recipients' => 'curadoria@example.invalid',
    ]);

    $this->assertSame(1, $notice->notifyReview(self::UUID, 10));
    $this->assertSame(0, $notice->notifyReview(self::UUID, 10));
    $this->assertCount(1, $this->messages);
  }

  /**
   * Disabled configuration queues nothing.
   */
  public function testDisabledConfigurationQueuesNothing(): void {
    $notice = $this->notice([
      'wiki_review_notifications' => FALSE,
      'wiki_review_recipients' => 'curadoria@example.invalid',
    ]);

    $this->assertSame(0, $notice->notifyReview(self::UUID, 10));
    $this->assertSame([], $this->messages);
  }

  /**
   * An invalid or stale revision queues nothing.
   */
  public function testInvalidRevisionQueuesNothing(): void {
    $notice = $this->notice([
      'wiki_review_notifications' => TRUE,
      'wiki_review_recipients' => 'curadoria@example.invalid',
    ], 'published');

    $this->assertSame(0, $notice->notifyReview(self::UUID, 10));
    $this->assertSame([], $this->messages);
  }

  /**
   * Parses and normalizes only valid addresses.
   */
  public function testParseRecipients(): void {
    $this->assertSame(
      ['a@example.invalid', 'b@example.invalid'],
      EditorialReviewNotice::parseRecipients(' A@Example.invalid; nao-e-email b@example.invalid '),
    );
  }

  /**
   * Builds the service with controlled dependencies.
   *
   * @param array<string, mixed> $settings
   *   Central Messenger settings.
   * @param string $state
   *   Moderation state returned by the revision fixture.
   */
  private function notice(array $settings, string $state = 'review'): EditorialReviewNotice {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): mixed => $settings[$key] ?? NULL);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with(EditorialReviewNotice::SETTINGS)->willReturn($config);

    $revision = $this->createMock(NodeInterface::class);
    $revision->method('uuid')->willReturn(self::UUID);
    $revision->method('bundle')->willReturn('wiki_entry');
    $revision->method('isDefaultRevision')->willReturn(TRUE);
    $revision->method('hasField')->with('moderation_state')->willReturn(TRUE);
    $list = $this->createMock(FieldItemListInterface::class);
    $list->method('__get')->willReturnCallback(static fn (string $name): mixed => $name === 'value' ? $state : NULL);
    $revision->method('get')->with('moderation_state')->willReturn($list);
    $revision->method('label')->willReturn('Cannabis medicinal');
    $revision->method('id')->willReturn(42);

    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->method('loadRevision')->with(10)->willReturn($revision);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    $store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $store->method('setWithExpireIfNotExists')->willReturnCallback(function (string $key): bool {
      if (isset($this->marks[$key])) {
        return FALSE;
      }
      $this->marks[$key] = TRUE;
      return TRUE;
    });
    $store->method('delete')->willReturnCallback(function (string $key): void {
      unset($this->marks[$key]);
    });
    $dedupe = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $dedupe->method('get')->with(EditorialReviewNotice::DEDUPE_COLLECTION)->willReturn($store);

    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $lock->method('release')->willReturn(NULL);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      static fn ($markup): string => $markup->getUntranslatedString(),
    );

    $queue = new class($this) {

      /**
       * Publica a mensagem no fixture do teste.
       */
      public function __construct(private readonly EditorialReviewNoticeTest $test) {}

      /**
       * Recebe a mensagem, como a fila real do mailer.
       */
      public function enqueue(MailMessage $message): void {
        $this->test->pushMessage($message);
      }

    };

    return new EditorialReviewNotice(
      $entityTypeManager,
      $configFactory,
      $dedupe,
      $lock,
      $translation,
      $queue,
    );
  }

  /**
   * Adds a queued message to the fixture.
   */
  public function pushMessage(MailMessage $message): void {
    $this->messages[] = $message;
  }

}
