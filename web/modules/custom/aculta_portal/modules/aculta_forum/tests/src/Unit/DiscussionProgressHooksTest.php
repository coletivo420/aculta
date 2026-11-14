<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\DiscussionProgressHooks;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the neutral attachment contract for discussion progress controls.
 */
#[Group('aculta_forum')]
final class DiscussionProgressHooksTest extends UnitTestCase {

  /**
   * Tests full Node displays with the shared field receive the behavior.
   */
  public function testAttachesOnlyToRenderedSharedDiscussionField(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->expects(self::once())
      ->method('hasField')
      ->with('field_aculta_discussion')
      ->willReturn(TRUE);
    $build = [
      '#view_mode' => 'full',
      'field_aculta_discussion' => ['#type' => 'field'],
    ];

    // O hook cria um comentário de referência para anexar o CKEditor. Sem acesso de criação,
    // não chama o construtor de formulário nem o renderizador.
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('access')->willReturn(FALSE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('create')->willReturn($comment);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('comment')->willReturn($storage);
    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entityTypeManager);
    \Drupal::setContainer($container);

    $hooks = new DiscussionProgressHooks(
      $entityTypeManager,
      $this->createMock(EntityFormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
    );
    $hooks->entityViewAlter($build, $node, $this->createMock(EntityViewDisplayInterface::class));

    self::assertSame('', $build['field_aculta_discussion']['#attributes']['data-aculta-discussion-thread']);
    self::assertSame(
      ['aculta_forum/discussion-reply-popup', 'aculta_forum/discussion-side'],
      $build['field_aculta_discussion']['#attached']['library'],
    );
  }

  /**
   * Tests teaser, missing, hidden and non-Node builds are untouched.
   */
  public function testIgnoresOtherOrUnavailableDisplays(): void {
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $node = $this->createMock(NodeInterface::class);
    $node->expects(self::exactly(2))
      ->method('hasField')
      ->with('field_aculta_discussion')
      ->willReturn(TRUE);
    $builds = [
      ['#view_mode' => 'teaser', 'field_aculta_discussion' => []],
      ['#view_mode' => 'full'],
      ['#view_mode' => 'full', 'field_aculta_discussion' => ['#access' => FALSE]],
    ];

    foreach ($builds as $build) {
      $original = $build;
      $this->hooks()->entityViewAlter($build, $node, $display);
      self::assertSame($original, $build);
    }

    $nonNode = $this->createMock(EntityInterface::class);
    $build = ['#view_mode' => 'full', 'field_aculta_discussion' => []];
    $original = $build;
    $this->hooks()->entityViewAlter($build, $nonNode, $display);
    self::assertSame($original, $build);
  }

  /**
   * Ensures the implementation uses Drupal's OOP hook attribute.
   */
  public function testUsesEntityViewAlterHook(): void {
    $method = new \ReflectionMethod(DiscussionProgressHooks::class, 'entityViewAlter');
    $attributes = $method->getAttributes(Hook::class);

    self::assertCount(1, $attributes);
    self::assertSame('entity_view_alter', $attributes[0]->newInstance()->hook);
  }

  private function hooks(): DiscussionProgressHooks {
    return new DiscussionProgressHooks(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFormBuilderInterface::class),
      $this->createMock(RendererInterface::class),
    );
  }

}
