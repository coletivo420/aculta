<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Form\AvisosPrivacyForm;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers member email preferences for all shared notice producers.
 */
#[CoversClass(AvisosPrivacyForm::class)]
#[Group('aculta_messenger')]
final class AvisosPrivacyFormTest extends UnitTestCase {

  /**
   * User can select all delivery modes for every integrated origin.
   */
  public function testBuildOffersSameEmailModesForEveryOrigin(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $userData->method('get')->willReturn(AvisoPreferencias::EMAIL_DIGEST);
    $preferences = new AvisoPreferencias($userData);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $formBuilder = new AvisosPrivacyForm($account, $preferences);
    $form = [];

    $formBuilder->build($form);

    $expectedOptions = [
      AvisoPreferencias::EMAIL_OFF,
      AvisoPreferencias::EMAIL_DIGEST,
      AvisoPreferencias::EMAIL_IMMEDIATE,
    ];
    foreach ([
      'email_respostas',
      'email_atualizacoes',
      'email_aprendizagem',
      'email_wiki',
      'email_loja',
      'email_doacao',
    ] as $element) {
      self::assertSame($expectedOptions, array_keys($form['avisos'][$element]['#options']), $element);
    }
    self::assertArrayHasKey('resumo_email', $form['avisos'], 'Digest delivery remains a distinct general opt-in.');
    self::assertArrayHasKey('aviso_sempre', $form['avisos'], 'Site notices remain mandatory.');
  }

  /**
   * Admin policy removes delivery choices from the member form.
   */
  public function testBuildRespectsAdministrativeModePolicy(): void {
    $userData = $this->createMock(UserDataInterface::class);
    $settings = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => [
        'email_modes' => ['wiki' => ['digest' => TRUE, 'immediate' => FALSE]],
      ],
    ]);
    $preferences = new AvisoPreferencias($userData, $settings);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $formBuilder = new AvisosPrivacyForm($account, $preferences);
    $form = [];

    $formBuilder->build($form);

    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST],
      array_keys($form['avisos']['email_wiki']['#options']),
    );
    self::assertContains(AvisoPreferencias::EMAIL_OFF, array_keys($form['avisos']['email_doacao']['#options']));
  }

  /**
   * Saves per-origin modes while preserving mandatory site notices.
   */
  public function testSubmitSavesModesForAllIntegratedOrigins(): void {
    $saved = [];
    $userData = $this->createMock(UserDataInterface::class);
    $userData->expects(self::exactly(9))
      ->method('set')
      ->willReturnCallback(static function (string $module, int $uid, string $key, mixed $value) use (&$saved): void {
        self::assertSame(AvisoPreferencias::MODULO, $module);
        self::assertSame(27, $uid);
        $saved[$key] = $value;
      });
    $preferences = new AvisoPreferencias($userData);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(27);
    $formBuilder = new AvisosPrivacyForm($account, $preferences);
    $formState = new FormState();
    $formState->setValue('resumo_email', FALSE);
    $formState->setValue('email_respostas', AvisoPreferencias::EMAIL_DIGEST);
    $formState->setValue('email_atualizacoes', AvisoPreferencias::EMAIL_OFF);
    $formState->setValue('email_aprendizagem', AvisoPreferencias::EMAIL_IMMEDIATE);
    $formState->setValue('email_wiki', AvisoPreferencias::EMAIL_OFF);
    $formState->setValue('email_loja', AvisoPreferencias::EMAIL_DIGEST);
    $formState->setValue('email_doacao', AvisoPreferencias::EMAIL_IMMEDIATE);
    $form = [];

    $formBuilder->submit($form, $formState);

    self::assertSame([
      'avisos_resumo_email' => 0,
      'avisos_resumo_forum_resposta' => 1,
      'avisos_resumo_forum_atualizacao' => 0,
      'avisos_email_mode_forum_resposta' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_forum_atualizacao' => AvisoPreferencias::EMAIL_OFF,
      'avisos_email_mode_aprendizagem' => AvisoPreferencias::EMAIL_IMMEDIATE,
      'avisos_email_mode_wiki' => AvisoPreferencias::EMAIL_OFF,
      'avisos_email_mode_loja' => AvisoPreferencias::EMAIL_DIGEST,
      'avisos_email_mode_doacao' => AvisoPreferencias::EMAIL_IMMEDIATE,
    ], $saved);
  }

}
