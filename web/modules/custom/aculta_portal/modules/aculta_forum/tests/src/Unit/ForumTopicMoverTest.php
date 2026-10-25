<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumTopicMover;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the public-only and historical immutability boundary of topic moves.
 */
#[Group('aculta_forum')]
final class ForumTopicMoverTest extends UnitTestCase {

  /**
   * Tests that historical Growroom authorship blocks category movement. */
  public function testHistoricalTopicIsNotMovable(): void {
    [$mover, $node, $account] = $this->subject(FALSE);
    $node->expects(self::never())->method('access');

    self::assertTrue($mover->access($node, $account)->isForbidden());
  }

  /**
   * Tests that permission is not inferred from administrative entity access. */
  public function testMovePermissionIsRequired(): void {
    [$mover, $node, $account] = $this->subject(TRUE, FALSE);
    $node->expects(self::never())->method('access');

    self::assertTrue($mover->access($node, $account)->isForbidden());
  }

  /**
   * Tests that a current public topic requires permission and entity access. */
  public function testPublicTopicRequiresMovePermissionAndViewAccess(): void {
    [$mover, $node, $account] = $this->subject(TRUE);
    $node->expects(self::once())->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());

    $result = $mover->access($node, $account);
    self::assertTrue($result->isAllowed());
    self::assertContains('user.permissions', $result->getCacheContexts());
  }

  /**
   * Tests an accepted move creates one revision on the same Node object. */
  public function testMoveChangesOnlyCategoryAndCreatesRevision(): void {
    [$mover, $node, $account] = $this->subject(TRUE);
    $node->method('access')->with('view', $account, TRUE)->willReturn(AccessResult::allowed());
    $node->method('id')->willReturn(100);
    $node->expects(self::once())->method('setNewRevision')->with(TRUE);
    $node->expects(self::once())->method('set')->with('field_aculta_categories', ['target_id' => 99]);
    $node->expects(self::once())->method('setRevisionLogMessage')->with('Categoria do tópico reorganizada pela administração do Fórum.');
    $node->expects(self::once())->method('setRevisionUserId')->with(7);
    $node->expects(self::once())->method('save');

    $account->method('id')->willReturn(7);
    $result = $mover->move(100, 99, $account);

    self::assertSame($node, $result);
  }

  /**
   * Creates a movable public topic or an immutable historic topic.
   *
   * @return array{\Drupal\aculta_forum\Service\ForumTopicMover, \Drupal\node\NodeInterface, \Drupal\Core\Session\AccountInterface}
   *   The service, node and account under test.
   */
  private function subject(bool $isPublic, bool $canMove = TRUE): array {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
    $domain->method('getCacheContexts')->willReturn([]);
    $domain->method('getCacheTags')->willReturn(['config:domain.record.forum_aculta_org']);
    $domain->method('getCacheMaxAge')->willReturn(-1);
    $domain_storage = $this->createMock(EntityStorageInterface::class);
    $domain_storage->method('load')->with('forum_aculta_org')->willReturn($domain);
    $term = $this->createMock(TermInterface::class);
    $term->method('bundle')->willReturn('aculta_forum_categories');
    $term->method('access')->willReturn(TRUE);
    $term->method('getCacheContexts')->willReturn([]);
    $term->method('getCacheTags')->willReturn(['taxonomy_term:12']);
    $term->method('getCacheMaxAge')->willReturn(-1);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('move aculta forum topics')->willReturn($canMove);

    $legacy = $this->createMock(FieldItemListInterface::class);
    $legacy->method('isEmpty')->willReturn($isPublic);
    $domain_field = $this->createMock(FieldItemListInterface::class);
    $domain_field->method('getValue')->willReturn([['target_id' => 'forum_aculta_org']]);
    $category = $this->createMock(FieldItemListInterface::class);
    $category->method('isEmpty')->willReturn(FALSE);
    $category->method('getValue')->willReturn([['target_id' => 12]]);

    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn('aculta_forum_topic');
    $node->method('hasField')->willReturn(TRUE);
    $node->method('get')->willReturnMap([
      ['field_autor_legado', $legacy],
      ['field_domain_source', $domain_field],
      ['field_aculta_categories', $category],
    ]);
    $node->method('getCacheContexts')->willReturn([]);
    $node->method('getCacheTags')->willReturn([]);
    $node->method('getCacheMaxAge')->willReturn(-1);

    $destination = $this->createMock(TermInterface::class);
    $destination->method('id')->willReturn(99);
    $destination->method('bundle')->willReturn('aculta_forum_categories');
    $destination->method('access')->willReturn(TRUE);
    $term_storage = $this->createMock(EntityStorageInterface::class);
    $term_storage->method('load')->willReturnMap([[12, $term], [99, $destination]]);
    $node_storage = $this->createMock(EntityStorageInterface::class);
    $node_storage->method('load')->with(100)->willReturn($node);
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnMap([
      ['domain', $domain_storage],
      ['taxonomy_term', $term_storage],
      ['node', $node_storage],
    ]);
    $purpose = new DomainPurposeManager(
      $manager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );

    return [
      new ForumTopicMover($manager, $purpose, $this->createMock(LoggerInterface::class)),
      $node,
      $account,
    ];
  }

}
