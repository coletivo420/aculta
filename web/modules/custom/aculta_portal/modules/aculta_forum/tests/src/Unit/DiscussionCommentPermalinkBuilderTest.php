<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests DiscussionCommentPermalinkBuilder.
 */
#[Group('aculta_forum')]
final class DiscussionCommentPermalinkBuilderTest extends TestCase {

  /**
   * O link público é /resposta/{cid}, na rota própria do módulo.
   */
  public function testBuildsPublicReplyPermalink(): void {
    $builder = $this->builder();

    $host = $this->createMock(NodeInterface::class);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('id')->willReturn(456);

    $url = $builder->build($comment);
    self::assertNotNull($url);
    self::assertSame('entity.comment.canonical', $url->getRouteName());
    self::assertSame(['comment' => 456], $url->getRouteParameters());
  }

  /**
   * Sem entidade hospedeira, não há link.
   */
  public function testReturnsNullWithoutHostEntity(): void {
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn(NULL);

    self::assertNull($this->builder()->build($comment));
    self::assertNull($this->builder()->hostUrl($comment));
  }

  /**
   * A URL do tópico leva a âncora do comentário e, sem paginação, nenhuma página.
   */
  public function testHostUrlHasAnchorAndNoPageWhenPerPageIsZero(): void {
    $hostUrl = Url::fromRoute('entity.node.canonical', ['node' => 123]);

    $field = $this->createMock(FieldDefinitionInterface::class);
    $field->method('getSetting')->with('per_page')->willReturn(0);

    $host = $this->createMock(NodeInterface::class);
    $host->method('toUrl')->with('canonical')->willReturn($hostUrl);
    $host->method('getFieldDefinition')->willReturn($field);

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('id')->willReturn(456);

    $url = $this->builder()->hostUrl($comment);
    self::assertNotNull($url);
    self::assertSame('entity.node.canonical', $url->getRouteName());
    self::assertSame('resposta-456', $url->getOption('fragment'));
    self::assertArrayNotHasKey('query', $url->getOptions());
  }

  /**
   * Cria o serviço com um gerenciador de paginação e de entidades simulados.
   */
  private function builder(): DiscussionCommentPermalinkBuilder {
    return new DiscussionCommentPermalinkBuilder(
      $this->createMock(PagerManagerInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
    );
  }

}
