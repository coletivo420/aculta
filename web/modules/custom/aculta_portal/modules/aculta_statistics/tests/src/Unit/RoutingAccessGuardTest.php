<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guarda das rotas: toda rota administrativa do módulo exige a permissão própria, e nenhuma é pública por engano.
 *
 * O purpose `main` das rotas `/painel-administrativo/**` é dado pela política de domínio (path rule em
 * DomainRouteSubscriber, docs/portal/ADMIN-DOMAIN-POLICY.md), e foi conferido no Runtime de teste.
 */
#[Group('aculta_statistics')]
final class RoutingAccessGuardTest extends TestCase {

  /**
   * Rotas do painel administrativo usam a permissão do módulo e nenhuma rota pública usa caminho administrativo.
   */
  public function testAdministrativeRoutesRequireTheModulePermission(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_statistics.routing.yml');

    foreach ($routes as $name => $route) {
      $path = (string) ($route['path'] ?? '');
      if (str_starts_with($path, '/painel-administrativo')) {
        $this->assertSame('administer aculta_statistics', $route['requirements']['_permission'] ?? NULL, "Rota $name precisa da permissão do módulo.");
      }
    }
  }

  /**
   * A rota pública (beacon da wiki) é a única sem permissão e não tem caminho administrativo.
   */
  public function testOnlyTheWikiBeaconIsPublic(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/aculta_statistics.routing.yml');
    $public = array_keys(array_filter($routes, static fn (array $route): bool => ($route['requirements']['_access'] ?? NULL) === 'TRUE'));

    $this->assertSame(['aculta_statistics.wiki_read'], $public);
  }

}
