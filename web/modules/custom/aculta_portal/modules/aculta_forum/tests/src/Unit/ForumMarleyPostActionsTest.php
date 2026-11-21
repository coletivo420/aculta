<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumMarleyPostActions;
use Drupal\aculta_forum\Service\ForumPostMover;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Contrato da ação Marley de mover publicação: acesso negado sem conta ou publicação,
 * e rota do frontend sem caminho administrativo.
 */
#[Group('aculta_forum')]
final class ForumMarleyPostActionsTest extends UnitTestCase {

  /**
   * Conta anônima não move publicação, sem consultar o mover.
   */
  public function testAnonymousAccountIsDeniedWithoutConsultingMover(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);
    $service = $this->service();

    self::assertFalse($service->access($account, NULL)->isAllowed());
  }

  /**
   * Sem publicação carregada, a ação é negada.
   */
  public function testMissingPostIsDenied(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);

    self::assertFalse($this->service()->access($account, NULL)->isAllowed());
  }

  /**
   * A rota Marley usa o controle de acesso do serviço e nunca aponta ao painel administrativo.
   */
  public function testMarleyPostMoveRouteIsFrontendScoped(): void {
    $root = dirname(__DIR__, 3);
    $routes = Yaml::parseFile($root . '/aculta_forum.routing.yml');
    self::assertIsArray($routes);
    $route = $routes['aculta_forum.marley_post_move'] ?? [];
    self::assertIsArray($route);

    $defaults = $route['defaults'] ?? [];
    self::assertIsArray($defaults);
    self::assertSame('\Drupal\aculta_forum\Form\ForumPostMoveForm', $defaults['_form'] ?? NULL);

    $requirements = $route['requirements'] ?? [];
    self::assertIsArray($requirements);
    self::assertSame('aculta_forum.marley_post_actions:access', $requirements['_custom_access'] ?? NULL);

    $path = $route['path'] ?? '';
    self::assertIsString($path);
    self::assertStringStartsWith('/marley/forum/publicacao/', $path);
    self::assertStringNotContainsString('/painel-administrativo/', $path);
  }

  /**
   * Serviço com o mover real não instanciado: os casos testados não o utilizam.
   */
  private function service(): ForumMarleyPostActions {
    $mover = (new \ReflectionClass(ForumPostMover::class))->newInstanceWithoutConstructor();
    return new ForumMarleyPostActions($mover);
  }

}
