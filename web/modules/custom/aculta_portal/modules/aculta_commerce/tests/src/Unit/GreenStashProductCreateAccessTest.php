<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Unit;

use Drupal\aculta_commerce\Access\GreenStashAccessPolicy;
use Drupal\aculta_commerce\Form\GreenStashProductCreateForm;
use Drupal\aculta_commerce\Store\GreenStashStoreResolver;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_commerce\Experience\GreenStashExperienceProvider;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests that product creation requires both Studio and Commerce access.
 *
 * @group aculta_commerce
 */
final class GreenStashProductCreateAccessTest extends UnitTestCase {

  /**
   * Denies creation when Green Stash is not available to the account.
   */
  public function testDeniedExperienceStopsBeforeCommerceAccess(): void {
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::never())->method('getAccessControlHandler');
    $entities->expects(self::never())->method('getStorage');
    $account = $this->createMock(AccountInterface::class);
    $form = new GreenStashProductCreateForm(
      $entities,
      $account,
      new StudioExperienceManager(),
      new GreenStashStoreResolver($entities),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $form->buildForm([], new FormState());
  }

  /**
   * Denies creation when Commerce denies product creation to the account.
   */
  public function testDeniedCommerceCreateAccessStopsBeforeStoreLookup(): void {
    $handler = $this->createMock(EntityAccessControlHandlerInterface::class);
    $handler->expects(self::once())
      ->method('createAccess')
      ->with('produto_loja', self::isInstanceOf(AccountInterface::class))
      ->willReturn(AccessResult::forbidden());
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->expects(self::once())
      ->method('getAccessControlHandler')
      ->with('commerce_product')
      ->willReturn($handler);
    $entities->expects(self::never())->method('getStorage');

    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('access aculta green stash')->willReturn(TRUE);
    $experiences = new StudioExperienceManager();
    $experiences->addProvider(new GreenStashExperienceProvider(new GreenStashAccessPolicy()));
    $form = new GreenStashProductCreateForm(
      $entities,
      $account,
      $experiences,
      new GreenStashStoreResolver($entities),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $form->buildForm([], new FormState());
  }

}
