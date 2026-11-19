<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Form\AccountPresentationSettingsForm;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Account presentation configuration form contract.
 */
#[CoversClass(AccountPresentationSettingsForm::class)]
#[Group('aculta_studio')]
final class AccountPresentationSettingsFormTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * The editable fields target only the presentation config object.
   */
  public function testFieldsUseExportableConfigTargetsAndSafeDefaults(): void {
    $config = $this->createMock(Config::class);
    $config->method('get')->willReturn(NULL);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('getEditable')
      ->with('aculta_studio.account_presentation')
      ->willReturn($config);
    $typed_config_manager = $this->createMock(TypedConfigManagerInterface::class);

    $form_object = new AccountPresentationSettingsForm($config_factory, $typed_config_manager);
    $form = $form_object->buildForm([], new FormState());

    self::assertSame(
      'aculta_studio.account_presentation:experiences_title',
      $this->formElement($form, 'experiences_title')['#config_target'],
    );
    self::assertSame(
      'Suas experiências',
      $this->formElement($form, 'experiences_title')['#default_value'],
    );
    self::assertSame(
      'aculta_studio.account_presentation:experiences_description',
      $this->formElement($form, 'experiences_description')['#config_target'],
    );
    self::assertSame(
      'aculta_studio.account_presentation:account_link_title',
      $this->formElement($form, 'account_link_title')['#config_target'],
    );
    self::assertSame(
      'aculta_studio.account_presentation:account_link_description',
      $this->formElement($form, 'account_link_description')['#config_target'],
    );
    self::assertSame(
      'aculta_studio.account_presentation:account_link_label',
      $this->formElement($form, 'account_link_label')['#config_target'],
    );
  }

  /**
   * Returns one rendered field element after validating its structure.
   *
   * @param array<array-key, mixed> $form
   *   The built form.
   * @param string $key
   *   Element key.
   *
   * @return array<array-key, mixed>
   *   The form element.
   */
  private function formElement(array $form, string $key): array {
    $element = $form[$key] ?? NULL;
    self::assertIsArray($element);
    return $element;
  }

}
