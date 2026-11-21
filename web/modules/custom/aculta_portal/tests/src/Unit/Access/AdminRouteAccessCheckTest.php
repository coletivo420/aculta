<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Access;

use Drupal\aculta_portal\Access\AdminRouteAccessCheck;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Routing\Route;

/**
 * Testa o controle de acesso exclusivo de administrador às rotas de backend.
 */
#[CoversClass(AdminRouteAccessCheck::class)]
final class AdminRouteAccessCheckTest extends UnitTestCase {

  private AdminRouteAccessCheck $accessCheck;

  protected function setUp(): void {
    parent::setUp();
    $this->accessCheck = new AdminRouteAccessCheck();
  }

  public function testAppliesToAdminRouteOption(): void {
    $route = new Route('/qualquer-caminho', [], [], ['_admin_route' => TRUE]);
    self::assertTrue($this->accessCheck->applies($route));

    $nonAdmin = new Route('/qualquer-caminho', [], [], ['_admin_route' => FALSE]);
    self::assertFalse($this->accessCheck->applies($nonAdmin));

    $unsetAdmin = new Route('/publico');
    self::assertFalse($this->accessCheck->applies($unsetAdmin));
  }

  public function testAppliesToAdminPrefixPaths(): void {
    $route1 = new Route('/painel-administrativo');
    self::assertTrue($this->accessCheck->applies($route1));

    $route2 = new Route('/painel-administrativo/forum/denuncias');
    self::assertTrue($this->accessCheck->applies($route2));

    $route3 = new Route('/admin');
    self::assertTrue($this->accessCheck->applies($route3));

    $route4 = new Route('/admin/structure/types');
    self::assertTrue($this->accessCheck->applies($route4));

    $routePublic = new Route('/forum/topicos/123');
    self::assertFalse($this->accessCheck->applies($routePublic));
  }

  public function testUid1AlwaysAllowed(): void {
    $route = new Route('/painel-administrativo');
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('1');
    $account->method('getRoles')->willReturn(['authenticated']);

    $result = $this->accessCheck->access($route, $account);
    self::assertTrue($result->isAllowed());
    self::assertFalse($result->isForbidden());
  }

  public function testAdministratorRoleAllowed(): void {
    $route = new Route('/painel-administrativo/configuracoes');
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn('42');
    $account->method('getRoles')->willReturn(['authenticated', 'administrator']);

    $result = $this->accessCheck->access($route, $account);
    self::assertTrue($result->isAllowed());
    self::assertFalse($result->isForbidden());
  }

  public function testNonAdministratorRolesForbidden(): void {
    $route = new Route('/painel-administrativo/forum');

    // Moderador
    $moderador = $this->createMock(AccountInterface::class);
    $moderador->method('id')->willReturn('10');
    $moderador->method('getRoles')->willReturn(['authenticated', 'moderador']);
    $resultMod = $this->accessCheck->access($route, $moderador);
    self::assertTrue($resultMod->isForbidden());
    self::assertFalse($resultMod->isAllowed());

    // Editor de conteúdo
    $editor = $this->createMock(AccountInterface::class);
    $editor->method('id')->willReturn('11');
    $editor->method('getRoles')->willReturn(['authenticated', 'content_editor']);
    $resultEd = $this->accessCheck->access($route, $editor);
    self::assertTrue($resultEd->isForbidden());

    // Educador LMS
    $teacher = $this->createMock(AccountInterface::class);
    $teacher->method('id')->willReturn('12');
    $teacher->method('getRoles')->willReturn(['authenticated', 'lms_teacher']);
    $resultTeacher = $this->accessCheck->access($route, $teacher);
    self::assertTrue($resultTeacher->isForbidden());

    // Anônimo
    $anon = $this->createMock(AccountInterface::class);
    $anon->method('id')->willReturn('0');
    $anon->method('getRoles')->willReturn(['anonymous']);
    $resultAnon = $this->accessCheck->access($route, $anon);
    self::assertTrue($resultAnon->isForbidden());
  }

}
