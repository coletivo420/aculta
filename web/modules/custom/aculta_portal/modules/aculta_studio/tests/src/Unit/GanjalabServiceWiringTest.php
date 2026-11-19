<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\GanjalabController;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\aculta_studio\Experience\StudioExperienceProviderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Yaml\Yaml;

/**
 * Compiles the real Ganjalab controller service and exercises its guards.
 *
 * The previous version asserted the YAML argument strings. This resolves the
 * service from its committed definition and verifies the two security guards
 * that protect the learner dashboard: unavailable experience is a 404 and
 * denied domain access is a 403.
 */
#[Group('aculta_studio')]
#[CoversClass(GanjalabController::class)]
final class GanjalabServiceWiringTest extends UnitTestCase {

  /**
   * The committed definition resolves to a GanjalabController instance.
   */
  public function testGanjalabControllerServiceIsDefined(): void {
    $controller = $this->controller($this->manager());
    self::assertInstanceOf(GanjalabController::class, $controller);
  }

  /**
   * A missing provider is reported as not found.
   */
  public function testMissingProviderIsNotFound(): void {
    $this->expectException(NotFoundHttpException::class);
    $this->controller($this->manager())->student();
  }

  /**
   * An unavailable provider is reported as not found.
   */
  public function testUnavailableProviderIsNotFound(): void {
    $manager = $this->manager($this->provider(['available' => FALSE]));
    $this->expectException(NotFoundHttpException::class);
    $this->controller($manager)->student();
  }

  /**
   * A provider the account cannot access is forbidden, not rendered.
   */
  public function testDeniedExperienceAccessIsForbidden(): void {
    $manager = $this->manager($this->provider([
      'available' => TRUE,
      'purpose' => 'courses',
      'access' => AccessResult::forbidden(),
    ]));
    $this->expectException(AccessDeniedHttpException::class);
    $this->controller($manager)->student();
  }

  /**
   * Builds the controller from the committed service definition.
   */
  private function controller(StudioExperienceManager $manager): GanjalabController {
    $services = Yaml::parseFile(
      dirname(__DIR__, 3) . '/aculta_studio.services.yml',
    )['services'];
    self::assertArrayHasKey('aculta_studio.ganjalab_controller', $services);
    $definition = $services['aculta_studio.ganjalab_controller'];

    $container = new ContainerBuilder();
    $container->setDefinition('aculta_studio.ganjalab_controller', (new Definition(
      $definition['class'],
      array_map(
        static fn (string $argument): Reference => new Reference(substr($argument, 1)),
        $definition['arguments'],
      ),
    ))->setPublic(TRUE));
    foreach (['aculta_studio.experience_manager', 'current_user'] as $dependency) {
      $container->setDefinition($dependency, (new Definition())->setSynthetic(TRUE)->setPublic(TRUE));
    }
    $container->compile();
    $container->set('aculta_studio.experience_manager', $manager);
    $container->set('current_user', $this->createMock(AccountInterface::class));

    return $container->get('aculta_studio.ganjalab_controller');
  }

  /**
   * Builds the real experience registry with an optional Ganjalab provider.
   */
  private function manager(?StudioExperienceProviderInterface $provider = NULL): StudioExperienceManager {
    $manager = new StudioExperienceManager();
    if ($provider !== NULL) {
      $manager->addProvider($provider);
    }
    return $manager;
  }

  /**
   * Builds a Ganjalab provider mock.
   *
   * @param array<string, mixed> $behaviour
   *   Keys: available, purpose, access.
   */
  private function provider(array $behaviour): StudioExperienceProviderInterface {
    $provider = $this->createMock(StudioExperienceProviderInterface::class);
    $provider->method('id')->willReturn('ganjalab');
    $provider->method('available')->willReturn($behaviour['available'] ?? TRUE);
    $provider->method('purpose')->willReturn($behaviour['purpose'] ?? 'courses');
    $provider->method('access')->willReturn($behaviour['access'] ?? AccessResult::allowed());
    return $provider;
  }

}
