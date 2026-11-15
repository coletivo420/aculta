<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\aculta_messenger\Traits\PrivateMessageTestTrait;
use Drupal\aculta_messenger\Entity\PrivateMessageBan;
use Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface;

/**
 * Tests deletion of private message entities.
 *
 * @group private_message
 */
final class EntityDeleteTest extends KernelTestBase {

  use PrivateMessageTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'message',
    'message_notify',
    'aculta_messenger',
    'token',
    'flag',
    'content_moderation',
    'pathauto',
    'path_alias',
    'path',
    'aculta_portal',
    'key',
    'aculta_file_uploader',
    'message_subscribe',
    'node',
    'datetime',
    'menu_ui',
    'options',
    'aculta_user',
    'aculta_mailer',
    'aculta_mail_confirmer',
    'aculta_forum',
    'site_alert',
    'system',
    'text',
    'user',
    'ctools',
    'ctools_views',
    'aculta_image',
    'profile',
    'views',
    'text_with_summary',
    'vvjs',
    'domain',
    'domain_alias',
    'domain_config',
    'domain_source',
    'address',
    'aculta_portal_cep',
    'login_emailusername',
    'social_auth',
    'group',
    'commerce',
    'commerce_cart',
    'commerce_checkout',
    'commerce_store',
    'commerce_order',
    'commerce_payment',
    'physical',
    'taxonomy',
    'media',
    'metatag',
    'schema_metatag',
    'schema_article',
    'schema_event',
    'schema_image_object',
    'schema_organization',
    'schema_product',
    'schema_web_site',
    'schema_web_page',
    'search_api_db',
    'commerce_price',
    'commerce_product',
    'state_machine',
    'migrate',
    'file',
    'entity_usage',
    'comment',
    'social_api',
    'workflows',
    'entity_reference_revisions',
    'image',
    'link',
  ];

  /**
   * The thread.
   *
   * @var \Drupal\aculta_messenger\Entity\PrivateMessageThreadInterface
   */
  protected PrivateMessageThreadInterface $thread;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('message');
    $this->installEntitySchema('private_message_ban');
    $this->installEntitySchema('private_message_thread');
    $this->installEntitySchema('private_message');
    $this->installSchema('aculta_user', ['aculta_user_nickname']);
    $this->installConfig('aculta_user');
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('profile');
    $this->installEntitySchema('path_alias');
    $this->installSchema('aculta_messenger', ['aculta_pm_thread_history']);

    $this->installConfig([
      'filter',
      'message',
      'message_notify',
    ]);

    $this->createTestingUsers();
    $this->thread = \Drupal::getContainer()->get('aculta_messenger.pm.service')->getThreadForMembers([
      $this->users['a'],
      $this->users['b'],
    ]);

    // Create a few messages.
    for ($i = 0; $i < 5; $i++) {
      /** @var \Drupal\aculta_messenger\Entity\PrivateMessageInterface $message */
      $message = \Drupal::entityTypeManager()
        ->getStorage('private_message')
        ->create([
          'owner' => $this->users['a'],
          'message' => $this->randomString(),
        ]);
      $message->save();
      $this->thread->addMessage($message);
    }
  }

  /**
   * Test callback.
   */
  public function testDeleteThread(): void {
    $this->thread->delete();

    // Assert that the thread has been deleted.
    /** @var int|string $threadId */
    $threadId = $this->thread->id();
    $this->assertNull(\Drupal::entityTypeManager()
      ->getStorage('private_message_thread')
      ->load($threadId));

    // Assert no messages are left.
    $this->assertEmpty(\Drupal::entityTypeManager()
      ->getStorage('private_message')
      ->loadMultiple());
  }

  /**
   * Tests that banned messages are also cleared.
   *
   * @dataProvider bannedMessagesProvider
   */
  public function testBannedMessages(string $logged_in): void {
    $this->setCurrentUser($this->users[$logged_in]);
    $ban = PrivateMessageBan::create([
      'owner' => $this->users['b']->id(),
      'target' => $this->users['a']->id(),
    ]);
    $ban->save();
    $this->thread->delete();

    // Assert that the thread has been deleted.
    /** @var int|string $threadId */
    $threadId = $this->thread->id();
    $this->assertNull(\Drupal::entityTypeManager()
      ->getStorage('private_message_thread')
      ->load($threadId));

    // Assert no messages are left.
    $this->assertEmpty(\Drupal::entityTypeManager()
      ->getStorage('private_message')
      ->loadMultiple());
  }

  /**
   * Provides test data for testBannedMessages().
   *
   * @return array[]
   *   The test data.
   */
  public static function bannedMessagesProvider(): array {
    return [
      // User A is logged in, which is the user that created the messages.
      ['a'],
      // User B is logged in, they banned user A, they cannot see the messages.
      ['b'],
    ];
  }

  /**
   * Test deleting a thread using the storage class.
   */
  public function testStorageThreadAndMessageDeletion(): void {
    \Drupal::entityTypeManager()->getStorage('private_message_thread')->delete([$this->thread]);
    /** @var int|string $threadId */
    $threadId = $this->thread->id();
    $this->assertNull(\Drupal::entityTypeManager()
      ->getStorage('private_message')
      ->load($threadId));
  }

}
