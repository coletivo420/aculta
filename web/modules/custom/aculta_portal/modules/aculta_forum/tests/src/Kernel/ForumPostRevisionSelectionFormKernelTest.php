<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\aculta_forum\Form\ForumPostRevisionSelectionForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Proves the revision selection form validates and orders the chosen revisions.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostRevisionSelectionFormKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'aculta_forum_post_test',
  ];

  /**
   * Post with two revisions.
   */
  private ForumPost $post;

  /**
   * First revision ID.
   */
  private int $firstRevision;

  /**
   * Second revision ID.
   */
  private int $secondRevision;

  /**
   * Revision of another post.
   */
  private int $foreignRevision;

  /**
   * Form under test.
   */
  private ForumPostRevisionSelectionForm $form;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_forum_post');
    $this->installConfig(['system', 'user', 'field', 'filter', 'text', 'node']);
    User::create(['name' => 'base', 'status' => 1])->save();
    $author = User::create(['name' => 'autora', 'status' => 1]);
    $author->save();
    $this->post = ForumPost::create([
      'uid' => $author->id(),
      'topic' => 1,
      'subject' => 'Primeira versão',
      'body' => ['value' => 'Primeira versão', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $this->post->save();
    $this->firstRevision = (int) $this->post->getRevisionId();
    $this->post->setNewRevision(TRUE);
    $this->post->set('subject', 'Segunda versão');
    $this->post->save();
    $this->secondRevision = (int) $this->post->getRevisionId();

    $foreign = ForumPost::create([
      'uid' => $author->id(),
      'topic' => 1,
      'subject' => 'Outra publicação',
      'body' => ['value' => 'Outra', 'format' => 'plain_text'],
      'status' => 1,
    ]);
    $foreign->save();
    $this->foreignRevision = (int) $foreign->getRevisionId();

    $this->form = $this->formFor($this->post);
  }

  /**
   * Defaults pick the oldest and newest revisions of the page.
   */
  public function testDefaults(): void {
    $build = $this->form->buildForm([], new FormState(), [
      $this->secondRevision => 'Revisão dois',
      $this->firstRevision => 'Revisão um',
    ]);

    self::assertSame('number', $build['older_revision']['#type']);
    self::assertSame($this->firstRevision, $build['older_revision']['#default_value']);
    self::assertSame($this->secondRevision, $build['newer_revision']['#default_value']);
    self::assertTrue($build['older_revision']['#required']);
    self::assertTrue($build['newer_revision']['#required']);
    self::assertSame('submit', $build['actions']['compare']['#type']);

    self::assertSame([], $this->form->buildForm([], new FormState()));
  }

  /**
   * Submission redirects with revisions sorted from oldest to newest.
   */
  public function testSubmitOrdersRevisions(): void {
    $state = new FormState();
    $state->setValue('older_revision', $this->secondRevision);
    $state->setValue('newer_revision', $this->firstRevision);
    $form = [];
    $this->form->submitForm($form, $state);

    $redirect = $state->getRedirect();
    self::assertNotNull($redirect);
    self::assertSame('aculta_forum.post_revision_compare', $redirect->getRouteName());
    $parameters = $redirect->getRouteParameters();
    self::assertSame((string) $this->firstRevision, (string) $parameters['left_revision']);
    self::assertSame((string) $this->secondRevision, (string) $parameters['right_revision']);
  }

  /**
   * Invalid, equal and foreign revisions are refused.
   */
  public function testInvalidSelectionsAreRejected(): void {
    $invalid = $this->selection('abc', (string) $this->secondRevision);
    self::assertArrayHasKey('older_revision', $invalid);

    $equal = $this->selection((string) $this->firstRevision, (string) $this->firstRevision);
    self::assertArrayHasKey('newer_revision', $equal);

    $foreign = $this->selection((string) $this->firstRevision, (string) $this->foreignRevision);
    self::assertArrayHasKey('newer_revision', $foreign);
  }

  /**
   * Without the route post the form cannot validate the selection.
   */
  public function testMissingPostIsRejected(): void {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')->willReturn(NULL);
    $form = new ForumPostRevisionSelectionForm(
      $routeMatch,
      $this->container->get('entity_type.manager'),
    );
    $state = new FormState();
    $state->setValue('older_revision', $this->firstRevision);
    $state->setValue('newer_revision', $this->secondRevision);
    $build = [];
    $form->validateForm($build, $state);

    self::assertArrayHasKey('newer_revision', $state->getErrors());
  }

  /**
   * Builds the form bound to the given post.
   */
  private function formFor(ForumPost $post): ForumPostRevisionSelectionForm {
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $routeMatch->method('getParameter')
      ->with('aculta_forum_post')
      ->willReturn($post);
    return new ForumPostRevisionSelectionForm(
      $routeMatch,
      $this->container->get('entity_type.manager'),
    );
  }

  /**
   * Validates a selection and returns the form errors.
   */
  private function selection(string $older, string $newer): array {
    $state = new FormState();
    $state->setValue('older_revision', $older);
    $state->setValue('newer_revision', $newer);
    $build = [];
    $this->form->validateForm($build, $state);
    return $state->getErrors();
  }

}
