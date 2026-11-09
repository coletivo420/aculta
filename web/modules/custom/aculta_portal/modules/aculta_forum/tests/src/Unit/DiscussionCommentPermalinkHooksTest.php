<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Hook\DiscussionCommentPermalinkHooks;
use Drupal\aculta_forum\Service\DiscussionCommentPermalinkBuilderInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests DiscussionCommentPermalinkHooks.
 */
#[Group('aculta_forum')]
final class DiscussionCommentPermalinkHooksTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $linkGenerator = $this->createMock(\Drupal\Core\Utility\LinkGeneratorInterface::class);
    $linkGenerator->method('generate')->willReturnCallback(
      static fn ($text, Url $url): \Drupal\Core\GeneratedLink => (new \Drupal\Core\GeneratedLink())->setGeneratedLink('<a href="' . $url->toString() . '">' . $text . '</a>')
    );
    $generator = $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/node/123?page=2#comment-456');
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('url_generator', $generator);
    $container->set('link_generator', $linkGenerator);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests preprocessComment sets host permalink for discussion comments.
   */
  public function testPreprocessCommentSetsHostPermalink(): void {
    $url = Url::fromRoute('entity.node.canonical', ['node' => 123], [
      'query' => ['page' => 2],
      'fragment' => 'comment-456',
    ]);
    $builder = $this->createMock(DiscussionCommentPermalinkBuilderInterface::class);
    $builder->method('build')->willReturn($url);

    $hooks = new DiscussionCommentPermalinkHooks($builder);
    $hooks->setStringTranslation($this->getStringTranslationStub());

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');

    $variables = [
      'elements' => ['#comment' => $comment],
      'permalink' => '/resposta/456',
    ];

    $hooks->preprocessComment($variables);

    self::assertStringContainsString('/node/123?page=2#comment-456', (string) $variables['permalink']);
    self::assertSame('/node/123?page=2#comment-456', $variables['permalink_url']);
  }

  /**
   * Other bundles are left untouched.
   */
  public function testIgnoresOtherBundles(): void {
    $builder = $this->createMock(DiscussionCommentPermalinkBuilderInterface::class);
    $builder->expects($this->never())->method('build');

    $hooks = new DiscussionCommentPermalinkHooks($builder);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('standard_comment');

    $variables = [
      'elements' => ['#comment' => $comment],
      'permalink' => '/resposta/456',
    ];

    $hooks->preprocessComment($variables);
    self::assertSame('/resposta/456', $variables['permalink']);
  }

}
