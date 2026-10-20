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
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the access boundary and link contract for quote actions.
 */
#[Group('aculta_forum')]
final class ForumQuoteHooksTest extends UnitTestCase {

  /**
   * Comments outside the shared discussion bundle never receive a quote link.
   */
  public function testOtherCommentBundlesAreIgnored(): void {
    $hooks = $this->hooks(AccessResult::allowed());
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('comment_forum');
    $links = [];
    $context = [];

    $hooks->commentLinksAlter($links, $comment, $context);

    self::assertSame([], $links);
  }

  /**
   * Unpublished comments never expose their text through the quote action.
   */
  public function testUnpublishedCommentDoesNotGetQuoteLink(): void {
    $hooks = $this->hooks(AccessResult::allowed());
    $comment = $this->comment(AccessResult::allowed(), AccessResult::allowed(), FALSE);
    $links = [];
    $context = [];

    $hooks->commentLinksAlter($links, $comment, $context);

    self::assertSame([], $links);
  }

  /**
   * A reader who cannot reply is not offered an unusable quote action.
   */
  public function testAccountWithoutReplyAccessDoesNotGetQuoteLink(): void {
    $hooks = $this->hooks(AccessResult::allowed());
    $comment = $this->comment(AccessResult::allowed(), AccessResult::forbidden());
    $links = [];
    $context = [];

    $hooks->commentLinksAlter($links, $comment, $context);

    self::assertSame([], $links);
  }

  /**
   * A context that denies reading never exposes a reply/quote action.
   */
  public function testUnreadableContextDoesNotExposeQuoteLink(): void {
    $hooks = $this->hooks(AccessResult::forbidden());
    $comment = $this->comment(AccessResult::allowed());
    $links = [];
    $context = [];

    $hooks->commentLinksAlter($links, $comment, $context);

    self::assertArrayNotHasKey('aculta_forum_quote', $links);
  }

  /**
   * Readable published comments link to Core's reply form with the source ID.
   */
  public function testReadableCommentLinksToCoreReplyForm(): void {
    $hooks = $this->hooks(AccessResult::allowed());
    $comment = $this->comment(AccessResult::allowed());
    $links = [];
    $context = [];

    $hooks->commentLinksAlter($links, $comment, $context);

    $url = $links['aculta_forum_quote']['#links']['aculta-forum-quote']['url'];
    self::assertSame('comment.reply', $url->getRouteName());
    self::assertSame(42, $url->getRouteParameters()['pid']);
    self::assertSame(['quote' => 42], $url->getOption('query'));
    self::assertSame(
      ['data-portal-ui' => 'button-secondary'],
      $links['aculta_forum_quote']['#links']['aculta-forum-quote']['attributes'],
    );
  }

  /**
   * The quote query only prefills the exact accessible parent reply form.
   */
  public function testReplyFormPrefillsSanitizedParentQuote(): void {
    $host = $this->createMock(EntityInterface::class);
    $this->makeCacheable($host);
    $parent = $this->comment(AccessResult::allowed());
    $parent->method('hasField')->with('comment_body')->willReturn(TRUE);
    $body = new class('<p>Original <strong>mensagem</strong>.</p>') extends FieldItemList {
      /**
       * Filtered comment markup used by this test.
       */
      private string $rendered;

      public function __construct(string $rendered) {
        $this->rendered = $rendered;
      }

      /**
       * Test field item list always has a comment body value.
       */
      public function isEmpty(): bool {
        return FALSE;
      }

      /**
       * Exposes the filtered text used by the quote builder.
       */
      public function __get($name) {
        return $name === 'processed' ? $this->rendered : parent::__get($name);
      }

    };
    $parent->method('get')->with('comment_body')->willReturn($body);
    $parent->method('getAuthorName')->willReturn('Pessoa autora');
    $parent->method('getCommentedEntity')->willReturn($host);
    $parent->method('getCommentedEntityTypeId')->willReturn('node');
    $parent->method('getCommentedEntityId')->willReturn(17);

    $reply = $this->createMock(CommentInterface::class);
    $reply->method('isNew')->willReturn(TRUE);
    $reply->method('hasParentComment')->willReturn(TRUE);
    $reply->method('getParentComment')->willReturn($parent);
    $reply->method('getCommentedEntity')->willReturn($host);
    $reply->method('getCommentedEntityTypeId')->willReturn('node');
    $reply->method('getCommentedEntityId')->willReturn(17);
    $formObject = $this->createMock(EntityFormInterface::class);
    $formObject->method('getEntity')->willReturn($reply);
    $formState = new FormState();
    $formState->setFormObject($formObject);

    $request = Request::create('/comment/reply/node/17/field_aculta_discussion/42?quote=42');
    $request->attributes->set('_route', 'comment.reply');
    $stack = new RequestStack();
    $stack->push($request);
    $hooks = $this->hooks(AccessResult::allowed(), $stack);
    $form = ['comment_body' => ['widget' => [0 => ['value' => ['#default_value' => '']]]]];

    $hooks->commentFormAlter($form, $formState, 'comment_form');

    self::assertSame(
      '<blockquote><p>Original mensagem.</p><cite>Pessoa autora</cite></blockquote><p></p>',
      $form['comment_body']['widget'][0]['value']['#default_value'],
    );
    self::assertContains('url.query_args:quote', $form['#cache']['contexts']);
  }

  /**
   * Creates the hook with a single provider returning the chosen access result.
   */
  private function hooks(AccessResult $readAccess, ?RequestStack $requestStack = NULL): ForumQuoteHooks {
    $host = $this->createMock(EntityInterface::class);
    $this->makeCacheable($host);
    $context = new DiscussionContext(
      $host,
      'public_forum',
      'public_forum:node:uuid',
      $readAccess,
      AccessResult::neutral(),
      AccessResult::neutral(),
      Url::fromRoute('system.404'),
      'public_if_entity_accessible',
      'public_if_entity_accessible',
      'public_if_entity_accessible',
    );
    $provider = $this->createMock(DiscussionContextProviderInterface::class);
    $provider->method('supports')->willReturn(TRUE);
    $provider->method('getContext')->willReturn($context);
    $resolver = new DiscussionContextResolver([$provider]);

    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturn(new TranslatableMarkup('Citar'));

    return new ForumQuoteHooks(
      $resolver,
      $this->createMock(AccountProxyInterface::class),
      new ForumQuoteBuilder(),
      $requestStack ?? new RequestStack(),
      $translation,
    );
  }

  /**
   * Creates a published Comment from the shared discussion field.
   */
  private function comment(
    AccessResult $viewAccess,
    ?AccessResult $replyAccess = NULL,
    bool $published = TRUE,
  ): CommentInterface {
    $host = $this->createMock(EntityInterface::class);
    $this->makeCacheable($host);
    $comment = $this->createMock(CommentInterface::class);
    $comment->method('bundle')->willReturn('aculta_discussion_post');
    $comment->method('getFieldName')->willReturn('field_aculta_discussion');
    $comment->method('isPublished')->willReturn($published);
    $comment->method('getCommentedEntity')->willReturn($host);
    $comment->method('access')->willReturnCallback(
      static fn (string $operation, AccountProxyInterface $account, bool $return_as_object) => $operation === 'create'
        ? ($replyAccess ?? $viewAccess)
        : $viewAccess,
    );
    $comment->method('getCommentedEntityTypeId')->willReturn('node');
    $comment->method('getCommentedEntityId')->willReturn(17);
    $comment->method('id')->willReturn(42);
    $this->makeCacheable($comment);
    return $comment;
  }

  /**
   * Supplies complete cache metadata for mocked entities.
   */
  private function makeCacheable(EntityInterface $entity): void {
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
  }

}
