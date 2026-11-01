<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Form\ForumSettingsForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Proves the Forum settings form keeps safe defaults and clamps input.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumSettingsFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'aculta_forum_post_test',
  ];

  /**
   * Form under test.
   */
  private ForumSettingsForm $form;

  /**
   * {@inheritdoc}
   */
  protected function getConfigSchemaExclusions(): array {
    // The schema ships with the Forum module, which the fixture does not load.
    return ['aculta_forum.settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user']);
    $this->form = new ForumSettingsForm(
      $this->container->get('config.factory'),
      $this->container->get('config.typed'),
    );
  }

  /**
   * The comment move option is off by default and the edit window is 10.
   */
  public function testDefaults(): void {
    $form = $this->form->buildForm([], new FormState());

    self::assertSame('checkbox', $form['comment_move_enabled']['#type']);
    self::assertSame(
      'aculta_forum.settings:comment_move_enabled',
      $form['comment_move_enabled']['#config_target'],
    );
    self::assertSame('number', $form['edit_window_minutes']['#type']);
    self::assertSame(10, $form['edit_window_minutes']['#default_value']);
    self::assertSame(0, $form['edit_window_minutes']['#min']);
    self::assertTrue($form['edit_window_minutes']['#required']);
  }

  /**
   * Submitted minutes are stored and negative values are clamped to zero.
   */
  public function testSubmitClampsEditWindow(): void {
    $state = new FormState();
    $state->setValue('edit_window_minutes', 15);
    $form = [];
    $this->form->submitForm($form, $state);
    self::assertSame(15, $this->container->get('config.factory')
      ->get('aculta_forum.settings')->get('edit_window_minutes'));

    $negative = new FormState();
    $negative->setValue('edit_window_minutes', -5);
    $this->form->submitForm($form, $negative);
    self::assertSame(0, $this->container->get('config.factory')
      ->get('aculta_forum.settings')->get('edit_window_minutes'));
  }

}
