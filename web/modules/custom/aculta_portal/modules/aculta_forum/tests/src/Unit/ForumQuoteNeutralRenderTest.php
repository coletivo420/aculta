<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Context\DiscussionContext;
use Drupal\aculta_forum\Context\DiscussionContextProviderInterface;
use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_forum\Hook\ForumQuoteHooks;
use Drupal\aculta_forum\Service\ForumQuoteBuilder;
use Drupal\comment\CommentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that ForumQuoteHooks produces neutral Drupal links in #links structure
 * compatible with Link::preRenderLinks and without direct SDC component instantiation.
 */
#[Group('aculta_forum')]
final class ForumQuoteNeutralRenderTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    $generator = $this->createMock(\Drupal\Core\Routing\UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/comment/reply/node/1/field_aculta_discussion/10?quote=10#comment-form');
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->set('url_generator', $generator);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Tests that quote action renders into #links structure and collects cleanly.
   */
  public function testCommentLinksAlterRendersNeutralLinksStructure(): void {
    $host = $this->createMock(EntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);

    $readAccess = AccessResult::allowed();
    $writeAccess = AccessResult::allowed();
    $context = new DiscussionContext(
      $host,
      'public_forum',
      'public_forum:node:uuid',
      $readAccess,
      $writeAccess,
      AccessResult::neutral(),
      Url::fromRoute('system.404'),
      'public',
      'public',
      'public'
    );
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->willReturn(TRUE);
    $provider->method('getContext')->willReturn($context);
    $resolver = new DiscussionContextResolver([$provider]);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn(new TranslatableMarkup('Citar'));

    $hooks = new ForumQuoteHooks(
      $resolver,
      $this->createMock(AccountProxyInterface::class),
      new ForumQuoteBuilder(),
      new RequestStack(),
      $translation,
    );

    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('isPublished')->willReturn(TRUE);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('getCommentedEntityTypeId')->willReturn('node');
    $comment->method('getCommentedEntityId')->willReturn(1);
    $comment->method('id')->willReturn(10);
    $comment->method('access')->willReturnCallback(
      static fn (string $op) => AccessResult::allowed()
    );
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheTags')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);

    // Initial links as prepared by CommentLazyBuilders
    $links = [
      '#theme' => 'links__comment__comment',
      '#pre_render' => [[Link::class, 'preRenderLinks']],
      '#attributes' => ['class' => ['links', 'inline']],
      'comment' => [
        '#theme' => 'links__comment__comment',
        '#links' => [
          'comment-reply' => [
            'title' => 'Responder',
            'url' => Url::fromRoute('comment.reply', [
              'entity_type' => 'node',
              'entity' => 1,
              'field_name' => 'field_aculta_discussion',
              'pid' => 10,
            ]),
          ],
        ],
      ],
    ];
    $hookContext = [];

    $hooks->commentLinksAlter($links, $comment, $hookContext);

    // Verifies action structure: must not instantiate SDC directly in module
    self::assertArrayNotHasKey('#component', $links);
    self::assertTrue(isset($links['comment']['#links']['comment-quote']) || isset($links['#links']['comment-quote']));

    // Call Link::preRenderLinks to verify Core collecting
    $rendered = Link::preRenderLinks($links);
    self::assertArrayHasKey('#links', $rendered);
    self::assertArrayHasKey('comment-quote', $rendered['#links']);
    self::assertSame('Citar', (string) $rendered['#links']['comment-quote']['title']);
    self::assertSame('comment-quote', $rendered['#links']['comment-quote']['attributes']['data-action']);
    self::assertSame('10', (string) $rendered['#links']['comment-quote']['attributes']['data-quote-id']);
  }

}
