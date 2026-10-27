<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Account\NicknamePolicy;
use Drupal\aculta_user\Hook\ProfileNicknameValidationHooks;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The legacy Profile form applies the User-owned nickname validation policy.
 */
#[CoversClass(ProfileNicknameValidationHooks::class)]
#[Group('aculta_user')]
final class ProfileNicknameValidationHooksTest extends UnitTestCase {

  /**
   * Adds validation only to role Profile forms that expose the field.
   */
  public function testAddsValidationToRoleProfileForm(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('bundle')->willReturn('authenticated');
    $profile->method('hasField')->with('field_nickname')->willReturn(TRUE);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($profile);
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn($form_object);
    $form = ['field_nickname' => ['widget' => []]];

    $this->hooks()->formAlter($form, $state, 'profile_profile_edit_form');

    self::assertCount(1, $form['#validate']);
    self::assertSame('validateNickname', $form['#validate'][0][1]);
  }

  /**
   * Duplicate values in a role Profile form are rejected by shared policy.
   */
  public function testRejectsDuplicateSubmittedNickname(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('bundle')->willReturn('authenticated');
    $profile->method('getOwnerId')->willReturn(7);
    $original = $this->createMock(FieldItemListInterface::class);
    $original->method('__get')->with('value')->willReturn('OldName');
    $profile->method('get')->with('field_nickname')->willReturn($original);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($profile);
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn($form_object);
    $state->method('getValue')->with('field_nickname')->willReturn([['value' => 'Pirata']]);
    $state->expects(self::once())->method('setErrorByName')->with('field_nickname', self::anything());

    $form = [];
    $this->hooks($this->policy(1))->validateNickname($form, $state);
  }

  /**
   * An unchanged legacy nickname does not block unrelated account edits.
   */
  public function testUnchangedNicknameSkipsLegacyValueValidation(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('bundle')->willReturn('authenticated');
    $original = $this->createMock(FieldItemListInterface::class);
    $original->method('getValue')->willReturn([['value' => 'Old Name']]);
    $original->method('__get')->with('value')->willReturn('Old Name');
    $profile->method('get')->with('field_nickname')->willReturn($original);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($profile);
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn($form_object);
    $state->method('getValue')->with('field_nickname')->willReturn([['value' => 'Old Name']]);
    $state->expects(self::never())->method('setErrorByName');

    $form = [];
    $this->hooks($this->policy(0))->validateNickname($form, $state);
  }

  /**
   * Trims a valid nickname before the Profile widget copies it to the entity.
   */
  public function testTrimsNicknameSubmittedWithWhitespace(): void {
    $profile = $this->createMock(ProfileInterface::class);
    $profile->method('bundle')->willReturn('authenticated');
    $profile->method('getOwnerId')->willReturn(7);
    $original = $this->createMock(FieldItemListInterface::class);
    $original->method('getValue')->willReturn([['value' => 'OldName']]);
    $profile->method('get')->with('field_nickname')->willReturn($original);
    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($profile);
    $state = $this->createMock(FormStateInterface::class);
    $state->method('getFormObject')->willReturn($form_object);
    $state->method('getValue')->with('field_nickname')->willReturn([['value' => '  Pirata  ']]);
    $state->expects(self::once())
      ->method('setValue')
      ->with('field_nickname', [['value' => 'Pirata']]);
    $state->expects(self::never())->method('setErrorByName');

    $form = [];
    $this->hooks($this->policy(0))->validateNickname($form, $state);
  }

  /**
   * Builds the hook with test translation and an optional query result.
   */
  private function hooks(?NicknamePolicy $policy = NULL): ProfileNicknameValidationHooks {
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    return new ProfileNicknameValidationHooks(
      $policy ?? $this->policy(0),
      $this->getStringTranslationStub(),
    );
  }

  /**
   * Builds the policy with a deterministic duplicate lookup result.
   */
  private function policy(int $count): NicknamePolicy {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('count')->willReturnSelf();
    $query->method('execute')->willReturn($count);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('profile')->willReturn($storage);
    return new NicknamePolicy($entities);
  }

}
