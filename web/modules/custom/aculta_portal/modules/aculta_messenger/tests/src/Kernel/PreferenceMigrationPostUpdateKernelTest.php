<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\PrivateMessageEmailPreferenceMigration;
use Drupal\aculta_messenger\Aviso\SharedEmailPreferenceMigration;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies preference post-update wrappers against Drupal's user-data storage.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class PreferenceMigrationPostUpdateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);
    require_once dirname(__DIR__, 3) . '/aculta_messenger.post_update.php';
  }

  /**
   * The Private Message post-update copies legacy choices and can be replayed.
   */
  public function testPrivateMessagePreferencePostUpdate(): void {
    $userData = $this->container->get('user.data');
    $legacyConfig = $this->createMock(ImmutableConfig::class);
    $legacyConfig->method('get')->willReturnMap([
      ['enable_notifications', FALSE],
      ['notify_by_default', FALSE],
    ]);
    $editableConfig = $this->createMock(Config::class);
    $editableConfig->method('get')->willReturnMap([
      ['email_modes.' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES, NULL],
      ['private_message_digest_anchor', NULL],
    ]);
    $editableConfig->method('set')->willReturnSelf();
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('private_message.settings')->willReturn($legacyConfig);
    $configFactory->method('getEditable')->with('aculta_messenger.settings')->willReturn($editableConfig);

    $userData->set('private_message', 27, 'receive_notification', TRUE);
    $userData->set('private_message', 28, 'receive_notification', FALSE);
    $userData->set(AvisoPreferencias::MODULO, 28, 'avisos_email_mode_mensagens_privadas', AvisoPreferencias::EMAIL_DIGEST);

    $migration = new PrivateMessageEmailPreferenceMigration(
      $configFactory,
      $userData,
      $this->container->get('database'),
    );
    $this->container->set('aculta_messenger.private_message_email_preference_migration', $migration);
    $sandbox = [];

    self::assertSame(
      'Migrated Private Message email preferences to ACULTA Messenger.',
      aculta_messenger_post_update_private_message_email_preferences($sandbox),
    );
    self::assertSame(AvisoPreferencias::EMAIL_IMMEDIATE, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_mensagens_privadas'));
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 28, 'avisos_email_mode_mensagens_privadas'));
    self::assertNull($userData->get('private_message', 27, 'receive_notification'));
    self::assertNull($userData->get('private_message', 28, 'receive_notification'));
    self::assertSame(1, $sandbox['#finished']);

    self::assertSame(
      'Migrated Private Message email preferences to ACULTA Messenger.',
      aculta_messenger_post_update_private_message_email_preferences($sandbox),
    );
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 28, 'avisos_email_mode_mensagens_privadas'));
  }

  /**
   * The shared digest post-update calls the registered bounded migrator.
   */
  public function testSharedPreferencePostUpdate(): void {
    $userData = $this->container->get('user.data');
    $userData->set(AvisoPreferencias::MODULO, 27, 'avisos_resumo_email', TRUE);
    $migration = new SharedEmailPreferenceMigration($userData, $this->container->get('database'));
    $this->container->set('aculta_messenger.shared_email_preference_migration', $migration);
    $sandbox = [];

    self::assertSame(
      'Migrated 1 legacy shared-notice email preferences.',
      aculta_messenger_post_update_shared_email_preferences($sandbox),
    );
    self::assertSame(1, $sandbox['#finished']);
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_forum_resposta'));
    self::assertNull($userData->get(AvisoPreferencias::MODULO, 27, 'avisos_resumo_email'));

    self::assertSame(
      'Migrated 0 legacy shared-notice email preferences.',
      aculta_messenger_post_update_shared_email_preferences($sandbox),
    );
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_forum_resposta'));
  }

}
