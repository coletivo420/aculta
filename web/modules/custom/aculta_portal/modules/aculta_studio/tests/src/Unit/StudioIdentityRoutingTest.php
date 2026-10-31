<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures Studio and LMS expose only their canonical routes.
 */
#[CoversNothing]
#[Group('aculta_studio')]
final class StudioIdentityRoutingTest extends UnitTestCase {

  /**
   * Account surfaces and domain suites each have one canonical owner.
   */
  public function testCanonicalPathsHaveOneOwner(): void {
    $routes = $this->routes();
    $expected = [
      '/conta' => 'aculta_studio.account',
      '/conta/dados' => 'aculta_studio.account_data',
      '/conta/endereco' => 'aculta_studio.account_address',
      '/conta/documentos' => 'aculta_studio.account_documents',
      '/conta/seguranca' => 'aculta_studio.account_security',
      '/conta/seguranca/senha' => 'aculta_studio.account_password',
      '/conta/conexoes' => 'aculta_studio.account_connections',
      '/conta/pedidos' => 'aculta_studio.account_orders',
      '/conta/apoio' => 'aculta_studio.account_support',
      '/conta/preferencias' => 'aculta_studio.account_preferences',
      '/perfil' => 'aculta_studio.profile',
      '/perfil/apelido' => 'aculta_studio.profile_nickname',
      '/perfil/privacidade' => 'aculta_studio.profile_privacy',
      '/perfil/bio' => 'aculta_studio.profile_bio',
      '/perfil/redes/{rede}' => 'aculta_studio.profile_social',
      '/marley' => 'aculta_studio.home',
      '/marley/{aba}' => 'aculta_studio.area',
      '/marley/{aba}/{secao}' => 'aculta_studio.section',
      '/marley/{aba}/{secao}/{sub}' => 'aculta_studio.tab',
      '/studio/we-sativa' => 'aculta_studio.we_sativa',
      '/studio/we-sativa/inicio' => 'aculta_studio.we_sativa_start',
      '/ganjalab' => 'aculta_lms.student_dashboard',
      '/ganjalab/cursos/novo' => 'aculta_lms.course_create',
      '/salvos' => 'aculta_studio.salvos',
      '/studio/green-stash' => 'aculta_commerce.green_stash',
      '/studio/green-stash/{secao}' => 'aculta_commerce.green_stash_area',
      '/studio/green-stash/produtos/novo' => 'aculta_commerce.green_stash_product_create',
      '/studio/green-stash/produtos/{produto}' => 'aculta_commerce.green_stash_product_detail',
      '/studio/green-stash/envios/{aculta_shipment}/transicao/{transition}' => 'aculta_commerce.green_stash_shipment_transition',
      '/conta/mercado-pago/planos' => 'aculta_pagamentos_mercadopago.studio_plans',
      '/studio/we-sativa/propostas/{proposal}/decidir' => 'aculta_wiki.proposal_decide',
    ];

    foreach ($expected as $path => $owner) {
      $matches = array_filter($routes, static fn (array $route): bool => ($route['path'] ?? NULL) === $path);
      self::assertCount(1, $matches, sprintf('Canonical path %s must have exactly one route.', $path));
      self::assertSame($path, $routes[$owner]['path']);
    }
  }

  /**
   * Conta and profile are restricted to account; suites use owner domains.
   */
  public function testAccountProfileAndSuiteAccess(): void {
    $routes = $this->routes();
    self::assertArrayNotHasKey('aculta_studio.hub', $routes);
    $accountRoutes = [
      'aculta_studio.account',
      'aculta_studio.account_data',
      'aculta_studio.account_address',
      'aculta_studio.account_documents',
      'aculta_studio.account_security',
      'aculta_studio.account_password',
      'aculta_studio.account_connections',
      'aculta_studio.account_orders',
      'aculta_studio.account_support',
      'aculta_studio.account_preferences',
    ];
    foreach ($accountRoutes as $id) {
      $route = $routes[$id];
      self::assertSame('account', $route['options']['_aculta_domain_purpose']);
      self::assertSame('access aculta portal', $route['requirements']['_permission']);
      self::assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
    }
    $profileRoutes = [
      'aculta_studio.profile',
      'aculta_studio.profile_nickname',
      'aculta_studio.profile_privacy',
      'aculta_studio.profile_bio',
      'aculta_studio.profile_social',
    ];
    foreach ($profileRoutes as $id) {
      $route = $routes[$id];
      self::assertSame('account', $route['options']['_aculta_domain_purpose']);
      self::assertSame('aculta_studio.profile_access:access', $route['requirements']['_custom_access']);
    }
    self::assertSame('account', $routes['aculta_studio.home']['options']['_aculta_domain_purpose']);
    self::assertSame('wiki', $routes['aculta_studio.we_sativa']['options']['_aculta_domain_purpose']);
    self::assertSame('wiki', $routes['aculta_studio.we_sativa_start']['options']['_aculta_domain_purpose']);
    self::assertSame('courses', $routes['aculta_lms.student_dashboard']['options']['_aculta_domain_purpose']);
    self::assertSame('courses', $routes['aculta_lms.course_create']['options']['_aculta_domain_purpose']);
    self::assertSame('create lms_course group', $routes['aculta_lms.course_create']['requirements']['_permission']);
    self::assertTrue($routes['aculta_lms.course_create']['options']['no_cache']);
    self::assertSame('aculta_studio.course_authoring_controller:create', $routes['aculta_lms.course_create']['defaults']['_controller']);
    self::assertSame('wiki', $routes['aculta_wiki.proposal_decide']['options']['_aculta_domain_purpose']);
    foreach ([
      'aculta_commerce.green_stash',
      'aculta_commerce.green_stash_area',
      'aculta_commerce.green_stash_product_create',
      'aculta_commerce.green_stash_product_detail',
      'aculta_commerce.green_stash_shipment_transition',
    ] as $id) {
      self::assertSame('shop', $routes[$id]['options']['_aculta_domain_purpose']);
    }
    self::assertSame('account', $routes['aculta_studio.salvos']['options']['_aculta_domain_purpose']);
    self::assertSame('/conta/mercado-pago/planos', $routes['aculta_pagamentos_mercadopago.studio_plans']['path']);
    $noCacheRoutes = array_slice($accountRoutes, 2);
    foreach ($noCacheRoutes as $id) {
      self::assertTrue($routes[$id]['options']['no_cache']);
    }
    self::assertTrue($routes['aculta_studio.profile_nickname']['options']['no_cache']);
    self::assertSame('\\Drupal\\aculta_user\\Form\\NicknameForm', $routes['aculta_studio.profile_nickname']['defaults']['_form']);
    self::assertSame('aculta_studio.marley_controller:page',
      $routes['aculta_studio.home']['defaults']['_controller']);
  }

  /**
   * Removed development aliases must not be registered as live routes.
   */
  public function testNoObsoleteStudioOrStudentAliases(): void {
    $routes = $this->routes();
    foreach ($routes as $id => $definition) {
      self::assertDoesNotMatchRegularExpression('/(?:legacy|old_)/i', $id);
    }
    $obsolete = [
      '/studio',
      '/studio/marley',
      '/studio/ganjalab',
      '/studio/salvos',
      '/studio/conta',
      '/studio/perfil',
      '/studio/pessoal',
      '/studio/Marley',
      '/studio/inicio',
      '/ganjalab420',
      '/minha-aprendizagem',
      '/wesativa',
      '/wesativa/inicio',
      '/wesativa/propostas/{proposal}/decidir',
      '/greenstash',
      '/greenstash/{secao}',
      '/studio/ganjalab420',
      '/studio/ganjalab/{aba}/{secao}/{sub}',
    ];
    foreach ($obsolete as $path) {
      $matches = array_filter($routes, static fn (array $route): bool => ($route['path'] ?? NULL) === $path);
      self::assertCount(0, $matches, sprintf('%s must not resolve as an alias.', $path));
    }
  }

  /**
   * Minha Conta stays at /conta-legada; the ACCOUNT root resolves to Conta.
   */
  public function testLegacyAccountRouteStillExistsButIsNotExposedAsAlias(): void {
    $root = dirname(__DIR__, 3);
    $portal = Yaml::decode(file_get_contents($root . '/../../aculta_portal.routing.yml'));
    self::assertSame('/conta-legada', $portal['aculta_portal.dashboard']['path']);
    // The technical /minha-conta path is blocked before routing, and the
    // Account purpose root is internally resolved to /conta, never /studio.
    $subscriber = file_get_contents($root . '/../../src/EventSubscriber/DomainPurposeRequestSubscriber.php');
    self::assertStringContainsString("\$accountPath === '/minha-conta'", $subscriber);
    self::assertStringContainsString('Response::HTTP_NOT_FOUND', $subscriber);
    self::assertStringNotContainsString('aculta_studio.hub', $subscriber);
  }

  /**
   * Reads the installed Studio/LMS route definitions.
   *
   * @return array<string, array<string, mixed>>
   *   Combined route maps.
   */
  private function routes(): array {
    $module = dirname(__DIR__, 3);
    return Yaml::decode(file_get_contents($module . '/aculta_studio.routing.yml'))
      + Yaml::decode(file_get_contents($module . '/../aculta_lms/aculta_lms.routing.yml'))
      + Yaml::decode(file_get_contents($module . '/../aculta_wiki/aculta_wiki.routing.yml'))
      + Yaml::decode(file_get_contents($module . '/../aculta_commerce/aculta_commerce.routing.yml'))
      + Yaml::decode(file_get_contents($module . '/../aculta_pagamentos_mercadopago/aculta_pagamentos_mercadopago.routing.yml'));
  }

}
