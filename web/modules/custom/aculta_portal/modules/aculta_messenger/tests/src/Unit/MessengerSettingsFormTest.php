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
  }

}
