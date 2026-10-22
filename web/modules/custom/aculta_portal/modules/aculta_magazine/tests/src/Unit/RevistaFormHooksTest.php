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
   * Builds a minimal publication form with the secondary fields of the bundle.
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
    self::assertFalse($form['revista_secondary']['#open']);
    self::assertContains('aculta_magazine/revista-form', $form['#attached']['library']);
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
    self::assertArrayHasKey('field_credits', $form['revista_secondary']);
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

    self::assertTrue($form['revista_secondary']['#open']);
    self::assertSame(
      'autofocus',
      $form['revista_secondary']['field_tags']['target_id']['#attributes']['autofocus'] ?? NULL,
    );
    self::assertNull($form['title']['#attributes']['autofocus'] ?? NULL);
  }

  /**
   * An error on the field container focuses its first input.
   */
  public function testErrorOnTheFieldContainerFocusesItsInput(): void {
    $form = $this->form();
    $form_state = new FormState();
    $form_state->setErrorByName('revista_secondary][field_meta_tags', 'Meta inválida.');
    (new RevistaFormHooks())->alter($form, $form_state, 'node_revista_edit_form');

    self::assertTrue($form['revista_secondary']['#open']);
    self::assertSame(
      'autofocus',
      $form['revista_secondary']['field_meta_tags']['meta']['#attributes']['autofocus'] ?? NULL,
    );
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

    self::assertTrue($form['revista_secondary']['#open']);
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

    self::assertFalse($form['revista_secondary']['#open']);
    self::assertFalse($this->hasAutofocus($form));
  }

  /**
   * Recursively checks a form array for the autofocus attribute.
   */
  private function hasAutofocus(array $element): bool {
    if (($element['#attributes']['autofocus'] ?? NULL) === 'autofocus') {
      return TRUE;
    }
    foreach ($element as $key => $value) {
      if ($key !== '#' && is_array($value) && $this->hasAutofocus($value)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
