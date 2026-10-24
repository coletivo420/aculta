<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Form\NoticeReadStateForm;
use Drupal\aculta_messenger\Service\NoticeReadActionBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\message\MessageInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests batch attachment of read-state forms to personal notice render items.
 */
#[CoversClass(NoticeReadActionBuilder::class)]
#[Group('aculta_messenger')]
final class NoticeReadActionBuilderTest extends UnitTestCase {

  /**
   * Only owned Messages with supported bundles receive a read-state form.
   */
  public function testAttachesFormsOnlyToOwnedSupportedMessages(): void {
    $supported = $this->message(12, 42, 'aculta_msg_wiki_notice');
    $unsupported = $this->message(13, 42, 'unsupported');
    $otherOwner = $this->message(14, 84, 'aculta_msg_wiki_notice');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects(self::once())->method('loadMultiple')->with([12, 13, 14])->willReturn([
      12 => $supported,
      13 => $unsupported,
      14 => $otherOwner,
    ]);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())->method('getStorage')->with('message')->willReturn($storage);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $account->method('id')->willReturn(42);
    $form = ['#type' => 'form'];
    $builder = $this->createMock(FormBuilderInterface::class);
    $builder->expects(self::once())->method('getForm')
      ->with(NoticeReadStateForm::class, 12, TRUE)
      ->willReturn($form);
    $items = [
      ['#attributes' => ['data-aculta-notice-message-id' => '12', 'data-aculta-notice-read' => 'unread']],
      ['#attributes' => ['data-aculta-notice-message-id' => '13', 'data-aculta-notice-read' => 'read']],
      ['#attributes' => ['data-aculta-notice-message-id' => '14', 'data-aculta-notice-read' => 'read']],
    ];

    $result = (new NoticeReadActionBuilder($entities, $builder))->attach($items, $account);

    self::assertSame($form, $result[0]['read_state_action']);
    self::assertArrayNotHasKey('read_state_action', $result[1]);
    self::assertArrayNotHasKey('read_state_action', $result[2]);
  }

  /**
   * Anonymous accounts do not cause notice entity loads.
   */
  public function testAnonymousAccountsReceiveNoActions(): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getStorage');
    $builder = $this->createMock(FormBuilderInterface::class);
    $builder->expects(self::never())->method('getForm');
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);
    $items = [['#attributes' => ['data-aculta-notice-message-id' => '12', 'data-aculta-notice-read' => 'unread']]];

    self::assertSame($items, (new NoticeReadActionBuilder($entities, $builder))->attach($items, $account));
  }

  /**
   * Creates a notice Message with ownership and type metadata.
   */
  private function message(int $id, int $ownerId, string $bundle): MessageInterface {
    $message = $this->createMock(MessageInterface::class);
    $message->method('id')->willReturn((string) $id);
    $message->method('getOwnerId')->willReturn($ownerId);
    $message->method('bundle')->willReturn($bundle);
    return $message;
  }

}
