<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_index\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_index\Plugin\Block\RelatedContentBlock;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatch;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Routing\Route;

/**
 * Tests RelatedContentBlock plugin behavior and ContentIndex relation querying.
 */
#[Group('aculta_index')]
#[RunTestsInSeparateProcesses]
final class RelatedContentBlockKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'key',
    'address',
    'geofield',
    'options',
    'link',
    'text',
    'filter',
    'taxonomy',
    'datetime',
    'datetime_range',
    'domain',
    'block',
    'aculta_calendar',
    'aculta_index',
    'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setDefinition('aculta_portal.domain_purpose', (new Definition(DomainPurposeManager::class, [
      new Reference('entity_type.manager'),
      new Reference('domain.negotiator'),
      new Reference('config.factory'),
      new Reference('request_stack'),
    ]))->setPublic(TRUE));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('domain');
    $this->installConfig(['system', 'taxonomy', 'domain']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    aculta_index_install();

    foreach (['aculta_org' => 'main', 'eventos_aculta_org' => 'eventos'] as $id => $name) {
      Domain::create([
        'id' => $id,
        'hostname' => $name . '.example.invalid',
        'name' => $name,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
        'weight' => 0,
      ])->save();
    }

    $authenticated = Role::load(Role::AUTHENTICATED_ID);
    if ($authenticated instanceof Role) {
      $authenticated->grantPermission('access content')->save();
    }
  }

  /**
   * Tests block plugin discovery, default configuration and form submission.
   */
  public function testBlockConfigurationAndForm(): void {
    /** @var \Drupal\Core\Block\BlockManagerInterface $blockManager */
    $blockManager = $this->container->get('plugin.manager.block');
    $this->assertTrue($blockManager->hasDefinition('aculta_index_related_content'));

    /** @var \Drupal\aculta_index\Plugin\Block\RelatedContentBlock $block */
    $block = $blockManager->createInstance('aculta_index_related_content');
    $this->assertInstanceOf(RelatedContentBlock::class, $block);

    $defaults = $block->defaultConfiguration();
    $this->assertSame(5, $defaults['limit']);
    $this->assertSame('Conteúdo relacionado', $defaults['block_title']);
    $this->assertTrue($defaults['show_summary']);

    $formState = new FormState();
    $formState->setValues([
      'block_title' => 'Itens vinculados',
      'relation_types' => 'event_location, related_entry',
      'limit' => 3,
      'show_summary' => FALSE,
    ]);
    $form = $block->blockForm([], $formState);
    $this->assertArrayHasKey('block_title', $form);
    $this->assertArrayHasKey('relation_types', $form);
    $this->assertArrayHasKey('limit', $form);
    $this->assertArrayHasKey('show_summary', $form);

    $block->blockSubmit($form, $formState);
    $config = $block->getConfiguration();
    $this->assertSame('Itens vinculados', $config['block_title']);
    $this->assertSame('event_location, related_entry', $config['relation_types']);
    $this->assertSame(3, $config['limit']);
    $this->assertFalse($config['show_summary']);
  }

  /**
   * Tests block returns empty when no source entity is available.
   */
  public function testBlockEmptyWhenNoSourceEntity(): void {
    /** @var \Drupal\aculta_index\Plugin\Block\RelatedContentBlock $block */
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('aculta_index_related_content');

    $build = $block->build();
    $this->assertSame([], $build);
  }

  /**
   * Tests block returns empty render array when entity has no relations.
   */
  public function testBlockEmptyWhenNoOutgoingRelations(): void {
    $event = Event::create([
      'title' => 'Evento Solitário',
      'field_status' => 'publicado',
    ]);
    $event->save();
    $this->buildAndActivateGeneration();

    /** @var \Drupal\aculta_index\Plugin\Block\RelatedContentBlock $block */
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('aculta_index_related_content');
    $block->setContextValue('entity', $event);

    $build = $block->build();
    // Must return empty renderable array (no empty shell or wrapper).
    $this->assertTrue(Element::isEmpty($build));
    $this->assertArrayNotHasKey('#theme', $build);
    $this->assertArrayNotHasKey('#items', $build);
  }

  /**
   * Tests block rendering related items, links, badges, summaries, and cache.
   */
  public function testBlockRendersOutgoingRelations(): void {
    $event = Event::create([
      'title' => 'Festival da Primavera',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $loc1 = EventLocation::create([
      'title' => 'Parque Central',
      'field_status' => 'publicado',
      'field_descricao' => 'Área verde no centro da cidade.',
    ]);
    $loc1->save();

    $loc2 = EventLocation::create([
      'title' => 'Auditório Municipal',
      'field_status' => 'publicado',
      'field_descricao' => 'Espaço fechado com ar condicionado.',
    ]);
    $loc2->save();

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dateStorage->create([
      'title' => 'Data 1',
      'field_evento' => $event->id(),
      'field_local' => $loc1->id(),
      'field_quando' => ['value' => '2026-11-20T14:00:00', 'end_value' => '2026-11-20T18:00:00'],
    ])->save();
    $dateStorage->create([
      'title' => 'Data 2',
      'field_evento' => $event->id(),
      'field_local' => $loc2->id(),
      'field_quando' => ['value' => '2026-11-21T14:00:00', 'end_value' => '2026-11-21T18:00:00'],
    ])->save();

    $this->buildAndActivateGeneration();

    /** @var \Drupal\aculta_index\Plugin\Block\RelatedContentBlock $block */
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('aculta_index_related_content', [
        'block_title' => 'Locais do Festival',
        'limit' => 5,
        'show_summary' => TRUE,
      ]);
    $block->setContextValue('entity', $event);

    $build = $block->build();
    $this->assertNotEmpty($build);
    $this->assertSame('item_list', $build['#theme']);
    $this->assertSame('Locais do Festival', (string) $build['#title']);
    $this->assertCount(2, $build['#items']);
    $this->assertSame('related-content-block', $build['#attributes']['data-portal-ui']);
    $this->assertArrayNotHasKey('#attached', $build);

    // Check rendered item attributes and sub-elements.
    $firstItem = $build['#items'][0];
    $this->assertSame('container', $firstItem['#type']);
    $this->assertSame('related-content-item', $firstItem['#attributes']['data-portal-ui']);
    $this->assertSame('aculta_event_location', $firstItem['#attributes']['data-entity-type']);
    $this->assertArrayHasKey('title', $firstItem);
    $this->assertArrayHasKey('badge', $firstItem);
    $this->assertArrayHasKey('summary', $firstItem);
    $this->assertSame('related-content-link', $firstItem['title']['#attributes']['data-portal-ui']);
    $this->assertSame('related-content-badge', $firstItem['badge']['#attributes']['data-portal-ui']);
    $this->assertSame('related-content-summary', $firstItem['summary']['#attributes']['data-portal-ui']);

    // Verify cache tags include source event and both location entities.
    $cacheTags = $build['#cache']['tags'];
    $this->assertContains('aculta_event:' . $event->id(), $cacheTags);
    $this->assertContains('aculta_event_location:' . $loc1->id(), $cacheTags);
    $this->assertContains('aculta_event_location:' . $loc2->id(), $cacheTags);

    // Verify cache contexts include languages and user permissions.
    $cacheContexts = $build['#cache']['contexts'];
    $this->assertContains('languages:language_interface', $cacheContexts);
    $this->assertContains('user.permissions', $cacheContexts);
  }

  /**
   * Tests block falls back to current route match when context is unset.
   */
  public function testBlockRouteMatchFallback(): void {
    $event = Event::create([
      'title' => 'Circuito Cultural',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $loc = EventLocation::create([
      'title' => 'Casa de Cultura',
      'field_status' => 'publicado',
    ]);
    $loc->save();

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dateStorage->create([
      'title' => 'Data Circuito',
      'field_evento' => $event->id(),
      'field_local' => $loc->id(),
      'field_quando' => ['value' => '2026-12-01T10:00:00', 'end_value' => '2026-12-01T12:00:00'],
    ])->save();

    $this->buildAndActivateGeneration();

    // Mock route match with aculta_event parameter.
    $route = new Route('/eventos/{aculta_event}');
    $routeMatch = new RouteMatch(
      'entity.aculta_event.canonical',
      $route,
      ['aculta_event' => $event],
      ['aculta_event' => (string) $event->id()],
    );

    $block = new RelatedContentBlock(
      [],
      'aculta_index_related_content',
      ['provider' => 'aculta_index'],
      $this->container->get('aculta_index.relation_query'),
      $this->container->get('current_user'),
      $routeMatch,
    );

    $build = $block->build();
    $this->assertNotEmpty($build);
    $this->assertCount(1, $build['#items']);
    $this->assertSame('Casa de Cultura', (string) $build['#items'][0]['title']['#title']);
  }

  /**
   * Tests block respects limit configuration.
   */
  public function testBlockRespectsConfiguredLimit(): void {
    $event = Event::create([
      'title' => 'Multi-Local Show',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $loc1 = EventLocation::create(['title' => 'Local Alfa', 'field_status' => 'publicado']);
    $loc1->save();
    $loc2 = EventLocation::create(['title' => 'Local Beta', 'field_status' => 'publicado']);
    $loc2->save();

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dateStorage->create([
      'title' => 'Data A',
      'field_evento' => $event->id(),
      'field_local' => $loc1->id(),
      'field_quando' => ['value' => '2026-11-25T19:00:00', 'end_value' => '2026-11-25T21:00:00'],
    ])->save();
    $dateStorage->create([
      'title' => 'Data B',
      'field_evento' => $event->id(),
      'field_local' => $loc2->id(),
      'field_quando' => ['value' => '2026-11-26T19:00:00', 'end_value' => '2026-11-26T21:00:00'],
    ])->save();

    $this->buildAndActivateGeneration();

    /** @var \Drupal\aculta_index\Plugin\Block\RelatedContentBlock $block */
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('aculta_index_related_content', [
        'limit' => 1,
      ]);
    $block->setContextValue('entity', $event);

    $build = $block->build();
    $this->assertNotEmpty($build);
    $this->assertCount(1, $build['#items']);
  }

  /**
   * Tests block hides unpublished or forbidden targets immediately.
   */
  public function testBlockHidesForbiddenTargetsImmediately(): void {
    $event = Event::create([
      'title' => 'Encontro Aberto',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $locPublic = EventLocation::create(['title' => 'Praça Pública', 'field_status' => 'publicado']);
    $locPublic->save();
    $locPrivate = EventLocation::create(['title' => 'Sala Privada', 'field_status' => 'publicado']);
    $locPrivate->save();

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dateStorage->create([
      'title' => 'Data 1',
      'field_evento' => $event->id(),
      'field_local' => $locPublic->id(),
      'field_quando' => ['value' => '2026-12-05T15:00:00', 'end_value' => '2026-12-05T17:00:00'],
    ])->save();
    $dateStorage->create([
      'title' => 'Data 2',
      'field_evento' => $event->id(),
      'field_local' => $locPrivate->id(),
      'field_quando' => ['value' => '2026-12-06T15:00:00', 'end_value' => '2026-12-06T17:00:00'],
    ])->save();

    $this->buildAndActivateGeneration();

    // Make second location private.
    $locPrivate->set('field_status', 'privado');
    $locPrivate->save();

    /** @var \Drupal\aculta_index\Plugin\Block\RelatedContentBlock $block */
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('aculta_index_related_content');
    $block->setContextValue('entity', $event);

    $build = $block->build();
    $this->assertNotEmpty($build);
    $this->assertCount(1, $build['#items']);
    $this->assertSame('Praça Pública', (string) $build['#items'][0]['title']['#title']);

    // Make first location private as well.
    $locPublic->set('field_status', 'privado');
    $locPublic->save();

    $buildAllPrivate = $block->build();
    // All targets private => block fails closed and returns empty renderable.
    $this->assertTrue(Element::isEmpty($buildAllPrivate));
    $this->assertArrayNotHasKey('#theme', $buildAllPrivate);
    $this->assertArrayNotHasKey('#items', $buildAllPrivate);
  }

  /**
   * Builds and activates an index generation from existing entities.
   */
  private function buildAndActivateGeneration(): void {
    /** @var \Drupal\aculta_index\Maintenance\ContentIndexRebuilder $rebuilder */
    $rebuilder = $this->container->get('aculta_index.rebuilder');
    $generationId = $rebuilder->start();
    $complete = FALSE;
    for ($batch = 0; $batch < 20 && !$complete; $batch++) {
      $complete = $rebuilder->processNextBatch($generationId, 50)['complete'];
    }
    $this->assertTrue($complete, 'O backfill das entidades deve ser concluído.');
    $this->processAllQueuedWork();
    $rebuilder->validate($generationId);
    $this->container->get('aculta_index.generation_manager')->activate($generationId);
  }

  /**
   * Processes all queued work items in Content Index work queue.
   */
  private function processAllQueuedWork(): void {
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    $queue = $this->container->get('queue')->get(ContentIndexWorkManager::QUEUE_ID);
    while ($item = $queue->claimItem()) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
    }
  }

}
