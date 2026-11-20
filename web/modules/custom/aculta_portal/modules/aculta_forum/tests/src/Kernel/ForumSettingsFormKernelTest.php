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
    'text',
    'filter',
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
    $commentMove = $form['comment_move_enabled'] ?? NULL;
    self::assertIsArray($commentMove);
    $editWindow = $form['edit_window_minutes'] ?? NULL;
    self::assertIsArray($editWindow);

    self::assertSame('checkbox', $commentMove['#type'] ?? NULL);
    self::assertSame(
      'aculta_forum.settings:comment_move_enabled',
      $commentMove['#config_target'] ?? NULL,
    );
    self::assertSame('number', $editWindow['#type'] ?? NULL);
    self::assertSame(10, $editWindow['#default_value'] ?? NULL);
    self::assertSame(0, $editWindow['#min'] ?? NULL);
    self::assertTrue($editWindow['#required'] ?? NULL);
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

  /**
   * Malformed reaction configuration is excluded and valid edits are stored.
   */
  public function testReactionConfigurationIsValidated(): void {
    $this->container->get('config.factory')->getEditable('aculta_forum.settings')
      ->set('allowed_reactions', [
        ['id' => 'heart', 'icon' => 'heart', 'label' => 'Coração', 'enabled' => TRUE],
        ['id' => ['invalid'], 'icon' => 'broken', 'label' => 'Inválida', 'enabled' => TRUE],
        ['id' => 'sem_rotulo', 'icon' => 'broken', 'enabled' => TRUE],
        'entrada inválida',
      ])
      ->set('default_reaction', 'heart')
      ->set('reactions_enabled', TRUE)
      ->save();

    $form = $this->form->buildForm([], new FormState());
    $fieldset = $form['reactions_fieldset'] ?? NULL;
    self::assertIsArray($fieldset);
    $defaultReaction = $fieldset['default_reaction'] ?? NULL;
    self::assertIsArray($defaultReaction);
    self::assertSame('item', $defaultReaction['#type'] ?? NULL);
    $markup = $defaultReaction['#markup'] ?? '';
    self::assertStringContainsString('Brócolis', is_string($markup) || $markup instanceof \Stringable ? (string) $markup : '');

    $table = $fieldset['reactions_table'] ?? NULL;
    self::assertIsArray($table);
    $row = $table[0] ?? NULL;
    self::assertIsArray($row);
    $id = $row['id'] ?? NULL;
    self::assertIsArray($id);
    self::assertSame('<code>heart</code>', $id['#markup'] ?? NULL);

    $state = new FormState();
    $state->setValue('edit_window_minutes', '15');
    $state->setValue('reply_max_depth', '3');
    $state->setValue('topic_author_delete_policy', 'direct_delete');
    $state->setValue('reactions_enabled', '1');
    $state->setValue('default_reaction', 'heart');
    $state->setValue('reactions_table', [
      0 => ['icon' => 'heart-new', 'label' => 'Novo rótulo', 'enabled' => 0],
    ]);
    $submissionForm = [];
    $this->form->submitForm($submissionForm, $state);

    $saved = $this->container->get('config.factory')->get('aculta_forum.settings');
    self::assertSame(15, $saved->get('edit_window_minutes'));
    self::assertSame(3, $saved->get('reply_max_depth'));
    self::assertSame('direct_delete', $saved->get('topic_author_delete_policy'));
    self::assertTrue($saved->get('reactions_enabled'));
    self::assertSame('broccoli', $saved->get('default_reaction'));
    self::assertSame([
      ['id' => 'heart', 'icon' => 'heart-new', 'label' => 'Novo rótulo', 'enabled' => FALSE],
    ], $saved->get('allowed_reactions'));

    $invalidState = new FormState();
    $invalidState->setValue('edit_window_minutes', '90');
    $invalidState->setValue('reply_max_depth', '8');
    $this->form->submitForm($submissionForm, $invalidState);
    self::assertArrayHasKey('reply_max_depth', $invalidState->getErrors());
    self::assertSame(15, $this->container->get('config.factory')
      ->get('aculta_forum.settings')->get('edit_window_minutes'));
  }

}
