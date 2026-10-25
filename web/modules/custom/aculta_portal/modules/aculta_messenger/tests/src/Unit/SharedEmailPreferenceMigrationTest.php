<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\SharedEmailPreferenceMigration;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\StatementInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests migration from legacy global notice email preferences. */
#[CoversClass(SharedEmailPreferenceMigration::class)]
#[Group('aculta_messenger')]
final class SharedEmailPreferenceMigrationTest extends UnitTestCase {

  /**
   * Migrates the global digest choice, preserves new choices, then deletes legacy keys. */
  public function testMigratesBatchAndRemovesLegacyPreferences(): void {
    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchCol')->willReturn([27]);
    $query = $this->createMock(SelectInterface::class);
    $query->method('fields')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('distinct')->willReturnSelf();
    $query->method('orderBy')->willReturnSelf();
    $query->method('range')->with(0, 500)->willReturnSelf();
    $query->method('execute')->willReturn($statement);
    $database = $this->createMock(Connection::class);
    $database->method('select')->with('users_data', 'ud')->willReturn($query);

    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_resumo_email' => TRUE,
      'avisos_resumo_forum_resposta' => FALSE,
      'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_IMMEDIATE,
      default => NULL,
    });
    $saved = [];
    $userData->expects(self::exactly(5))->method('set')->willReturnCallback(
      static function (string $module, int $uid, string $key, mixed $value) use (&$saved): void {
        self::assertSame(AvisoPreferencias::MODULO, $module);
        self::assertSame(27, $uid);
        $saved[$key] = $value;
      },
    );
    $deleted = [];
    $userData->expects(self::exactly(3))->method('delete')->willReturnCallback(
      static function (string $module, int $uid, string $key) use (&$deleted): void {
        self::assertSame(AvisoPreferencias::MODULO, $module);
        self::assertSame(27, $uid);
        $deleted[] = $key;
      },
    );

    $migration = new SharedEmailPreferenceMigration($userData, $database);
    $lastUid = 0;

    self::assertSame(1, $migration->migrateBatch($lastUid));
    self::assertSame(27, $lastUid);
    self::assertSame([
      'avisos_email_mode_forum_resposta' => AvisoPreferencias::EMAIL_OFF,
      'avisos_email_mode_forum_atualizacao' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_aprendizagem' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_doacao' => AvisoPreferencias::EMAIL_DIGEST,
    ], $saved);
    self::assertSame([
      'avisos_resumo_email',
      'avisos_resumo_forum_resposta',
      'avisos_resumo_forum_atualizacao',
    ], $deleted);
  }

}
