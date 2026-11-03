<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_lms\Access\AnswerAttachmentAccessCheck;
use Drupal\aculta_lms\Access\UploadedAnswerAccessCheck;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UploadedAnswerAccessCheck::class)]
final class UploadedAnswerAccessCheckTest extends UnitTestCase {

  public function testLegacyEntryPointDelegatesToTheAcultaAnswerPolicy(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $entity = $this->createMock(EntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('getCacheContexts')->willReturn([]);
    $entity->method('getCacheTags')->willReturn([]);
    $entity->method('getCacheMaxAge')->willReturn(-1);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->expects(self::never())->method('getStorage');
    $policy = new AnswerAttachmentAccessCheck($entityTypeManager, $this->createMock(LearningEligibilityInterface::class));

    $result = (new UploadedAnswerAccessCheck($policy))->access($entity, $account);

    self::assertInstanceOf(AccessResult::class, $result);
    self::assertFalse($result->isAllowed());
  }

}
