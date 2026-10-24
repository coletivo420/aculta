<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\AccountController;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Checks the account controller's fail-closed dispatch.
 */
#[CoversClass(AccountController::class)]
#[Group('aculta_studio')]
final class AccountControllerTest extends UnitTestCase {

  /**
   * An absent provider must not render a fallback account page.
   */
  public function testMissingProviderIsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    (new AccountController(new StudioExperienceManager(), $this->createMock(AccountInterface::class)))->page();
  }

  /**
   * An account denied by the provider cannot render account data.
   */
  public function testAccessDeniedDoesNotBuild(): void {
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider(FALSE));
    $this->expectException(AccessDeniedHttpException::class);
    (new AccountController($manager, $this->createMock(AccountInterface::class)))->page();
  }

  /**
   * The controller returns only its authorized provider's render array.
   */
  public function testAuthorizedProviderBuilds(): void {
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider(TRUE));
    self::assertSame(['#type' => 'container', '#account' => TRUE],
      (new AccountController($manager, $this->createMock(AccountInterface::class)))->page());
  }

  /**
   * Creates a provider with predictable access for the controller test.
   */
  private function provider(bool $allowed): StudioExperienceProviderInterface {
    return new class($allowed) implements StudioExperienceProviderInterface {

      public function __construct(private readonly bool $allowed) {}

      public function id(): string {
        return 'conta';
      }

      public function label(): string {
        return 'Minha Conta';
      }

      public function purpose(): string {
        return 'account';
      }

      public function routeName(): string {
        return 'aculta_studio.account';
      }

      public function available(): bool {
        return TRUE;
      }

      public function access(AccountInterface $account): AccessResultInterface {
        return $this->allowed ? AccessResult::allowed() : AccessResult::forbidden();
      }

      public function build(): array {
        return ['#type' => 'container', '#account' => TRUE];
      }

    };
  }

}
