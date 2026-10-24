<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Forum channel preferences and legacy settings compatibility. */
#[CoversClass(AvisoPreferencias::class)]
#[Group('aculta_messenger')]
final class AvisoPreferenciasTest extends UnitTestCase {

  /**
   * Preserves a prior digest opt-out until a new mode is selected. */
  public function testLegacyForumDigestOptOutBecomesEmailOff(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_resumo_forum_resposta' => 0,
      default => NULL,
    });
    $preferences = new AvisoPreferencias($userData);

    self::assertSame(AvisoPreferencias::EMAIL_OFF, $preferences->emailMode(27, Aviso::TIPO_RESPOSTA));
    self::assertFalse($preferences->tipoNoResumo(27, Aviso::TIPO_RESPOSTA));
  }

  /**
   * Includes a Forum event in digest only when its current mode is digest. */
  public function testForumDigestSelectionFollowsCurrentChannelMode(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_email_mode_forum_atualizacao' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_forum_resposta' => AvisoPreferencias::EMAIL_IMMEDIATE,
      default => NULL,
    });
    $preferences = new AvisoPreferencias($userData);

    self::assertTrue($preferences->tipoNoResumo(27, Aviso::TIPO_ATUALIZACAO));
    self::assertFalse($preferences->tipoNoResumo(27, Aviso::TIPO_RESPOSTA));
  }

  /**
   * An administrator restriction suppresses a disallowed saved user mode. */
  public function testAdministrativePolicyRestrictsEffectiveEmailMode(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_IMMEDIATE,
      default => NULL,
    });
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => ['email_modes' => ['wiki' => ['digest' => TRUE, 'immediate' => FALSE]]],
    ]);
    $preferences = new AvisoPreferencias($userData, $configFactory);

    self::assertSame([AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST], $preferences->allowedEmailModes('wiki'));
    self::assertSame(AvisoPreferencias::EMAIL_OFF, $preferences->emailMode(27, 'wiki'));
    $this->expectException(\InvalidArgumentException::class);
    $preferences->saveEmailMode(27, 'wiki', AvisoPreferencias::EMAIL_IMMEDIATE);
  }

  /**
   * A missing admin setting remains compatible with existing opt-in behavior. */
  public function testMissingAdministrativePolicyAllowsAllModes(): void {
    $preferences = new AvisoPreferencias(
      $this->createMock(UserDataInterface::class),
      $this->getConfigFactoryStub(['aculta_messenger.settings' => []]),
    );

    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE],
      $preferences->allowedEmailModes('doacao'),
    );
  }

  /**
   * Unknown notice categories fail closed and cannot persist email modes.
   */
  public function testUnknownCategoryCannotEnableEmail(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::never())->method('get');
    $userData->expects(self::never())->method('set');
    $preferences = new AvisoPreferencias($userData);

    self::assertSame([AvisoPreferencias::EMAIL_OFF], $preferences->allowedEmailModes('future_module'));
    self::assertSame(AvisoPreferencias::EMAIL_OFF, $preferences->emailMode(27, 'future_module'));
    $this->expectException(\InvalidArgumentException::class);
    $preferences->saveEmailMode(27, 'future_module', AvisoPreferencias::EMAIL_DIGEST);
  }

}
