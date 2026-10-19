<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Hook\PublicationDiscussionHooks;
use Drupal\comment\CommentingStatus;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the in-memory default for publication Comment fields.
 */
#[Group('aculta_magazine')]
final class PublicationDiscussionHooksTest extends UnitTestCase {

  /**
   * Existing Comment statistics survive default status initialization.
   */
  public function testMissingStatusUsesOpenAndPreservesStatistics(): void {
    $values = [[
      'cid' => 17,
      'last_comment_timestamp' => 123456,
      'last_comment_name' => NULL,
      'last_comment_uid' => 5,
      'comment_count' => 3,
    ],
    ];
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn($values);
    $field->expects(self::once())
      ->method('setValue')
      ->with([[
        'cid' => 17,
        'last_comment_timestamp' => 123456,
        'last_comment_name' => NULL,
        'last_comment_uid' => 5,
        'comment_count' => 3,
        'status' => CommentingStatus::Open->value,
      ],
      ]);

    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getDefaultValue')->willReturn([
      ['status' => CommentingStatus::Open->value],
    ]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->with('field_aculta_discussion')->willReturn(TRUE);
    $node->method('get')->with('field_aculta_discussion')->willReturn($field);
    $node->method('getFieldDefinition')->with('field_aculta_discussion')->willReturn($definition);

    (new PublicationDiscussionHooks())->nodeLoad([$node]);
  }

  /**
   * The configured default is used when an older publication has no status.
   */
  public function testMissingStatusUsesConfiguredFieldDefault(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('getValue')->willReturn([['comment_count' => 0]]);
    $field->expects(self::once())
      ->method('setValue')
      ->with([[
        'comment_count' => 0,
        'status' => CommentingStatus::Closed->value,
      ],
      ]);

    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getDefaultValue')->willReturn([
      ['status' => CommentingStatus::Closed->value],
    ]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('article');
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->willReturn($field);
    $node->method('getFieldDefinition')->willReturn($definition);

    (new PublicationDiscussionHooks())->nodeLoad([$node]);
  }

  /**
   * Explicit Hidden and Closed values remain unchanged.
   */
  public function testExplicitHiddenAndClosedStatusesArePreserved(): void {
    foreach ([CommentingStatus::Hidden->value, CommentingStatus::Closed->value] as $status) {
      $field = $this->createMock(FieldItemListInterface::class);
      $field->method('getValue')->willReturn([['status' => $status]]);
      $field->expects(self::never())->method('setValue');

      $node = $this->createMock(NodeInterface::class);
      $node->method('bundle')->willReturn('blog');
      $node->method('hasField')->with('field_aculta_discussion')->willReturn(TRUE);
      $node->method('get')->with('field_aculta_discussion')->willReturn($field);

      (new PublicationDiscussionHooks())->nodeLoad([$node]);
    }
  }

  /**
   * Nodes outside the publication bundles are ignored.
   */
  public function testNonPublicationNodesAreIgnored(): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->expects(self::never())->method('hasField');

    (new PublicationDiscussionHooks())->nodeLoad([$node]);
  }

}
