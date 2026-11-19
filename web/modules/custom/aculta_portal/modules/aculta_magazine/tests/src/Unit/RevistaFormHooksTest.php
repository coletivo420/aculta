<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Hook\RevistaFormHooks;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the grouping of secondary properties in the publication form.
 */
#[Group('aculta_magazine')]
final class RevistaFormHooksTest extends UnitTestCase {

  /**
   * Clears the static FormState error flag so it does not leak to other tests.
   */
  protected function tearDown(): void {
    (new FormState())->clearErrors();
    parent::tearDown();
  }

  /**
   * Builds a minimal publication form with the secondary fields of the bundle.
   *
   * @return array<string, mixed>
   *   Minimal Form API form.
   */
  private function form(): array {
    return [
      'title' => [
        '#type' => 'textfield',
        '#title' => 'Título',
      ],
      'field_tags' => [
        '#type' => 'container',
        '#access' => TRUE,
        'target_id' => [
          '#type' => 'entity_autocomplete',
          '#attributes' => [],
        ],
      ],
      'field_meta_tags' => [
        '#type' => 'details',
        '#access' => TRUE,
        'meta' => [
          '#type' => 'textfield',
          '#attributes' => [],
        ],
        '#attributes' => [],
      ],
    ];
  }

  /**
   * The secondary fields move into a closed group; unrelated forms are ignored.
   */
  public function testSecondaryFieldsAreGroupedAndOtherFormsAreIgnored(): void {
    $hooks = new RevistaFormHooks();

    $form = $this->form();
    $form_state = new FormState();
    $hooks->alter($form, $form_state, 'node_revista_edit_form');
    self::assertArrayHasKey('revista_secondary', $form);
    self::assertArrayNotHasKey('field_tags', $form);
    self::assertArrayNotHasKey('field_meta_tags', $form);
    self::assertArrayHasKey('title', $form);
    self::assertFalse(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertContains('aculta_magazine/revista-form', self::arrayAt($form, '#attached', 'library'));
    self::assertFalse($this->hasAutofocus($form));

    $form = $this->form();
    $form_state = new FormState();
    $hooks->alter($form, $form_state, 'node_revista_delete_form');
    self::assertArrayNotHasKey('revista_secondary', $form);
    self::assertArrayHasKey('field_tags', $form);
    self::assertArrayNotHasKey('#attached', $form);
  }

  /**
   * A single secondary field still produces a group.
   */
  public function testGroupIsCreatedWhenOnlyOneFieldMoves(): void {
    $form = ['field_credits' => ['#type' => 'container']];
    $form_state = new FormState();
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_form');

    self::assertArrayHasKey('revista_secondary', $form);
    self::assertArrayNotHasKey('field_credits', $form);
    self::assertArrayHasKey('field_credits', self::arrayAt($form, 'revista_secondary'));
  }

  /**
   * A form without any secondary field does not render an empty group.
   */
  public function testEmptyGroupIsNotCreated(): void {
    $form = ['title' => ['#type' => 'textfield']];
    $form_state = new FormState();
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_form');

    self::assertArrayNotHasKey('revista_secondary', $form);
  }

  /**
   * An error under the group opens it and focuses the invalid field.
   */
  public function testErrorInGroupOpensItAndTakesTheFocus(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('revista_secondary][field_tags', 'Etiqueta inválida.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertSame(
      'autofocus',
      self::optionalValueAt($form, 'revista_secondary', 'field_tags', 'target_id', '#attributes', 'autofocus'),
    );
    self::assertNull(self::optionalValueAt($form, 'title', '#attributes', 'autofocus'));
  }

  /**
   * An error on the field container focuses its first input.
   */
  public function testErrorOnTheFieldContainerFocusesItsInput(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('revista_secondary][field_meta_tags', 'Meta inválida.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertSame(
      'autofocus',
      self::optionalValueAt($form, 'revista_secondary', 'field_meta_tags', 'meta', '#attributes', 'autofocus'),
    );
  }

  /**
   * Core field widgets may still report original paths without group prefix.
   */
  public function testOriginalFieldErrorOpensGroupAndFocusesWidget(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('field_tags][target_id', 'Etiqueta inválida.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertSame(
      'autofocus',
      self::optionalValueAt($form, 'revista_secondary', 'field_tags', 'target_id', '#attributes', 'autofocus'),
    );
    // A alteração visual não deve reescrever o estado de validação do Core.
    self::assertArrayHasKey('field_tags][target_id', $form_state->getErrors());
  }

  /**
   * An original-path error opens the group but does not steal earlier focus.
   */
  public function testOriginalFieldErrorPreservesEarlierErrorFocus(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('title', 'Título obrigatório.');
    $form_state->setErrorByName('field_tags][target_id', 'Etiqueta inválida.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertFalse($this->hasAutofocus($form));
  }

  /**
   * Non-interactive wrappers must not receive autofocus after field errors.
   */
  public function testErrorOnContainerWithoutWidgetDoesNotFocusWrapper(): void {
    $form = [
      'field_credits' => [
        '#type' => 'container',
        '#attributes' => [],
        'description' => [
          '#type' => 'markup',
          '#markup' => 'Sem campo de entrada.',
        ],
      ],
    ];
    $form_state = new FormState();
    $form_state->setErrorByName('field_credits', 'Crédito inválido.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertFalse($this->hasAutofocus($form));
  }

  /**
   * Hidden controls and inaccessible inputs cannot receive validation focus.
   */
  public function testInvalidFieldSkipsHiddenAndInaccessibleInputs(): void {
    $form = [
      'field_credits' => [
        '#type' => 'container',
        'token' => ['#type' => 'hidden', '#value' => 'test'],
        'disabled' => [
          '#type' => 'textfield',
          '#access' => FALSE,
        ],
        'credit' => [
          '#type' => 'textfield',
          '#attributes' => [],
        ],
      ],
    ];
    $form_state = new FormState();
    $form_state->setErrorByName('field_credits', 'Crédito obrigatório.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertSame('autofocus', self::optionalValueAt($form, 'revista_secondary', 'field_credits', 'credit', '#attributes', 'autofocus'));
    self::assertFalse($this->hasAutofocus(self::arrayAt($form, 'revista_secondary', 'field_credits', 'token')));
    self::assertFalse($this->hasAutofocus(self::arrayAt($form, 'revista_secondary', 'field_credits', 'disabled')));
  }

  /**
   * An earlier error keeps the focus; the group still opens.
   */
  public function testEarlierErrorKeepsTheFocusButGroupStillOpens(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('title', 'Título obrigatório.');
    $form_state->setErrorByName('revista_secondary][field_tags', 'Etiqueta inválida.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertFalse($this->hasAutofocus($form));
  }

  /**
   * Errors outside the group keep it closed.
   */
  public function testUnrelatedErrorKeepsGroupClosed(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('title', 'Título obrigatório.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertFalse(self::valueAt($form, 'revista_secondary', '#open'));
    self::assertFalse($this->hasAutofocus($form));
  }

  /**
   * Recursively checks a form array for the autofocus attribute.
   *
   * @param array<array-key, mixed> $element
   *   Form element to inspect.
   */
  private function hasAutofocus(array $element): bool {
    $attributes = $element['#attributes'] ?? NULL;
    if (is_array($attributes) && ($attributes['autofocus'] ?? NULL) === 'autofocus') {
      return TRUE;
    }
    foreach ($element as $key => $value) {
      if ($key !== '#' && is_array($value) && $this->hasAutofocus($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Reads a form value while validating every parent element.
   */
  private static function valueAt(mixed $form, string ...$path): mixed {
    $value = $form;
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        throw new \UnexpectedValueException(sprintf('Missing form element key "%s".', $key));
      }
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Reads an optional form value, returning NULL when a path is absent.
   */
  private static function optionalValueAt(mixed $form, string ...$path): mixed {
    $value = $form;
    foreach ($path as $key) {
      if (!is_array($value) || !array_key_exists($key, $value)) {
        return NULL;
      }
      $value = $value[$key];
    }

    return $value;
  }

  /**
   * Reads a nested form element while validating its array type.
   *
   * @return array<array-key, mixed>
   *   Form element array.
   */
  private static function arrayAt(mixed $form, string ...$path): array {
    $value = self::valueAt($form, ...$path);
    if (!is_array($value)) {
      throw new \UnexpectedValueException(sprintf('Form path "%s" must contain an array.', implode('.', $path)));
    }

    return $value;
  }

}
