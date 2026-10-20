<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicy;
use Drupal\aculta_messenger\Hook\ForumAvisoHooks;
use Drupal\comment\CommentInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that Forum updates generate notices only when a reply is approved.
 */
#[Group('aculta_messenger')]
final class ForumAvisoHooksTest extends UnitTestCase {

  /**
   * Updating an already published Comment during a move is not a new reply.
   */
  public function testPublishedCommentUpdateDoesNotRegisterAnotherNotice(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->expects(self::once())->method('isPublished')->willReturn(TRUE);

    $comment = $this->createMock(CommentInterface::class);
    $comment->expects(self::once())->method('isPublished')->willReturn(TRUE);
    $comment->expects(self::once())->method('getOriginal')->willReturn($original);
    $comment->expects(self::never())->method('getCommentedEntity');

    $flag_service = $this->createMock(FlagServiceInterface::class);
    $flag_service->expects(self::never())->method('getFlagById');
    $hooks = $this->hooks($flag_service);

    $hooks->commentUpdate($comment);
  }

  /**
   * Publishing a previously pending Comment still reaches notice registration.
   */
  public function testApprovalOfPendingCommentStillRegistersThePublishedReply(): void {
    $original = $this->createMock(CommentInterface::class);
    $original->expects(self::once())->method('isPublished')->willReturn(FALSE);

    $comment = $this->createMock(CommentInterface::class);
    $comment->expects(self::exactly(2))->method('isPublished')->willReturn(TRUE);
    $comment->expects(self::once())->method('getOriginal')->willReturn($original);
    $comment->expects(self::once())->method('bundle')->willReturn('aculta_discussion_post');
    $comment->expects(self::once())->method('getCommentedEntity')->willReturn(NULL);

    $flag_service = $this->createMock(FlagServiceInterface::class);
    $flag_service->expects(self::never())->method('getFlagById');
    $hooks = $this->hooks($flag_service);

    $hooks->commentUpdate($comment);
  }

  /**
   * Creates the real registrar with isolated access and Flag dependencies.
   */
  private function hooks(FlagServiceInterface $flag_service): ForumAvisoHooks {
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $access_policy = new ForumNoticeAccessPolicy(
      $entity_type_manager,
      new DomainPurposeManager(
        $entity_type_manager,
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
    );
    $avisos = new AvisoService(
      $entity_type_manager,
      $flag_service,
      $access_policy,
    );
    return new ForumAvisoHooks($avisos);
  }

}
