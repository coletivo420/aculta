<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Controller\ForumPostModerationFormController;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Proves the moderation form opens the exact requested revision.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostModerationFormControllerKernelTest extends KernelTestBase {

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
    'domain',
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
   * Captured entity passed to the form builder.
   */
  private ?ForumPost $builtPost = NULL;

  /**
   * Captured form operation.
   */
  private ?string $operation = NULL;

  /**
   * Form builder double.
   */
  private EntityFormBuilderInterface $formBuilder;

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
    $this->post->set('body', ['value' => 'Segunda versão', 'format' => 'plain_text']);
    $this->post->save();
    $this->secondRevision = (int) $this->post->getRevisionId();

    $this->formBuilder = $this->createMock(EntityFormBuilderInterface::class);
    $this->formBuilder->method('getForm')->willReturnCallback(
      function ($entity, $operation): array {
        $this->builtPost = $entity;
        $this->operation = $operation;
        return ['#markup' => 'form'];
      },
    );
  }

  /**
   * The requested revision, not the default one, reaches the form builder.
   */
  public function testRequestedRevisionIsBuilt(): void {
    $build = $this->controller()->form((string) $this->firstRevision);

    self::assertSame('form', $build['#markup']);
    self::assertNotNull($this->builtPost);
    self::assertSame($this->firstRevision, (int) $this->builtPost->getRevisionId());
    self::assertSame((int) $this->post->id(), (int) $this->builtPost->id());
    self::assertSame('default', $this->operation);
  }

  /**
   * Unknown revision identifiers are not found.
   */
  public function testUnknownRevisionIsNotFound(): void {
    try {
      $this->controller()->form('999999');
      self::fail('An unknown revision must not build a form.');
    }
    catch (NotFoundHttpException) {
      self::assertNull($this->builtPost);
    }
  }

  /**
   * Builds the controller with the form builder double.
   */
  private function controller(): ForumPostModerationFormController {
    return new ForumPostModerationFormController(
      $this->container->get('entity_type.manager'),
      $this->formBuilder,
    );
  }

}
