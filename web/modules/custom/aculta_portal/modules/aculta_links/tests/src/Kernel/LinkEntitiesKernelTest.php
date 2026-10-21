<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_links\Kernel;

use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormState;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_links\Entity\Link;
use Drupal\aculta_links\Entity\LinkItem;
use Drupal\aculta_links\Entity\LinkPage;
use Drupal\aculta_links\Form\LinkPageItemAddForm;
use Drupal\aculta_links\Form\LinkPageEditorForm;
use Drupal\aculta_links\Service\LinkAnalytics;
use Symfony\Component\HttpFoundation\Request;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    'token',
    'metatag',
    'datetime',
    'link',
    'node',
    'options',
    'path_alias',
    'views',
    'aculta_links',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Portal integration is an external contract in this isolated Kernel test.
    $container->register('aculta_portal.domain_purpose')
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
    $container->register('aculta_user.author_identity_resolver')
      ->setSynthetic(TRUE)
      ->setPublic(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $domain_purpose = (new \ReflectionClass(DomainPurposeManager::class))
      ->newInstanceWithoutConstructor();
    $this->container->set('aculta_portal.domain_purpose', $domain_purpose);
    $identity_resolver = (new \ReflectionClass(AuthorIdentityResolver::class))
      ->newInstanceWithoutConstructor();
    $this->container->set('aculta_user.author_identity_resolver', $identity_resolver);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('aculta_link');
    $this->installEntitySchema('aculta_link_page');
    $this->installEntitySchema('aculta_link_item');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('aculta_links', ['aculta_links_stat_daily']);
    $this->installConfig(['system', 'user', 'node']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Tests page composition, owner access, publication, and link revision.
   */
  public function testEntityStorageAndOwnerBoundAccess(): void {
    $route_provider = $this->container->get('router.route_provider');
    $dashboard_route = $route_provider->getRouteByName('aculta_links.dashboard');
    self::assertSame('/painel-administrativo/links', $dashboard_route->getPath());
    self::assertTrue($dashboard_route->getOption('_admin_route'));
    $public_route = $route_provider->getRouteByName('aculta_links.page_public');
    self::assertSame('/pagina/{slug}', $public_route->getPath());
    self::assertSame('links', $public_route->getOption('_aculta_domain_purpose'));
    $short_redirect_route = $route_provider->getRouteByName('aculta_links.short_redirect');
    self::assertSame('/redirecionar/{codigo}', $short_redirect_route->getPath());
    self::assertSame('links', $short_redirect_route->getOption('_aculta_domain_purpose'));
    self::assertSame(['GET', 'HEAD'], $short_redirect_route->getMethods());
    $short_qr_route = $route_provider->getRouteByName('aculta_links.short_qr_code');
    self::assertSame('/redirecionar/{codigo}/codigo-qr.svg', $short_qr_route->getPath());
    self::assertSame('links', $short_qr_route->getOption('_aculta_domain_purpose'));
    self::assertSame(['GET', 'HEAD'], $short_qr_route->getMethods());
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
    $editor_route = $route_provider->getRouteByName('aculta_links.page_editor');
    self::assertSame('/painel-administrativo/links/pagina/{aculta_link_page}/editar-itens', $editor_route->getPath());
    $page_item_add_route = $route_provider->getRouteByName('aculta_links.page_item_add');
    self::assertSame('/painel-administrativo/links/pagina/{aculta_link_page}/item/adicionar', $page_item_add_route->getPath());
    self::assertSame('aculta_link_page.update', $page_item_add_route->getRequirement('_entity_access'));
    $preview_route = $route_provider->getRouteByName('aculta_links.page_preview');
    self::assertSame('/pagina/{slug}/previa', $preview_route->getPath());
    self::assertSame('links', $preview_route->getOption('_aculta_domain_purpose'));
    $landing_route = $route_provider->getRouteByName('aculta_links.landing');
    self::assertSame('/redes-sociais', $landing_route->getPath());
    self::assertSame('links', $landing_route->getOption('_aculta_domain_purpose'));
    $social_settings_route = $route_provider->getRouteByName('aculta_links.social_links_settings');
    self::assertSame('/painel-administrativo/aculta/redes-sociais', $social_settings_route->getPath());
    self::assertTrue($social_settings_route->getOption('_admin_route'));
    $analytics_settings_route = $route_provider->getRouteByName('aculta_links.analytics_settings');
    self::assertSame('/painel-administrativo/aculta/links/configuracoes', $analytics_settings_route->getPath());
    self::assertSame('administer aculta links', $analytics_settings_route->getRequirement('_permission'));
    self::assertTrue($analytics_settings_route->getOption('_admin_route'));
    self::assertSame('aculta_link_page.update', $editor_route->getRequirement('_entity_access'));
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
    $short_code = (string) $link->get('short_code')->value;
    self::assertMatchesRegularExpression('/^[0-9a-z]{12}$/D', $short_code);
    $analytics = $this->container->get('aculta_links.analytics');
    self::assertFalse($analytics->isEnabled(), 'Analytics starts disabled unless a site administrator explicitly enables it.');
    $this->container->get('config.factory')->getEditable('aculta_links.settings')
      ->set('analytics_enabled', TRUE)
      ->save();
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create('/redirecionar/' . $short_code, 'GET'));
    $short_response = $this->container->get('aculta_links.public_page_controller')
      ->redirectByCode($short_code);
    $request_stack->pop();
    self::assertSame(302, $short_response->getStatusCode());
    self::assertSame('https://example.org/', $short_response->getTargetUrl());
    self::assertSame([], $analytics->clickCounts([(int) $link->id()]), 'Clicks remain pending until the queue worker aggregates them.');
    $queue = $this->container->get('queue')->get(LinkAnalytics::QUEUE_ID);
    $queue_item = $queue->claimItem();
    self::assertNotFalse($queue_item, 'A validated short-link redirect queues a click event.');
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(LinkAnalytics::QUEUE_ID);
    $worker->processItem($queue_item->data);
    $queue->deleteItem($queue_item);
    self::assertSame([((int) $link->id()) => 1], $analytics->clickCounts([(int) $link->id()]), 'The worker stores only the aggregate count by destination.');
    $request_stack->push(Request::create('/redirecionar/' . $short_code, 'HEAD'));
    $head_response = $this->container->get('aculta_links.public_page_controller')
      ->redirectByCode($short_code);
    $request_stack->pop();
    self::assertSame(302, $head_response->getStatusCode());
    self::assertSame(0, $queue->numberOfItems(), 'HEAD probes do not enqueue click events.');
    $expired_date = gmdate('Y-m-d', $this->container->get('datetime.time')->getRequestTime() - 91 * 86400);
    $this->container->get('database')->insert('aculta_links_stat_daily')
      ->fields([
        'event' => 'click',
        'target_id' => (int) $link->id(),
        'event_date' => $expired_date,
        'count' => 8,
      ])
      ->execute();
    self::assertSame(1, $analytics->purgeExpired(), 'Cron retention removes aggregates outside 90 days.');
    $first_revision_id = $link->getRevisionId();

    $page = LinkPage::create([
      'title' => 'Página cultural',
      'slug' => 'pagina-cultural',
      'description' => 'Apresentação pública.',
      'theme_preset' => 'soft',
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

    $other_link = Link::create([
      'title' => 'Destino de outra conta',
      'uri' => ['uri' => 'https://other.example/'],
      'uid' => $other->id(),
      'status' => TRUE,
    ]);
    $other_link->save();
    $cross_owner_item = LinkItem::create([
      'page' => $page->id(),
      'link' => $other_link->id(),
      'uid' => $owner->id(),
      'title' => 'Referência cruzada',
      'status' => TRUE,
    ]);
    self::assertNotEmpty(
      $cross_owner_item->validate()->getByField('link'),
      'A composição não pode referenciar destino pertencente a outra conta.',
    );

    $now = $this->container->get('datetime.time')->getRequestTime();
    $scheduled_item = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Visite o projeto na abertura',
      'weight' => 1,
      'status' => TRUE,
      'available_from' => gmdate('Y-m-d\\TH:i:s', $now + 300),
      'available_until' => gmdate('Y-m-d\\TH:i:s', $now + 900),
    ]);
    $scheduled_item->save();

    $invalid_schedule = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Janela inválida',
      'available_from' => gmdate('Y-m-d\\TH:i:s', $now + 600),
      'available_until' => gmdate('Y-m-d\\TH:i:s', $now + 300),
    ]);
    self::assertNotEmpty(
      $invalid_schedule->validate()->getByField('available_until'),
      'A data final precisa ser posterior à data inicial.',
    );

    self::assertFalse($page->isPublished(), 'Micropáginas começam despublicadas.');
    self::assertFalse((bool) $page->get('show_owner_identity')->value, 'A identidade do titular começa desativada.');
    self::assertSame('soft', $page->get('theme_preset')->value, 'A escolha de preset é persistida na micropágina.');
    self::assertTrue($link->access('update', $owner));
    self::assertTrue($link->access('view', $other), 'Destinos publicados são públicos.');
    self::assertTrue($page->access('update', $owner));
    self::assertFalse($page->access('update', $other));
    self::assertTrue($item->access('update', $owner));
    self::assertFalse($item->access('update', $other));
    self::assertSame((int) $page->id(), (int) $item->get('page')->target_id);
    self::assertSame((int) $link->id(), (int) $item->get('link')->target_id);

    $anonymous = User::getAnonymousUser();
    self::assertFalse($page->access('view', $anonymous), 'Rascunhos não são públicos.');
    $this->container->get('current_user')->setAccount($owner);
    $preview = $this->container->get('aculta_links.public_page_controller')->preview($page);
    self::assertTrue($preview['#has_links'], 'Proprietário pode pré-visualizar itens de um rascunho.');
    self::assertSame('soft', $preview['#theme_preset']);
    self::assertSame([], $preview['#owner_identity'], 'A prévia não tenta herdar identidade sem opção explícita.');
    self::assertCount(2, $preview['#links']['#items'], 'A prévia do proprietário inclui itens agendados para o futuro.');
    self::assertSame(0, $preview['#cache']['max-age'], 'Prévia privada nunca pode ser compartilhada em cache.');
    self::assertTrue(
      $this->container->get('aculta_links.public_page_controller')->previewBySlug('pagina-cultural')['#has_links'],
      'Prévia por slug resolve o rascunho do proprietário.',
    );
    $this->container->get('current_user')->setAccount($anonymous);
    $page->setPublished();
    $page->save();
    $this->container->get('entity_type.manager')
      ->getAccessControlHandler('aculta_link_page')
      ->resetCache();
    $item = $this->container->get('entity_type.manager')->getStorage('aculta_link_item')->load($item->id());
    self::assertTrue($page->isPublished(), 'O estado de publicação da página é persistido.');
    self::assertTrue($page->access('view', $anonymous), 'Páginas publicadas ficam visíveis publicamente.');
    self::assertTrue($item->access('view', $anonymous), 'Itens publicados ligados a página e destino publicados são públicos.');
    self::assertFalse(
      $cross_owner_item->access('view', $anonymous),
      'Um item que cruza proprietários não se torna público mesmo se página e destino estiverem publicados.',
    );
    self::assertTrue($link->access('view', $anonymous), 'Destinos publicados ficam visíveis publicamente.');
    $render = $this->container->get('aculta_links.public_page_controller')->view('pagina-cultural');
    self::assertSame('aculta_links_page', $render['#theme']);
    self::assertTrue($render['#has_links']);
    self::assertSame('item_list', $render['#links']['#theme']);
    self::assertCount(1, $render['#links']['#items']);
    self::assertGreaterThan(0, $render['#cache']['max-age']);
    self::assertLessThanOrEqual(300, $render['#cache']['max-age'], 'A página expira no início da próxima janela agendada.');

    $invalid_slug = LinkPage::create([
      'title' => 'Slug inválido',
      'slug' => 'Página inválida',
      'uid' => $owner->id(),
    ]);
    self::assertNotEmpty($invalid_slug->validate(), 'Slug público deve seguir gramática ASCII em minúsculas.');
    $duplicate_slug = LinkPage::create([
      'title' => 'Slug duplicado',
      'slug' => 'pagina-cultural',
      'uid' => $owner->id(),
    ]);
    self::assertNotEmpty($duplicate_slug->validate()->getByField('slug'), 'Slug deve ser globalmente único.');
    $invalid_preset = LinkPage::create([
      'title' => 'Preset inválido',
      'slug' => 'pagina-com-preset-invalido',
      'theme_preset' => 'custom-css',
      'uid' => $owner->id(),
    ]);
    self::assertNotEmpty($invalid_preset->validate()->getByField('theme_preset'), 'Presets só aceitam os valores governados.');
    $unsafe_link = Link::create([
      'title' => 'Destino inseguro',
      'uri' => ['uri' => 'http://example.org/'],
      'uid' => $owner->id(),
    ]);
    self::assertNotEmpty($unsafe_link->validate()->getByField('uri'), 'Destinos externos precisam usar HTTPS.');
    $internal_link = Link::create([
      'title' => 'Destino interno',
      'uri' => ['uri' => 'internal:/sobre'],
      'uid' => $owner->id(),
    ]);
    self::assertEmpty($internal_link->validate()->getByField('uri'), 'Destinos Drupal internos são permitidos.');

    $link->set('title', 'Site institucional atualizado');
    $link->setNewRevision(TRUE);
    $link->setRevisionLogMessage('Atualiza o rótulo do destino.');
    $link->save();

    self::assertNotSame($first_revision_id, $link->getRevisionId());
    $revision_storage = $this->container->get('entity_type.manager')->getStorage('aculta_link');
    $previous_revision = $revision_storage->loadRevision($first_revision_id);
    self::assertSame('Site institucional', $previous_revision->label());
  }

  /**
   * Tests add forms build their configurable content fields.
   */
  public function testEntityAddFormsExposeContentFields(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $form_builder = $this->container->get('form_builder');
    $expected_fields = [
      'aculta_link' => ['title', 'uri', 'target_node'],
      'aculta_link_page' => ['title', 'slug', 'description', 'theme_preset'],
      'aculta_link_item' => ['title', 'page', 'link'],
    ];

    foreach ($expected_fields as $entity_type_id => $field_names) {
      $form_object = $entity_type_manager->getFormObject($entity_type_id, 'add');
      self::assertInstanceOf(ContentEntityForm::class, $form_object);
      $form_object->setEntity($entity_type_manager->getStorage($entity_type_id)->create());
      $form = $form_builder->getForm($form_object);

      foreach ($field_names as $field_name) {
        self::assertArrayHasKey($field_name, $form, $entity_type_id . ' form should expose ' . $field_name);
      }
    }
  }

  /**
   * Tests the composition editor persists revisions and rejects foreign rows.
   */
  public function testPageEditorSubmitSavesAndChecksItemOwnership(): void {
    $role = Role::create(['id' => 'links_editor_owner', 'label' => 'Links editor owner']);
    $role->grantPermission('manage own aculta links');
    $role->save();
    $owner = User::create(['name' => 'links-editor-owner', 'status' => TRUE]);
    $owner->addRole('links_editor_owner');
    $owner->save();
    $this->container->get('current_user')->setAccount($owner);

    $link = Link::create([
      'title' => 'Destino do editor',
      'uri' => ['uri' => 'https://example.org/editor'],
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    $link->save();
    $page = LinkPage::create([
      'title' => 'Página do editor',
      'slug' => 'pagina-do-editor',
      'uid' => $owner->id(),
    ]);
    $page->save();
    $item = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Botão existente',
      'weight' => 0,
      'status' => TRUE,
    ]);
    $item->save();
    $original_revision = $item->getRevisionId();

    $editor = new LinkPageEditorForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
      $this->container->get('aculta_portal.domain_purpose'),
    );
    $display_page = clone $page;
    $display_page->set('slug', 'slug inválido');
    $display_state = new FormState();
    $display_form = $editor->buildForm([], $display_state, $display_page);
    self::assertSame('textfield', $display_form['items'][$item->id()]['content']['title']['#type']);
    self::assertSame('Botão existente', $display_form['items'][$item->id()]['content']['title']['#default_value']);
    self::assertSame(255, $display_form['items'][$item->id()]['content']['title']['#maxlength']);
    self::assertSame('textfield', $display_form['items'][$item->id()]['content']['description']['#type']);
    self::assertSame('Destino e agendamento', (string) $display_form['items'][$item->id()]['edit']['data']['#title']);

    $invalid_state = new FormState();
    $invalid_state->set('aculta_link_page_id', (int) $page->id());
    $invalid_state->setValue('items', [
      (string) $item->id() => [
        'content' => ['title' => '   ', 'description' => 'Inválida'],
        'weight' => 5,
        'status' => 0,
      ],
    ]);
    $form = [];
    $editor->submitForm($form, $invalid_state);
    self::assertTrue($invalid_state->hasAnyErrors());
    $unchanged_item = $this->container->get('entity_type.manager')
      ->getStorage('aculta_link_item')
      ->load($item->id());
    self::assertSame('Botão existente', $unchanged_item->label());
    self::assertSame($original_revision, $unchanged_item->getRevisionId());

    $state = new FormState();
    $state->set('aculta_link_page_id', (int) $page->id());
    $state->setValue('items', [
      (string) $item->id() => [
        'content' => ['title' => 'Botão atualizado', 'description' => 'Descrição atualizada'],
        'weight' => 4,
        'status' => 0,
      ],
    ]);
    $editor->submitForm($form, $state);

    $saved_item = $this->container->get('entity_type.manager')
      ->getStorage('aculta_link_item')
      ->load($item->id());
    self::assertSame(4, (int) $saved_item->get('weight')->value);
    self::assertSame('Botão atualizado', $saved_item->label());
    self::assertSame('Descrição atualizada', $saved_item->get('description')->value);
    self::assertFalse($saved_item->isPublished());
    self::assertNotSame($original_revision, $saved_item->getRevisionId());

    $other_page = LinkPage::create([
      'title' => 'Outra página do proprietário',
      'slug' => 'outra-pagina-do-proprietario',
      'uid' => $owner->id(),
    ]);
    $other_page->save();
    $foreign_item = LinkItem::create([
      'page' => $other_page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Item de outra página',
      'status' => TRUE,
    ]);
    $foreign_item->save();

    $malicious_state = new FormState();
    $malicious_state->set('aculta_link_page_id', (int) $page->id());
    $malicious_state->setValue('items', [
      (string) $item->id() => [
        'content' => ['title' => 'Alteração parcial proibida', 'description' => ''],
        'weight' => 99,
        'status' => 1,
      ],
      (string) $foreign_item->id() => [
        'content' => ['title' => 'Item estrangeiro', 'description' => ''],
        'weight' => 99,
        'status' => 0,
      ],
    ]);
    try {
      $editor->submitForm($form, $malicious_state);
      self::fail('An item from another page must reject the complete submission.');
    }
    catch (AccessDeniedHttpException) {
      $unchanged_item = $this->container->get('entity_type.manager')
        ->getStorage('aculta_link_item')
        ->load($item->id());
      self::assertSame('Botão atualizado', $unchanged_item->label());
      self::assertSame(4, (int) $unchanged_item->get('weight')->value);
    }
  }

  /**
   * Tests publication filters against the actual owner-scoped entity query.
   */
  public function testDashboardPublicationFilterQueriesOnlySelectedItems(): void {
    $role = Role::create(['id' => 'links_filter_owner', 'label' => 'Links filter owner']);
    $role->grantPermission('manage own aculta links');
    $role->save();
    $owner = User::create(['name' => 'links-filter-owner', 'status' => TRUE]);
    $owner->addRole('links_filter_owner');
    $owner->save();
    $this->container->get('current_user')->setAccount($owner);
    $page = LinkPage::create([
      'title' => 'Página com filtros',
      'slug' => 'pagina-com-filtros',
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    $page->save();
    $link = Link::create([
      'title' => 'Destino do filtro',
      'uri' => ['uri' => 'https://example.org/'],
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    $link->save();
    $published = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Botão publicado',
      'status' => TRUE,
    ]);
    $published->save();
    $draft = LinkItem::create([
      'page' => $page->id(),
      'link' => $link->id(),
      'uid' => $owner->id(),
      'title' => 'Botão em rascunho',
      'status' => FALSE,
    ]);
    $draft->save();

    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create('/painel-administrativo/links?itens_estado=rascunhos'));
    $controller = $this->container->get('aculta_links.dashboard_controller');
    $build_table = new \ReflectionMethod($controller, 'buildTable');
    $build = $build_table->invoke(
      $controller,
      'aculta_link_item',
      'aculta_link_item_list',
      'Itens de micropágina',
      FALSE,
      'uid',
      2,
    );
    $section_navigation = (new \ReflectionMethod($controller, 'buildSectionNavigation'))
      ->invoke($controller);
    $navigation_markup = (string) $this->container->get('renderer')
      ->renderRoot($section_navigation);
    $request_stack->pop();

    self::assertCount(1, $build['table']['#rows']);
    self::assertSame('Botão em rascunho', (string) $build['table']['#rows'][0]['title']->getText());
    self::assertSame('links-items', $build['#attributes']['id']);
    self::assertContains('url.query_args:itens_estado', $build['table']['#cache']['contexts']);
    self::assertStringContainsString('<nav aria-label="Navegação entre listas de links">', $navigation_markup);
    self::assertStringContainsString('#links-items', $navigation_markup);
    self::assertStringContainsString('itens_estado=rascunhos', $navigation_markup);
    self::assertNotSame($published->id(), $draft->id());
  }

  /**
   * Tests the page-specific item form limits selectable links to the owner.
   */
  public function testPageItemAddFormListsOnlySameOwnerDestinations(): void {
    $role = Role::create(['id' => 'links_item_owner', 'label' => 'Links item owner']);
    $role->grantPermission('manage own aculta links');
    $role->save();
    $owner = User::create(['name' => 'item-form-owner', 'status' => TRUE]);
    $owner->addRole('links_item_owner');
    $owner->save();
    $other = User::create(['name' => 'item-form-other', 'status' => TRUE]);
    $other->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'Conteúdo referenciável',
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    $node->save();
    $referenced_link = Link::create([
      'title' => 'Conteúdo do portal',
      'target_node' => $node->id(),
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    self::assertEmpty($referenced_link->validate(), 'Um destino pode referenciar conteúdo Drupal sem URL duplicada.');
    $referenced_link->save();

    $missing_destination = Link::create([
      'title' => 'Sem destino',
      'uid' => $owner->id(),
    ]);
    self::assertNotEmpty($missing_destination->validate()->getByField('uri'), 'Um destino reutilizável precisa de URL ou referência.');
    $conflicting_destination = Link::create([
      'title' => 'Destinos conflitantes',
      'uri' => ['uri' => 'https://example.org/'],
      'target_node' => $node->id(),
      'uid' => $owner->id(),
    ]);
    self::assertNotEmpty($conflicting_destination->validate(), 'Um destino não pode combinar URL e referência.');

    $owned_link = Link::create([
      'title' => 'Destino próprio',
      'uri' => ['uri' => 'https://example.org/owned'],
      'uid' => $owner->id(),
      'status' => TRUE,
    ]);
    $owned_link->save();
    $foreign_link = Link::create([
      'title' => 'Destino de outra conta',
      'uri' => ['uri' => 'https://example.org/foreign'],
      'uid' => $other->id(),
      'status' => TRUE,
    ]);
    $foreign_link->save();
    $page = LinkPage::create([
      'title' => 'Página do formulário',
      'slug' => 'pagina-do-formulario',
      'uid' => $owner->id(),
    ]);
    $page->save();

    $this->container->get('current_user')->setAccount($owner);
    $form = new LinkPageItemAddForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('current_user'),
    );
    $build = $form->buildForm([], new FormState(), $page);

    self::assertArrayHasKey((string) $owned_link->id(), $build['link']['#options']);
    self::assertArrayNotHasKey((string) $foreign_link->id(), $build['link']['#options']);
    foreach (array_keys($build['link']['#options']) as $link_id) {
      $candidate = $this->container->get('entity_type.manager')
        ->getStorage('aculta_link')
        ->load($link_id);
      self::assertSame((string) $owner->id(), (string) $candidate->getOwnerId());
    }
  }

  /**
   * Tests legacy social-links configuration migration without deleting source.
   */
  public function testSocialLinksConfigurationMigrationPreservesLegacySource(): void {
    $this->installConfig(['aculta_links']);
    $config_storage = $this->container->get('config.storage');
    $legacy_values = [
      'instagram' => 'https://instagram.com/coletivo_420',
      'youtube' => '',
      'facebook' => '',
      'buttons' => [['text' => 'Campanha', 'url' => '/campanha']],
    ];
    $config_storage->write('aculta_portal.social_links', $legacy_values);
    $config_storage->delete('aculta_links.social_links');
    $this->container->get('module_handler')->loadInclude('aculta_links', 'install');

    aculta_links_update_10002();

    self::assertSame($legacy_values, $config_storage->read('aculta_links.social_links'));
    self::assertSame($legacy_values, $config_storage->read('aculta_portal.social_links'));
  }

}
