<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
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
    (new AccountController(new StudioExperienceManager(), $this->createMock(AccountInterface::class), $this->domains()))->page();
  }

  /**
   * An account denied by the provider cannot render account data.
   */
  public function testAccessDeniedDoesNotBuild(): void {
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider(FALSE));
    $this->expectException(AccessDeniedHttpException::class);
    (new AccountController($manager, $this->createMock(AccountInterface::class), $this->domains()))->page();
  }

  /**
   * The controller returns only its authorized provider's render array.
   */
  public function testAuthorizedProviderBuilds(): void {
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider(TRUE));
    $page = (new AccountController($manager, $this->createMock(AccountInterface::class), $this->domains()))->page();

    self::assertTrue($page['#account']);
    self::assertArrayHasKey('#experiences', $page);
  }

  /**
   * Creates a provider with predictable access for the controller test.
   */
  private function provider(bool $allowed): StudioExperienceProviderInterface {
    return new class($allowed) implements StudioExperienceProviderInterface {

      public function __construct(private readonly bool $allowed) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'conta';
      }

      /**
       * {@inheritdoc}
       */
      public function label(): string {
        return 'Minha Conta';
      }

      /**
       * {@inheritdoc}
       */
      public function purpose(): string {
        return 'account';
      }

      /**
       * {@inheritdoc}
       */
      public function routeName(): string {
        return 'aculta_studio.account';
      }

      /**
       * {@inheritdoc}
       */
      public function available(): bool {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function access(AccountInterface $account): AccessResultInterface {
        return $this->allowed ? AccessResult::allowed() : AccessResult::forbidden();
      }

      /**
       * {@inheritdoc}
       */
      public function build(): array {
        return ['#type' => 'container', '#account' => TRUE];
      }

    };
  }

  /**
   * Creates a Domain resolver for controller tests.
   */
  private function domains(): DomainSiteManager {
    return new DomainSiteManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
