<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Kernel;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\SharedEmailPreferenceMigration;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the preference migration against Drupal's real user-data storage. */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class SharedEmailPreferenceMigrationKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc} */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);
  }

  /**
   * Copies legacy choices, retains new modes, and deletes source rows. */
  public function testDatabaseBatchMigration(): void {
    $userData = $this->container->get('user.data');
    $userData->set(AvisoPreferencias::MODULO, 27, 'avisos_resumo_email', TRUE);
    $userData->set(AvisoPreferencias::MODULO, 27, 'avisos_resumo_forum_resposta', FALSE);
    $userData->set(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_wiki', AvisoPreferencias::EMAIL_IMMEDIATE);
    $migration = new SharedEmailPreferenceMigration($userData, $this->container->get('database'));
    $lastUid = 0;

    self::assertSame(1, $migration->migrateBatch($lastUid, 1));
    self::assertSame(27, $lastUid);
    self::assertSame(AvisoPreferencias::EMAIL_OFF, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_forum_resposta'));
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_forum_atualizacao'));
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_aprendizagem'));
    self::assertSame(AvisoPreferencias::EMAIL_IMMEDIATE, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_wiki'));
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_loja'));
    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $userData->get(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_doacao'));
    self::assertNull($userData->get(AvisoPreferencias::MODULO, 27, 'avisos_resumo_email'));
    self::assertNull($userData->get(AvisoPreferencias::MODULO, 27, 'avisos_resumo_forum_resposta'));
    self::assertNull($userData->get(AvisoPreferencias::MODULO, 27, 'avisos_resumo_forum_atualizacao'));
    self::assertSame(0, $migration->migrateBatch($lastUid, 1));
  }

}
