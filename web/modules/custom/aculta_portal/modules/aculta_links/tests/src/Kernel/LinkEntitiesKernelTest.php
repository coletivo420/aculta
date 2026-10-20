<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Kernel;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\KernelTests\KernelTestBase;
use Drupal\aculta_links\Entity\Link;
use Drupal\aculta_links\Entity\LinkItem;
use Drupal\aculta_links\Entity\LinkPage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests storage, ownership, references, and revisions for the Links entities.
 */
#[Group('aculta_links')]
#[RunTestsInSeparateProcesses]
final class LinkEntitiesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'link',
    'views',
    'aculta_links',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_link');
    $this->installEntitySchema('aculta_link_page');
    $this->installEntitySchema('aculta_link_item');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user']);
  }

  /**
   * Tests page composition, owner access, publication, and link revision.
   */
  public function testEntityStorageAndOwnerBoundAccess(): void {
    $route_provider = $this->container->get('router.route_provider');
    $dashboard_route = $route_provider->getRouteByName('aculta_links.dashboard');
    self::assertSame('/painel-administrativo/links', $dashboard_route->getPath());
    self::assertTrue($dashboard_route->getOption('_admin_route'));
    $page_add_route = $route_provider->getRouteByName('entity.aculta_link_page.add_form');
    self::assertSame('/painel-administrativo/links/pagina/adicionar', $page_add_route->getPath());
    self::assertTrue($page_add_route->getOption('_admin_route'));
    $link_revision_route = $route_provider->getRouteByName('entity.aculta_link.revision');
    self::assertSame(
      '/painel-administrativo/links/destino/{aculta_link}/revisao/{aculta_link_revision}/visualizar',
      $link_revision_route->getPath(),
    );
    $item_add_route = $route_provider->getRouteByName('entity.aculta_link_item.add_form');
    self::assertSame('/painel-administrativo/links/item/adicionar', $item_add_route->getPath());
    $item_revision_route = $route_provider->getRouteByName('entity.aculta_link_item.revision');
    self::assertSame(
      '/painel-administrativo/links/item/{aculta_link_item}/revisao/{aculta_link_item_revision}/visualizar',
      $item_revision_route->getPath(),
    );

    $owner_role = Role::create(['id' => 'links_owner', 'label' => 'Links owner']);
    $owner_role->grantPermission('manage own aculta links');
    $owner_role->save();
    $other_role = Role::create(['id' => 'links_other', 'label' => 'Links other']);
    $other_role->grantPermission('manage own aculta links');
    $other_role->save();
    $owner = User::create(['name' => 'links-owner', 'status' => TRUE]);
    $owner->addRole('links_owner');
    $owner->save();
    $other = User::create(['name' => 'links-other', 'status' => TRUE]);
    $other->addRole('links_other');
    $other->save();

    $link = Link::create([
      'title' => 'Site institucional',
      'uri' => ['uri' => 'https://example.org/'],
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    $link->save();
    $first_revision_id = $link->getRevisionId();

    $page = LinkPage::create([
      'title' => 'Página cultural',
      'slug' => 'pagina-cultural',
      'description' => 'Apresentação pública.',
      'uid' => $owner->id(),
    ]);
    $page->save();

    $item = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Visite nosso site',
      'weight' => 0,
      'status' => TRUE,
    ]);
    $item->save();

    self::assertFalse($page->isPublished(), 'Micropáginas começam despublicadas.');
    self::assertTrue($link->access('update', $owner));
    self::assertFalse($link->access('view', $other));
    self::assertTrue($page->access('update', $owner));
    self::assertFalse($page->access('update', $other));
    self::assertTrue($item->access('update', $owner));
    self::assertFalse($item->access('update', $other));
    self::assertSame((int) $page->id(), (int) $item->get('page')->target_id);
    self::assertSame((int) $link->id(), (int) $item->get('link')->target_id);

    $link->set('title', 'Site institucional atualizado');
    $link->setNewRevision(TRUE);
    $link->setRevisionLogMessage('Atualiza o rótulo do destino.');
    $link->save();

    self::assertNotSame($first_revision_id, $link->getRevisionId());
    $revision_storage = $this->container->get('entity_type.manager')->getStorage('aculta_link');
    $previous_revision = $revision_storage->loadRevision($first_revision_id);
    self::assertSame('Site institucional', $previous_revision->label());
  }

}
