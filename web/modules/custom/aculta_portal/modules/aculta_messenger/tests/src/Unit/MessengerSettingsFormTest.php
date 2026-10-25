<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Form\MessengerSettingsForm;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\FormState;
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

}
