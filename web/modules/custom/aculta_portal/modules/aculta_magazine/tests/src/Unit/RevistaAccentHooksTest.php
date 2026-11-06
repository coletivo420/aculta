<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Hook\RevistaAccentHooks;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ui_styles\Definition\StyleDefinition;
use Drupal\ui_styles\Element\Styles as UiStylesStyles;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\ui_styles\StylePluginManagerInterface;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\aculta_magazine\Hook\RevistaAccentHooks */
final class RevistaAccentHooksTest extends UnitTestCase {

  /** @covers ::removeExtraClasses */
  public function testWidgetCannotSubmitFreeformClasses(): void {
    $element = ['wrapper' => ['_ui_styles_extra' => ['#type' => 'textfield'], 'ui_styles_revista_accent' => []]];
    $result = RevistaAccentHooks::removeExtraClasses($element, new FormState());
    self::assertArrayNotHasKey('_ui_styles_extra', $result['wrapper']);
    self::assertArrayHasKey('ui_styles_revista_accent', $result['wrapper']);
  }

  /** @covers ::alterForm */
  public function testUiStylesWidgetPreservesNestedInputValues(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('hasField')->with('field_revista_accent')->willReturn(TRUE);
    $node->method('get')->with('field_revista_accent')->willReturn((object) ['value' => NULL]);

    $formObject = $this->createMock(EntityForm::class);
    $formObject->method('getEntity')->willReturn($node);
    $formState = $this->createMock(FormStateInterface::class);
    $formState->method('getFormObject')->willReturn($formObject);

    $definition = new StyleDefinition([
      'provider' => 'test_theme',
      'options' => ['revista-teaser--accent-red' => 'Vermelha'],
    ]);
    $manager = $this->createMock(StylePluginManagerInterface::class);
    $manager->expects(self::exactly(2))
      ->method('getDefinition')
      ->with('revista_accent', FALSE)
      ->willReturn($definition);
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->with('administer nodes')->willReturn(TRUE);

    $form = [];
    (new RevistaAccentHooks($manager, $user))->alterForm($form, $formState, 'node_revista_edit_form');

    self::assertTrue($form['revista_accent_styles']['#tree']);
  }

  /** @covers ::applyAccent */
  public function testAllowedAccentIsAppliedThroughUiStyles(): void {
    $accent = 'accent-' . 'yellow';
    $field = (object) ['value' => $accent];
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('hasField')->with('field_revista_accent')->willReturn(TRUE);
    $node->method('get')->with('field_revista_accent')->willReturn($field);
    $manager = $this->createMock(StylePluginManagerInterface::class);
    $manager->method('getDefinition')->with('revista_accent', FALSE)->willReturn(new StyleDefinition(['options' => [$accent => 'Amarela']]));
    $manager->expects(self::once())
      ->method('addClasses')
      ->with(['#attributes' => []], ['revista_accent' => $accent])
      ->willReturn(['#attributes' => ['class' => [$accent]]]);
    $hook = new RevistaAccentHooks($manager, $this->createMock(\Drupal\Core\Session\AccountProxyInterface::class));
    $build = [];
    $hook->applyAccent($build, $node, $this->createMock(EntityViewDisplayInterface::class), 'teaser');
    self::assertSame([$accent], $build['#attributes']['class']);
  }

  /** @covers ::applyAccent */
  public function testInvalidAccentIsIgnored(): void {
    $field = (object) ['value' => 'custom-' . 'class'];
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('hasField')->with('field_revista_accent')->willReturn(TRUE);
    $node->method('get')->with('field_revista_accent')->willReturn($field);
    $manager = $this->createMock(StylePluginManagerInterface::class);
    $manager->method('getDefinition')->with('revista_accent', FALSE)->willReturn(new StyleDefinition(['options' => ['accent-green' => 'Verde']]));
    $manager->expects(self::never())->method('addClasses');
    $hook = new RevistaAccentHooks($manager, $this->createMock(\Drupal\Core\Session\AccountProxyInterface::class));
    $build = [];
    $hook->applyAccent($build, $node, $this->createMock(EntityViewDisplayInterface::class), 'teaser');
    self::assertSame([], $build);
  }

  /** @covers ::buildEntity */
  public function testEntityBuilderStoresOnlyPluginOptions(): void {
    $accent = 'accent-' . 'red';
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('hasField')->with('field_revista_accent')->willReturn(TRUE);
    $node->expects(self::once())->method('set')->with('field_revista_accent', $accent);
    $manager = $this->createMock(StylePluginManagerInterface::class);
    $manager->method('getDefinition')->with('revista_accent', FALSE)->willReturn(new StyleDefinition(['options' => [$accent => 'Vermelha']]));
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->with('administer nodes')->willReturn(TRUE);
    $hook = new RevistaAccentHooks($manager, $user);
    $state = new FormState();
    $widgetElement = ['#default_value' => ['selected' => [], 'extra' => '']];
    $widgetValue = UiStylesStyles::valueCallback(
      $widgetElement,
      ['wrapper' => ['revista' => ['ui_styles_revista_accent' => $accent], '_ui_styles_extra' => 'forged-class']],
      $state,
    );
    $state->setValue('revista_accent_styles', $widgetValue);
    $form = [];
    $hook->buildEntity('node', $node, $form, $state);
  }

  /** @covers ::buildEntity */
  public function testEntityBuilderRejectsForgedAccent(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('revista');
    $node->method('hasField')->with('field_revista_accent')->willReturn(TRUE);
    $node->expects(self::once())->method('set')->with('field_revista_accent', NULL);
    $manager = $this->createMock(StylePluginManagerInterface::class);
    $manager->method('getDefinition')->with('revista_accent', FALSE)->willReturn(new StyleDefinition(['options' => ['accent-green' => 'Verde']]));
    $user = $this->createMock(AccountProxyInterface::class);
    $user->method('hasPermission')->with('administer nodes')->willReturn(TRUE);
    $hook = new RevistaAccentHooks($manager, $user);
    $state = new FormState();
    $state->setValue('revista_accent_styles', ['selected' => ['revista_accent' => 'outside-list'], 'extra' => '']);
    $form = [];
    $hook->buildEntity('node', $node, $form, $state);
  }

  /** @covers ::fieldAccess */
  public function testOnlyAdministratorsCanEditAccentField(): void {
    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getName')->willReturn('field_revista_accent');
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('administer nodes')->willReturn(FALSE);
    $hook = new RevistaAccentHooks(
      $this->createMock(StylePluginManagerInterface::class),
      $this->createMock(AccountProxyInterface::class),
    );
    $access = $hook->fieldAccess('edit', $definition, $account);
    self::assertTrue($access->isForbidden());
    self::assertContains('user.permissions', $access->getCacheContexts());
  }

}
