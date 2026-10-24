<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\StudioHubPreviewController;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Tests authorized, optional-aware Studio hub discovery.
 */
#[CoversClass(StudioHubPreviewController::class)]
#[Group('aculta_studio')]
final class StudioHubPreviewControllerTest extends UnitTestCase {

  /**
   * The hub itself cannot be accessed by visitors or unauthorized members.
   */
  public function testHubRequiresAuthenticationAndPortalPermission(): void {
    $account = $this->account(FALSE, TRUE);
    $this->expectException(AccessDeniedHttpException::class);
    (new StudioHubPreviewController(
      new StudioExperienceManager(), $account, $this->routes([])
    ))->page();
  }

  /**
   * Only available, authorized and routed providers are presented.
   */
  public function testPreviewListsOnlyVisibleRoutedExperiences(): void {
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider('pessoal', 'Meu Espaço', TRUE, TRUE));
    $manager->addProvider($this->provider('marley', 'Marley', FALSE, TRUE));
    $manager->addProvider($this->provider('ganjalab', 'Ganjalab', TRUE, FALSE));
    $manager->addProvider($this->provider('we_sativa', 'WE SATIVA', TRUE, TRUE));

    $controller = new StudioHubPreviewController(
      $manager,
      $this->account(TRUE, TRUE),
      $this->routes([
        'aculta_studio.personal',
        'aculta_studio.marley',
        // No WE SATIVA route: provider must be skipped.
      ]),
    );
    $build = $controller->page();

    self::assertSame('studio-hub-preview', $build['#attributes']['data-portal-ui']);
    self::assertSame(0, $build['#cache']['max-age']);
    self::assertSame('item_list', $build['experiences']['#theme']);
    self::assertCount(1, $build['experiences']['#items']);
    $link = $build['experiences']['#items'][0];
    self::assertSame('Meu Espaço', $link['#title']);
    self::assertSame('aculta_studio.personal', $link['#url']->getRouteName());
  }

  /**
   * A registered provider with the wrong purpose is not advertised.
   */
  public function testProviderInAnotherPurposeIsHidden(): void {
    $manager = new StudioExperienceManager();
    $manager->addProvider($this->provider('forum', 'Forum', TRUE, TRUE, 'forum'));
    $build = (new StudioHubPreviewController(
      $manager, $this->account(TRUE, TRUE), $this->routes(['aculta_studio.forum'])
    ))->page();

    self::assertSame([], $build['experiences']['#items']);
  }

  /**
   * Creates an independent provider with controlled state.
   */
  private function provider(string $id, string $label, bool $available, bool $allowed, string $purpose = 'account'): StudioExperienceProviderInterface {
    return new class($id, $label, $available, $allowed, $purpose) implements StudioExperienceProviderInterface {

      public function __construct(
        private readonly string $idValue,
        private readonly string $labelValue,
        private readonly bool $availableValue,
        private readonly bool $allowedValue,
        private readonly string $purposeValue,
      ) {}

      public function id(): string {
        return $this->idValue;
      }

      public function label(): string {
        return $this->labelValue;
      }

      public function purpose(): string {
        return $this->purposeValue;
      }

      public function routeName(): string {
        return match ($this->idValue) {
          'pessoal' => 'aculta_studio.personal',
          'marley' => 'aculta_studio.home',
          'ganjalab' => 'aculta_lms.student_dashboard',
          default => 'aculta_studio.' . $this->idValue,
        };
      }

      public function available(): bool {
        return $this->availableValue;
      }

      public function access(AccountInterface $account): AccessResultInterface {
        return AccessResult::allowedIf($this->allowedValue);
      }

      public function build(): array {
        return [];
      }

    };
  }

  /**
   * Generates a fake route provider for the current installed routes.
   */
  private function routes(array $known): RouteProviderInterface {
    $provider = $this->createMock(RouteProviderInterface::class);
    $provider->method('getRouteByName')->willReturnCallback(
      static function (string $name) use ($known): Route {
        if (!in_array($name, $known, TRUE)) {
          throw new RouteNotFoundException($name);
        }
        return new Route('/studio/' . $name);
      }
    );
    return $provider;
  }

  /**
   * Creates an account stub for top-level hub access.
   */
  private function account(bool $authenticated, bool $permitted): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('hasPermission')->willReturn($permitted);
    return $account;
  }

}
