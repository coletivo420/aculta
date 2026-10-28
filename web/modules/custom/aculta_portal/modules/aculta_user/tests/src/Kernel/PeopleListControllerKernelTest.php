<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_user\Controller\PeopleListController;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\UserSession;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Yaml\Yaml;

/** Verifies the minimal people listing and its administrative access boundary. */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class PeopleListControllerKernelTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'field', 'filter', 'views', 'profile'];

  protected function setUp(): void {
    parent::setUp();
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_user\\', dirname(__DIR__, 3) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_portal\\', dirname(__DIR__, 5) . '/src', TRUE);
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user', 'filter', 'profile']);

    ProfileType::create(['id' => 'authenticated', 'label' => 'Perfil autenticado'])->save();
    foreach (['field_first_name', 'field_nickname'] as $fieldName) {
      FieldStorageConfig::create([
        'entity_type' => 'profile',
        'field_name' => $fieldName,
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => 'authenticated',
        'field_name' => $fieldName,
        'label' => $fieldName,
      ])->save();
    }
  }

  public function testListingContainsOnlyApprovedIdentityFields(): void {
    $account = User::create([
      'name' => 'private-person@example.test',
      'mail' => 'private-person@example.test',
      'status' => 1,
    ]);
    $account->save();
    Profile::create([
      'type' => 'authenticated',
      'uid' => $account->id(),
      'status' => TRUE,
      'field_first_name' => 'Pessoa Exemplo',
      'field_nickname' => 'pessoa-exemplo',
    ])->save();

    $build = (new PeopleListController($this->container->get('entity_type.manager')))->listing();
    $output = serialize($build);
    self::assertStringContainsString('Pessoa Exemplo', $output);
    self::assertStringContainsString('pessoa-exemplo', $output);
    self::assertStringContainsString('Ativa', $output);
    self::assertStringNotContainsString('private-person@example.test', $output);
    self::assertStringNotContainsString('mail', $output);
    self::assertStringNotContainsString('documento', $output);
    self::assertStringNotContainsString('endereço', $output);
  }

  public function testCommonAccountCannotAccessPeopleListRoute(): void {
    $modulePath = DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_user/aculta_user.routing.yml';
    $definition = Yaml::parseFile($modulePath)['aculta_user.people'];
    $route = new Route($definition['path'], $definition['defaults'], $definition['requirements']);
    $request = Request::create('/painel-administrativo/pessoas');
    $request->attributes->set('_route', 'aculta_user.people');
    $request->attributes->set('_route_object', $route);
    $result = $this->container->get('access_manager')->check(
      RouteMatch::createFromRequest($request),
      new UserSession(['uid' => 42, 'roles' => ['authenticated']]),
      $request,
      TRUE,
    );

    self::assertFalse($result->isAllowed());
  }

}
