<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Form\MessengerSettingsForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests administrative messaging and email mode settings.
 */
#[CoversClass(MessengerSettingsForm::class)]
#[Group('aculta_messenger')]
final class MessengerSettingsFormTest extends UnitTestCase {

  /**
   * Existing policy values are shown as selected modes in the admin form.
   */
  public function testBuildShowsPerCategoryEmailPolicy(): void {
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => [
        'group_creation_enabled' => TRUE,
        'group_max_members' => 10,
        'retention_months' => 6,
        'cleanup_batch' => 20,
        'send_limit_per_hour' => 10,
        'email_modes' => [
          'forum_resposta' => ['digest' => FALSE, 'immediate' => TRUE],
          'wiki' => ['digest' => TRUE, 'immediate' => FALSE],
        ],
      ],
    ]);
    $form = new MessengerSettingsForm($configFactory, $this->createMock(TypedConfigManagerInterface::class));
    $form->setStringTranslation($this->getStringTranslationStub());

    $build = $form->buildForm([], new FormState());

    self::assertSame(
      [AvisoPreferencias::EMAIL_IMMEDIATE],
      $build['email_modes']['forum_resposta']['#default_value'],
    );
    self::assertSame(
      [AvisoPreferencias::EMAIL_DIGEST],
      $build['email_modes']['wiki']['#default_value'],
    );
    self::assertArrayHasKey('doacao', $build['email_modes']);
    foreach ([AvisoPreferencias::EMAIL_PRIVATE_MESSAGES, AvisoPreferencias::EMAIL_FRIENDSHIP] as $category) {
      $expectedOptions = $category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES
        ? [AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE]
        : [AvisoPreferencias::EMAIL_IMMEDIATE];
      self::assertSame($category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES
        ? [AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE]
        : [AvisoPreferencias::EMAIL_IMMEDIATE], $build['email_modes'][$category]['#default_value']);
      self::assertSame($expectedOptions, array_keys($build['email_modes'][$category]['#options']));
    }
    self::assertSame(
      AvisoPreferencias::EMAIL_IMMEDIATE,
      $build['email_modes'][AvisoPreferencias::EMAIL_PRIVATE_MESSAGES . '_default_mode']['#default_value'],
    );
    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE],
      array_keys($build['email_modes'][AvisoPreferencias::EMAIL_PRIVATE_MESSAGES . '_default_mode']['#options']),
    );
  }

  /**
   * Administrative policy changes persist without disabling site notices.
   */
  public function testSubmitPersistsEmailModesAndWeeklyDigestAnchor(): void {
    $config = $this->createMock(Config::class);
    $saved = [];
    $config->expects(self::exactly(7))
      ->method('set')
      ->willReturnCallback(static function (string $key, mixed $value) use (&$saved, $config): Config {
        $saved[$key] = $value;
        return $config;
      });
    $config->expects(self::once())->method('save');
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->expects(self::once())
      ->method('getEditable')
      ->with('aculta_messenger.settings')
      ->willReturn($config);
    $form = new MessengerSettingsForm($factory, $this->createMock(TypedConfigManagerInterface::class));
    $form->setStringTranslation($this->getStringTranslationStub());
    $form->setMessenger($this->createMock(MessengerInterface::class));

    $emailModes = [];
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $emailModes[$category] = [];
    }
    $emailModes['forum_resposta'] = [AvisoPreferencias::EMAIL_IMMEDIATE => AvisoPreferencias::EMAIL_IMMEDIATE];
    $emailModes[AvisoPreferencias::EMAIL_PRIVATE_MESSAGES] = [
      AvisoPreferencias::EMAIL_DIGEST => AvisoPreferencias::EMAIL_DIGEST,
    ];
    $emailModes[AvisoPreferencias::EMAIL_PRIVATE_MESSAGES . '_default_mode'] = AvisoPreferencias::EMAIL_IMMEDIATE;
    $emailModes['private_message_digest_anchor'] = '2026-11-02';
    $state = new FormState();
    $state->setValues([
      'group_creation_enabled' => TRUE,
      'group_max_members' => 10,
      'retention_months' => 6,
      'cleanup_batch' => 20,
      'send_limit_per_hour' => 10,
      'email_modes' => $emailModes,
    ]);

    $formArray = [];
    $form->submitForm($formArray, $state);

    self::assertSame('2026-11-02', $saved['private_message_digest_anchor']);
    self::assertSame([
      'digest' => FALSE,
      'immediate' => TRUE,
    ], $saved['email_modes']['forum_resposta']);
    self::assertSame([
      'digest' => TRUE,
      'immediate' => FALSE,
      'default_mode' => AvisoPreferencias::EMAIL_OFF,
    ], $saved['email_modes'][AvisoPreferencias::EMAIL_PRIVATE_MESSAGES]);
    self::assertSame(FALSE, $saved['email_modes']['wiki']['digest']);
  }

}
