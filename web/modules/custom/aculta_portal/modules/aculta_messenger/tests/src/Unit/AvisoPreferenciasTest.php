<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Forum channel preferences and category email settings. */
#[CoversClass(AvisoPreferencias::class)]
#[Group('aculta_messenger')]
final class AvisoPreferenciasTest extends UnitTestCase {

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
  #[DataProvider('administrativePolicyCases')]
  public function testAdministrativePolicyRestrictsEffectiveEmailMode(
    string $category,
    array $configuredModes,
    string $savedMode,
    array $allowedModes,
  ): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_email_mode_' . $category => $savedMode,
      default => NULL,
    });
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => ['email_modes' => [$category => $configuredModes]],
    ]);
    $preferences = new AvisoPreferencias($userData, $configFactory);

    self::assertSame($allowedModes, $preferences->allowedEmailModes($category));
    self::assertSame(AvisoPreferencias::EMAIL_OFF, $preferences->emailMode(27, $category));
    $this->expectException(\InvalidArgumentException::class);
    $preferences->saveEmailMode(27, $category, $savedMode);
  }

  /**
   * Builds both administrator mode restrictions for every shared origin.
   *
   * @return array<string, array{string, array<string, bool>, string, list<string>}>
   *   Category, site policy, saved member mode, and allowed modes.
   */
  public static function administrativePolicyCases(): array {
    $cases = [];
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $immediateOnly = in_array($category, AvisoPreferencias::EMAIL_IMMEDIATE_ONLY_CATEGORIES, TRUE);
      $cases[$category . ' disallows direct delivery'] = [
        $category,
        ['digest' => TRUE, 'immediate' => FALSE],
        AvisoPreferencias::EMAIL_IMMEDIATE,
        $immediateOnly
          ? [AvisoPreferencias::EMAIL_OFF]
          : [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST],
      ];
      if (!$immediateOnly) {
        $cases[$category . ' disallows digest'] = [
          $category,
          ['digest' => FALSE, 'immediate' => TRUE],
          AvisoPreferencias::EMAIL_DIGEST,
          [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_IMMEDIATE],
        ];
      }
    }
    return $cases;
  }

  /**
   * A missing admin setting allows every supported email mode. */
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
   * Explicit category selection enables the selected category.
   */
  public function testExplicitDigestModeAppliesToItsCategory(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturnCallback(static fn(string $module, int $uid, string $key): mixed => match ($key) {
      'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_DIGEST,
      default => NULL,
    });
    $preferences = new AvisoPreferencias($userData);

    self::assertSame(AvisoPreferencias::EMAIL_DIGEST, $preferences->emailMode(27, 'wiki'));
  }

  /**
   * Missing category choice never enables email.
   */
  public function testMissingCategoryPreferenceDefaultsToSiteOnly(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::once())
      ->method('get')
      ->with(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_wiki')
      ->willReturn(NULL);
    $preferences = new AvisoPreferencias($userData);

    self::assertSame(AvisoPreferencias::EMAIL_OFF, $preferences->emailMode(27, 'wiki'));
  }

  /**
   * Honors the administrator's weekly digest default for new preferences.
   */
  public function testPrivateMessageDigestCanBeTheAdministrativeDefault(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::once())->method('get')
      ->with(AvisoPreferencias::MODULO, 27, 'avisos_email_mode_' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES)
      ->willReturn(NULL);
    $preferences = new AvisoPreferencias(
      $userData,
      $this->getConfigFactoryStub([
        'aculta_messenger.settings' => [
          'email_modes' => [
            AvisoPreferencias::EMAIL_PRIVATE_MESSAGES => [
              'digest' => TRUE,
              'immediate' => TRUE,
              'default_mode' => AvisoPreferencias::EMAIL_DIGEST,
            ],
          ],
        ],
      ]),
    );

    self::assertSame(
      AvisoPreferencias::EMAIL_DIGEST,
      $preferences->emailMode(27, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES),
    );
  }

  /**
   * Malformed explicit administration policy fails closed for e-mail modes.
   */
  public function testMalformedAdministrativePolicyDisallowsEmailModes(): void {
    $preferences = new AvisoPreferencias(
      $this->createMock(UserDataInterface::class),
      $this->getConfigFactoryStub([
        'aculta_messenger.settings' => [
          'email_modes' => [
            'wiki' => 'invalid',
            'loja' => ['digest' => 'false', 'immediate' => 1],
          ],
        ],
      ]),
    );

    self::assertSame([AvisoPreferencias::EMAIL_OFF], $preferences->allowedEmailModes('wiki'));
    self::assertSame([AvisoPreferencias::EMAIL_OFF], $preferences->allowedEmailModes('loja'));
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
