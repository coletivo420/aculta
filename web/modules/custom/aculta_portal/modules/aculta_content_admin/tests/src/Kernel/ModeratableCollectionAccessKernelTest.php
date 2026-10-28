<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_content_admin\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies native access filters dashboard collections and routes.
 */
#[Group('aculta_content_admin')]
#[RunTestsInSeparateProcesses]
final class ModeratableCollectionAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'file', 'field', 'node', 'node_storage_body_field', 'text', 'filter',
    'block_content', 'block_content_storage_body_field', 'menu_ui', 'views', 'aculta_faq',
    'aculta_content_admin',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('block_content');
    $this->installConfig([
      'system', 'user', 'filter', 'node_storage_body_field', 'block_content_storage_body_field',
      'node', 'block_content', 'aculta_faq', 'aculta_content_admin',
    ]);
  }

  /**
   * Areas denied by their own access rules stay hidden and deny direct access.
   */
  public function testDeniedFaqAreaIsHiddenAndDeniedByItsOwnRoute(): void {
    $user = User::create(['uid' => 2, 'name' => 'ordinary-editor']);
    $user->save();
    $registry = $this->container->get('aculta_content_admin.moderation_registry');
    $visible = array_column($registry->forAccount($user)['collections'], 'route');

    self::assertNotContains('aculta_faq.admin', $visible);
    self::assertFalse($this->container->get('access_manager')
      ->checkNamedRoute('aculta_faq.admin', [], $user, TRUE)->isAllowed());
    self::assertFalse($this->container->get('access_manager')
      ->checkNamedRoute('aculta_content_admin.overview', [], $user, TRUE)->isAllowed());
  }

  /**
   * A specific moderation permission exposes only its matching collection.
   */
  public function testFaqModeratorSeesFaqAndCanOpenIt(): void {
    $role = Role::create(['id' => 'faq_editor', 'label' => 'FAQ editor']);
    $role->grantPermission('administer aculta faq');
    $role->save();
    $user = User::create(['uid' => 2, 'name' => 'faq-editor', 'roles' => ['faq_editor']]);
    $user->save();

    $registry = $this->container->get('aculta_content_admin.moderation_registry');
    $visible = array_column($registry->forAccount($user)['collections'], 'route');
    self::assertContains('aculta_faq.admin', $visible);
    self::assertTrue($this->container->get('access_manager')
      ->checkNamedRoute('aculta_content_admin.overview', [], $user, TRUE)->isAllowed());
    self::assertTrue($this->container->get('access_manager')
      ->checkNamedRoute('aculta_faq.admin', [], $user, TRUE)->isAllowed());
  }

  /**
   * Page Views are discovered and keep their native permission requirement.
   */
  public function testViewCollectionsAreDiscoveredAndKeepNativeAccess(): void {
    $view = View::create([
      'id' => 'content_admin_access_test',
      'label' => 'Conteúdo de teste',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'access' => [
              'type' => 'perm',
              'options' => ['perm' => 'administer nodes'],
            ],
          ],
        ],
        'page' => [
          'id' => 'page',
          'display_plugin' => 'page',
          'display_options' => ['path' => 'painel-administrativo/teste-conteudo'],
        ],
      ],
    ]);
    $view->save();
    $this->container->get('router.builder')->rebuild();

    $reader = User::create(['uid' => 2, 'name' => 'content-reader']);
    $reader->save();
    $reader_routes = array_column(
      $this->container->get('aculta_content_admin.moderation_registry')->forAccount($reader)['collections'],
      'route',
    );
    self::assertNotContains('view.content_admin_access_test.page', $reader_routes);
    self::assertFalse($this->container->get('access_manager')->checkNamedRoute(
      'view.content_admin_access_test.page',
      [],
      $reader,
      TRUE,
    )->isAllowed());

    $role = Role::create(['id' => 'node_moderator', 'label' => 'Node moderator']);
    $role->grantPermission('administer nodes');
    $role->save();
    $moderator = User::create(['uid' => 3, 'name' => 'content-moderator', 'roles' => ['node_moderator']]);
    $moderator->save();
    $moderator_routes = array_column(
      $this->container->get('aculta_content_admin.moderation_registry')->forAccount($moderator)['collections'],
      'route',
    );
    self::assertContains('view.content_admin_access_test.page', $moderator_routes);
    self::assertTrue($this->container->get('access_manager')->checkNamedRoute(
      'view.content_admin_access_test.page',
      [],
      $moderator,
      TRUE,
    )->isAllowed());
  }

}
