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

    $build = self::assertArray($form->buildForm([], new FormState()));
    $emailModes = self::arrayAt($build, 'email_modes');
    $magazineEvents = self::arrayAt($build, 'magazine_events');

    self::assertSame(
      [AvisoPreferencias::EMAIL_IMMEDIATE],
      self::valueAt(self::arrayAt($emailModes, 'forum_resposta'), '#default_value'),
    );
    self::assertSame(
      [AvisoPreferencias::EMAIL_DIGEST],
      self::valueAt(self::arrayAt($emailModes, 'wiki'), '#default_value'),
    );
    self::assertArrayHasKey('doacao', $emailModes);
    self::assertTrue(self::arrayAt($magazineEvents, 'published')['#default_value']);
    self::assertTrue(self::arrayAt($magazineEvents, 'updated')['#default_value']);
    self::assertTrue(self::arrayAt($magazineEvents, 'review_submitted')['#default_value']);
    self::assertTrue(self::arrayAt($magazineEvents, 'review_returned')['#default_value']);
    foreach ([AvisoPreferencias::EMAIL_PRIVATE_MESSAGES, AvisoPreferencias::EMAIL_FRIENDSHIP] as $category) {
      $expectedOptions = $category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES
        ? [AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE]
        : [AvisoPreferencias::EMAIL_IMMEDIATE];
      self::assertSame($category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES
        ? [AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE]
        : [AvisoPreferencias::EMAIL_IMMEDIATE], self::arrayAt($emailModes, $category)['#default_value']);
      self::assertSame($expectedOptions, array_keys(self::arrayAt(self::arrayAt($emailModes, $category), '#options')));
    }
    foreach (AvisoPreferencias::EMAIL_CATEGORIES as $category) {
      $defaultMode = self::arrayAt($emailModes, $category . '_default_mode');
      self::assertSame('select', $defaultMode['#type']);
      self::assertSame(
        $category === AvisoPreferencias::EMAIL_PRIVATE_MESSAGES || $category === AvisoPreferencias::EMAIL_FRIENDSHIP
          ? AvisoPreferencias::EMAIL_IMMEDIATE
          : AvisoPreferencias::EMAIL_OFF,
        $defaultMode['#default_value'],
        $category,
      );
      if ($category === AvisoPreferencias::EMAIL_FRIENDSHIP) {
        self::assertArrayNotHasKey(AvisoPreferencias::EMAIL_DIGEST, self::arrayAt($defaultMode, '#options'));
      }
    }
    self::assertSame(
      AvisoPreferencias::EMAIL_IMMEDIATE,
      self::valueAt(self::arrayAt($emailModes, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES . '_default_mode'), '#default_value'),
    );
    self::assertSame(
      [AvisoPreferencias::EMAIL_OFF, AvisoPreferencias::EMAIL_DIGEST, AvisoPreferencias::EMAIL_IMMEDIATE],
      array_keys(self::arrayAt(self::arrayAt($emailModes, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES . '_default_mode'), '#options')),
    );
    self::assertTrue(self::valueAt(self::arrayAt($emailModes, 'private_message_digest_anchor'), '#required'));
  }

  /**
   * Malformed policy values are not presented as enabled modes.
   */
  public function testBuildFailsClosedForMalformedEmailModeValues(): void {
    $configFactory = $this->getConfigFactoryStub([
      'aculta_messenger.settings' => [
        'group_creation_enabled' => TRUE,
        'group_max_members' => 10,
        'retention_months' => 6,
        'cleanup_batch' => 20,
        'send_limit_per_hour' => 10,
        'email_modes' => [
          'wiki' => ['digest' => 'false', 'immediate' => '0'],
          'loja' => ['digest' => 0, 'immediate' => 'true'],
          'doacao' => 'invalid',
        ],
      ],
    ]);
    $form = new MessengerSettingsForm($configFactory, $this->createMock(TypedConfigManagerInterface::class));
    $form->setStringTranslation($this->getStringTranslationStub());

    $build = self::assertArray($form->buildForm([], new FormState()));
    $emailModes = self::arrayAt($build, 'email_modes');

    self::assertSame([], self::valueAt(self::arrayAt($emailModes, 'wiki'), '#default_value'));
    self::assertSame([], self::valueAt(self::arrayAt($emailModes, 'loja'), '#default_value'));
    self::assertSame([], self::valueAt(self::arrayAt($emailModes, 'doacao'), '#default_value'));
    self::assertSame([AvisoPreferencias::EMAIL_OFF], array_keys(self::arrayAt(self::arrayAt($emailModes, 'doacao_default_mode'), '#options')));
  }

  /**
   * Administrative policy changes persist without disabling site notices.
   */
  public function testSubmitPersistsEmailModesAndWeeklyDigestAnchor(): void {
    $config = $this->createMock(Config::class);
    $saved = [];
    $config->expects(self::exactly(10))
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
    $emailModes['forum_resposta_default_mode'] = AvisoPreferencias::EMAIL_IMMEDIATE;
    $emailModes['wiki'] = [AvisoPreferencias::EMAIL_DIGEST => AvisoPreferencias::EMAIL_DIGEST];
    $emailModes['wiki_default_mode'] = AvisoPreferencias::EMAIL_DIGEST;
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
      'magazine_events' => [
        'published' => TRUE,
        'updated' => FALSE,
        'review_submitted' => TRUE,
        'review_returned' => FALSE,
      ],
    ]);

    $formArray = [];
    $form->submitForm($formArray, $state);

    self::assertSame('2026-11-02', $saved['private_message_digest_anchor']);
    $savedEmailModes = self::arrayAt($saved, 'email_modes');
    self::assertSame([
      'published' => TRUE,
      'updated' => FALSE,
      'review_submitted' => TRUE,
      'review_returned' => FALSE,
    ], $saved['magazine_events']);
    self::assertSame([
      'digest' => FALSE,
      'immediate' => TRUE,
      'default_mode' => AvisoPreferencias::EMAIL_IMMEDIATE,
    ], self::arrayAt($savedEmailModes, 'forum_resposta'));
    self::assertSame([
      'digest' => TRUE,
      'immediate' => FALSE,
      'default_mode' => AvisoPreferencias::EMAIL_OFF,
    ], self::arrayAt($savedEmailModes, AvisoPreferencias::EMAIL_PRIVATE_MESSAGES));
    self::assertSame([
      'digest' => TRUE,
      'immediate' => FALSE,
      'default_mode' => AvisoPreferencias::EMAIL_DIGEST,
    ], self::arrayAt($savedEmailModes, 'wiki'));
  }

  /**
   * Validates an untyped Form API result before its fields are inspected.
   *
   * @return array<mixed>
   *   The form render array.
   */
  private static function assertArray(mixed $value): array {
    self::assertIsArray($value);
    return $value;
  }

  /**
   * Returns a nested form array after validating its runtime type.
   *
   * @param array<mixed> $parent
   *   The form or render element containing the requested child.
   *
   * @return array<mixed>
   *   The requested form or render element.
   */
  private static function arrayAt(array $parent, string $key): array {
    self::assertArrayHasKey($key, $parent);
    self::assertIsArray($parent[$key]);
    return $parent[$key];
  }

  /**
   * Returns a present value from a validated form array.
   *
   * @param array<mixed> $parent
   *   The render element containing the value.
   */
  private static function valueAt(array $parent, string $key): mixed {
    self::assertArrayHasKey($key, $parent);
    return $parent[$key];
  }

}
