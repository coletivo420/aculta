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

}
