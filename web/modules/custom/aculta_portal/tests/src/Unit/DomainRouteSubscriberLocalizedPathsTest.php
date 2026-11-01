<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\EventSubscriber\DomainRouteSubscriber;
use Drupal\aculta_portal\EventSubscriber\SocialAuthCallbackRouteSubscriber;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests Portuguese canonical paths for public contrib routes.
 *
 * @coversDefaultClass \Drupal\aculta_portal\EventSubscriber\DomainRouteSubscriber
 */
#[Group('aculta_portal')]
final class DomainRouteSubscriberLocalizedPathsTest extends UnitTestCase {

  /**
   * Replaces upstream paths while preserving route IDs and route count.
   */
  public function testLocalizedPathsReplaceUpstreamPathsWithoutAliases(): void {
    $expected = [
      'entity.group.join' => '/grupo/{group}/entrar',
      'entity.group.leave' => '/grupo/{group}/sair',
      'lms.course.start' => '/curso/{group}/iniciar',
      'lms.course.reset_test' => '/curso/{group}/reiniciar-avaliacao',
      'lms.group.self_results' => '/grupo/{group}/resultados-curso',
      'lms.answer.details' => '/resposta/{lms_answer}/{js}/detalhes',
      'entity.lms_answer.edit_form' => '/resposta/{lms_answer}/avaliar',
      'profile.user_page.single' => '/identidade/{user}/{profile_type}',
      'profile.user_page.multiple' => '/identidade/{user}/{profile_type}/lista',
      'profile.user_page.add_form' => '/identidade/{user}/{profile_type}/adicionar',
      'social_auth.network.redirect' => '/oauth/{network}',
      'social_auth.network.callback' => '/integracoes/{network}/retorno',
      'social_auth.user.profiles' => '/identidade/{user}/conexoes/oauth',
      'entity.webform_submission.user' => '/meus-formularios/{user}/{submission_view}',
    ];
    $routes = new RouteCollection();
    foreach ($expected as $name => $_path) {
      $routes->add($name, new Route('/upstream/' . str_replace('.', '/', $name)));
    }
    $routeNames = array_keys($expected);

    $subscriber = new DomainRouteSubscriber($this->createMock(ConfigFactoryInterface::class));
    $this->invokeAlterRoutes($subscriber, $routes);
    $this->invokeAlterRoutes(new SocialAuthCallbackRouteSubscriber(), $routes);

    self::assertSame($routeNames, array_keys($routes->all()));
    self::assertCount(count($expected), $routes);
    foreach ($expected as $name => $path) {
      self::assertSame($path, $routes->get($name)?->getPath(), $name);
    }
    foreach ([
      'lms.course.start',
      'lms.course.reset_test',
      'lms.group.self_results',
      'lms.answer.details',
      'entity.lms_answer.edit_form',
    ] as $name) {
      self::assertSame('courses', $routes->get($name)?->getOption('_aculta_domain_purpose'), $name);
    }
    foreach (['entity.group.join', 'entity.group.leave'] as $name) {
      self::assertNull($routes->get($name)?->getOption('_aculta_domain_purpose'), $name);
    }
    foreach ([
      'profile.user_page.single',
      'profile.user_page.multiple',
      'profile.user_page.add_form',
      'social_auth.user.profiles',
      'entity.webform_submission.user',
    ] as $name) {
      self::assertSame('account', $routes->get($name)?->getOption('_aculta_domain_purpose'), $name);
    }
  }

  /**
   * Invokes a route subscriber's protected alteration hook for a fixture.
   */
  private function invokeAlterRoutes(object $subscriber, RouteCollection $routes): void {
    $method = new \ReflectionMethod($subscriber, 'alterRoutes');
    $method->invoke($subscriber, $routes);
  }

}
