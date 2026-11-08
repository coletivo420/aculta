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
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests COM-09: Citação must consult getWriteAccess (not just getReadAccess).
 * When write access is denied or discussion is closed, quote action must not be exposed.
 */
#[Group('aculta_forum')]
final class ForumQuoteAccessPropagationTest extends UnitTestCase {

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
   * Tests that when getWriteAccess is forbidden, quote link is not exposed even if read access is allowed.
   */
  public function testWriteAccessForbiddenDeniesQuoteLink(): void {
    $host = $this->createMock(EntityInterface::class);
    $host->method('getCacheContexts')->willReturn([]);
    $host->method('getCacheTags')->willReturn([]);
    $host->method('getCacheMaxAge')->willReturn(-1);

    // Read allowed, but write forbidden (e.g. discussion closed)
    $readAccess = AccessResult::allowed();
    $writeAccess = AccessResult::forbidden('Discussion is closed');
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
    $comment->method('access')->willReturnCallback(
      static fn (string $op) => AccessResult::allowed()
    );
    $comment->method('getCacheContexts')->willReturn([]);
    $comment->method('getCacheTags')->willReturn([]);
    $comment->method('getCacheMaxAge')->willReturn(-1);

    $links = [];
    $hookContext = [];

    $hooks->commentLinksAlter($links, $comment, $hookContext);

    // Reproduces COM-09: Currently ForumQuoteHooks only checks $discussion->getReadAccess(),
    // so in the buggy code this assertion will FAIL (because it mistakenly generated the quote link).
    self::assertEmpty($links, 'Quote link must not be generated when write access is denied or discussion is closed.');
  }

}
