<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\PrivateMessageEmailPreferenceMigration;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the administrative half of legacy preference migration. */
#[CoversClass(PrivateMessageEmailPreferenceMigration::class)]
#[Group('aculta_messenger')]
final class PrivateMessageEmailPreferenceMigrationTest extends UnitTestCase {

  /**
   * User choices are copied in bounded batches, then legacy keys are removed.
   */
  public function testUserBatchCopiesMissingValuesAndPreservesMessengerChoice(): void {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchCol')->willReturn([4, 8]);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('range')->with(0, 2)->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->with('users_data', 'ud')->willReturn($query);
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(
      static fn(string $module, int $uid, string $key): mixed => match ([$module, $uid, $key]) {
      ['private_message', 4, 'receive_notification'] => 1,
      ['aculta_messenger', 4, 'avisos_email_mode_mensagens_privadas'] => NULL,
      ['private_message', 8, 'receive_notification'] => 0,
      ['aculta_messenger', 8, 'avisos_email_mode_mensagens_privadas'] => 'off',
      default => NULL,
      },
    );
    $userData->expects(self::once())->method('set')
      ->with('aculta_messenger', 4, 'avisos_email_mode_mensagens_privadas', 'immediate');
    $userData->expects(self::exactly(2))->method('delete')->willReturnCallback(
      static function (string $module, int $uid, string $key): void {
        self::assertSame('private_message', $module);
        self::assertContains($uid, [4, 8]);
        self::assertSame('receive_notification', $key);
      },
    );
    $migration = new PrivateMessageEmailPreferenceMigration(
      $this->createMock(ConfigFactoryInterface::class),
      $userData,
      $database,
    );
    $lastUid = 0;

    self::assertTrue($migration->migrateUserBatch($lastUid, 2));
    self::assertSame(8, $lastUid);
  }

  /**
   * Existing Messenger policy wins; missing options adopt central defaults.
   */
  public function testAdministrativeDefaultsPreserveExistingMessengerChoices(): void {
    $legacy = $this->createMock(Config::class);
    $legacy->method('get')->willReturnCallback(static fn(string $key): mixed => match ($key) {
      'enable_notifications' => TRUE,
      'notify_by_default' => FALSE,
      default => NULL,
    });
    $values = [
      'email_modes.mensagens_privadas' => ['immediate' => FALSE, 'default_mode' => 'off'],
      'private_message_digest_anchor' => '2026-11-09',
    ];
    $settings = $this->createMock(Config::class);
    $settings->method('get')->willReturnCallback(fn(string $key): mixed => $values[$key] ?? NULL);
    $settings->expects(self::once())->method('set')
      ->with('email_modes.mensagens_privadas', [
        'immediate' => FALSE,
        'default_mode' => 'off',
        'digest' => TRUE,
      ])->willReturnSelf();
    $settings->expects(self::once())->method('save')->willReturnSelf();
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('private_message.settings')->willReturn($legacy);
    $factory->method('getEditable')->with('aculta_messenger.settings')->willReturn($settings);
    $migration = new PrivateMessageEmailPreferenceMigration(
      $factory,
      $this->createMock(UserDataInterface::class),
      $this->createMock(Connection::class),
    );

    $migration->migrateAdministrativeDefaults();
  }

}
