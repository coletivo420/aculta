<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\WeSativaController;
use Drupal\aculta_studio\Experience\StartPageExperienceProviderInterface;
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
 * Tests the Studio entrypoint delegates WE SATIVA start pages to its owner.
 */
#[CoversClass(WeSativaController::class)]
#[Group('aculta_studio')]
final class WeSativaControllerTest extends UnitTestCase {

  /**
   * The owner start page renders when access is allowed.
   */
  public function testStartPageRequiresAvailableProviderAndOwnerAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $provider = $this->provider(AccessResult::allowed());
    $manager = new StudioExperienceManager();
    $manager->addProvider($provider);

    $build = (new WeSativaController($manager, $account))->start();

    self::assertSame(['#type' => 'container', '#owner' => 'wiki'], $build);
  }

  /**
   * Providers without a start-page contract remain unavailable at this route.
   */
  public function testStartPageIsHiddenWhenProviderHasNoStartPageContract(): void {
    $account = $this->createMock(AccountInterface::class);
    $manager = new StudioExperienceManager();
    $manager->addProvider(new class implements StudioExperienceProviderInterface {

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'we_sativa';
      }

      /**
       * {@inheritdoc}
       */
      public function label(): string {
        return 'WE SATIVA';
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
        return 'aculta_studio.we_sativa';
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
        return AccessResult::allowed();
      }

      /**
       * {@inheritdoc}
       */
      public function build(): array {
        return [];
      }

    });

    $this->expectException(NotFoundHttpException::class);
    (new WeSativaController($manager, $account))->start();
  }

  /**
   * An owner access denial prevents rendering the start page.
   */
  public function testStartPageDeniesAccountsRejectedByTheOwner(): void {
    $account = $this->createMock(AccountInterface::class);
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider(AccessResult::forbidden()));

    $this->expectException(AccessDeniedHttpException::class);
    (new WeSativaController($manager, $account))->start();
  }

  /**
   * Creates a test provider with a controlled access result.
   */
  private function provider(AccessResultInterface $access): StudioExperienceProviderInterface&StartPageExperienceProviderInterface {
    return new class($access) implements StudioExperienceProviderInterface, StartPageExperienceProviderInterface {

      public function __construct(private readonly AccessResultInterface $result) {}

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'we_sativa';
      }

      /**
       * {@inheritdoc}
       */
      public function label(): string {
        return 'WE SATIVA';
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
        return 'aculta_studio.we_sativa';
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
        return $this->result;
      }

      /**
       * {@inheritdoc}
       */
      public function build(): array {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function buildStart(): array {
        return ['#type' => 'container', '#owner' => 'wiki'];
      }

    };
  }

}
