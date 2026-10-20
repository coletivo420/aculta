<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\DiscussionProgressHooks;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

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

    (new DiscussionProgressHooks())->entityViewAlter($build, $node, $this->createMock(EntityViewDisplayInterface::class));

    self::assertSame('', $build['field_aculta_discussion']['#attributes']['data-aculta-discussion-thread']);
    self::assertSame(['aculta_forum/discussion-progress'], $build['field_aculta_discussion']['#attached']['library']);
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
      (new DiscussionProgressHooks())->entityViewAlter($build, $node, $display);
      self::assertSame($original, $build);
    }

    $nonNode = $this->createMock(EntityInterface::class);
    $build = ['#view_mode' => 'full', 'field_aculta_discussion' => []];
    $original = $build;
    (new DiscussionProgressHooks())->entityViewAlter($build, $nonNode, $display);
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

}
