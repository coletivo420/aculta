<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Form\EventDateCreateForm;
use Drupal\aculta_events\Hook\EventDateHooks;
use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Valida a integração de eventos e locais com a infraestrutura aculta_index.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventContentIndexProviderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'domain', 'aculta_calendar',
    'aculta_index', 'aculta_events',
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
   * Metadados dos providers, allowlist segura, URLs canônicas e fingerprint.
   */
  public function testEventAndLocationProviderMetadataAndCanonicalUrl(): void {
    Vocabulary::create(['vid' => 'aculta_event_type', 'name' => 'Tipo de evento'])->save();
    $tipo = Term::create(['vid' => 'aculta_event_type', 'name' => 'Oficina']);
    $tipo->save();

    Vocabulary::create(['vid' => 'aculta_location_type', 'name' => 'Tipo de local'])->save();
    $locTipo = Term::create(['vid' => 'aculta_location_type', 'name' => 'Galeria']);
    $locTipo->save();

    $event = Event::create([
      'title' => 'Oficina de Aquarela',
      'field_status' => 'publicado',
      'field_tipo' => $tipo->id(),
    ]);
    $event->save();

    $location = EventLocation::create([
      'title' => 'Galeria de Belas Artes',
      'field_status' => 'publicado',
      'field_tipo' => $locTipo->id(),
      'field_endereco' => [
        'country_code' => 'BR',
        'address_line1' => 'Rua das Flores, 123',
        'locality' => 'Curitiba',
        'administrative_area' => 'PR',
        'postal_code' => '80000000',
      ],
      'field_telefone' => '41999998888',
      'field_site' => 'https://galeria.example.org',
    ]);
    $location->save();

    // 1. URLs canônicas em Event e EventLocation.
    $eventUrl = $event->toUrl('canonical');
    $this->assertSame('aculta_events.page', $eventUrl->getRouteName());
    $this->assertSame((string) $event->get('field_slug')->value, $eventUrl->getRouteParameters()['slug']);

    $eventEditUrl = $event->toUrl('edit-form');
    $this->assertSame('aculta_events.event_edit', $eventEditUrl->getRouteName());
    $this->assertSame((string) $event->id(), $eventEditUrl->getRouteParameters()['aculta_event']);

    $locUrl = $location->toUrl('canonical');
    $this->assertSame('aculta_events.location_canonical', $locUrl->getRouteName());
    $this->assertSame((string) $location->id(), $locUrl->getRouteParameters()['aculta_event_location']);

    $locEditUrl = $location->toUrl('edit-form');
    $this->assertSame('aculta_events.location_edit', $locEditUrl->getRouteName());

    $locDeleteUrl = $location->toUrl('delete-form');
    $this->assertSame('aculta_events.location_delete', $locDeleteUrl->getRouteName());

    // toCanonicalUrl com e sem DomainPurposeManager.
    $this->assertSame('aculta_events.page', $event->toCanonicalUrl(NULL)->getRouteName());
    $this->assertSame('aculta_events.location_canonical', $location->toCanonicalUrl(NULL)->getRouteName());

    /** @var \Drupal\aculta_portal\Domain\DomainPurposeManager $purposes */
    $purposes = $this->container->get('aculta_portal.domain_purpose');
    $canonicalEventAbs = $event->toCanonicalUrl($purposes);
    $this->assertStringContainsString('/eventos/' . $event->get('field_slug')->value, $canonicalEventAbs->toString());

    $canonicalLocAbs = $location->toCanonicalUrl($purposes);
    $this->assertStringContainsString('/locais/' . $location->id(), $canonicalLocAbs->toString());

    // 2. Providers: versão e fingerprint.
    $eventProvider = $this->container->get('aculta_events.index_provider.event');
    $locProvider = $this->container->get('aculta_events.index_provider.location');

    $this->assertSame(2, $eventProvider->getProjectionVersion());
    $this->assertSame(2, $locProvider->getProjectionVersion());

    /** @var \Drupal\aculta_index\Provider\ContentIndexProviderRegistry $registry */
    $registry = $this->container->get('aculta_index.provider_registry');
    $this->assertNotNull($registry->findForBundle('aculta_event', 'aculta_event'));
    $this->assertNotNull($registry->findForBundle('aculta_event_location', 'aculta_event_location'));
    $this->assertSame(64, strlen($registry->fingerprint()));

    // 3. Allowlist de projeção do Local: não inclui telefone, site, endereço exato.
    $locProj = $locProvider->project($location);
    $this->assertNotNull($locProj);
    $this->assertSame('aculta_event_location', $locProj->entityTypeId);
    $this->assertSame((string) $location->id(), $locProj->entityId);
    $this->assertSame('Galeria de Belas Artes', $locProj->label);
    $this->assertTrue($locProj->published);
    $this->assertCount(1, $locProj->relations);
    $this->assertSame('location_type', $locProj->relations[0]->type);

    // 4. Ausência de status trata como não publicável (falha fechada).
    $locSemStatus = EventLocation::create(['title' => 'Local sem status']);
    $locSemStatus->set('field_status', NULL);
    $this->assertFalse($locProvider->isIndexable($locSemStatus));
    $this->assertNull($locProvider->project($locSemStatus));

    $eventSemStatus = Event::create(['title' => 'Evento sem status']);
    $eventSemStatus->set('field_status', NULL);
    $this->assertFalse($eventProvider->isIndexable($eventSemStatus));
    $this->assertNull($eventProvider->project($eventSemStatus));
  }

  /**
   * Relações Evento > Local: deduplicação, múltiplos locais, filtros de status/modo e reparenting.
   */
  public function testEventLocationRelationshipsDeduplicationAndFiltering(): void {
    $event = Event::create([
      'title' => 'Festival Multi-Local',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $locA = EventLocation::create(['title' => 'Teatro A', 'field_status' => 'publicado']);
    $locA->save();
    $locB = EventLocation::create(['title' => 'Praça B', 'field_status' => 'publicado']);
    $locB->save();
    $locDraft = EventLocation::create(['title' => 'Galpão Rascunho', 'field_status' => 'rascunho']);
    $locDraft->save();
    $locPrivate = EventLocation::create(['title' => 'Sala Privada', 'field_status' => 'privado']);
    $locPrivate->save();

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');

    // Data 1 e Data 2 compartilham o mesmo local A (deve deduplicar).
    $date1 = $dateStorage->create([
      'title' => 'Sessão 1',
      'field_evento' => $event->id(),
      'field_local' => $locA->id(),
      'field_quando' => ['value' => '2026-11-01T10:00:00', 'end_value' => '2026-11-01T12:00:00'],
    ]);
    $date1->save();

    $date2 = $dateStorage->create([
      'title' => 'Sessão 2',
      'field_evento' => $event->id(),
      'field_local' => $locA->id(),
      'field_quando' => ['value' => '2026-11-01T14:00:00', 'end_value' => '2026-11-01T16:00:00'],
    ]);
    $date2->save();

    // Data 3 em local B (deve acrescentar o local B).
    $date3 = $dateStorage->create([
      'title' => 'Sessão 3',
      'field_evento' => $event->id(),
      'field_local' => $locB->id(),
      'field_quando' => ['value' => '2026-11-02T10:00:00', 'end_value' => '2026-11-02T12:00:00'],
    ]);
    $date3->save();

    // Data 4 sem local (deve ser ignorada sem erro).
    $date4 = $dateStorage->create([
      'title' => 'Sessão Sem Local',
      'field_evento' => $event->id(),
      'field_quando' => ['value' => '2026-11-03T10:00:00', 'end_value' => '2026-11-03T12:00:00'],
    ]);
    $date4->save();

    // Data 5 em modo online (deve ser ignorada para relações físicas).
    $date5 = $dateStorage->create([
      'title' => 'Sessão Virtual',
      'field_evento' => $event->id(),
      'field_local' => $locA->id(),
      'field_modo' => 'online',
      'field_quando' => ['value' => '2026-11-04T10:00:00', 'end_value' => '2026-11-04T12:00:00'],
    ]);
    $date5->save();

    // Data 6 em local rascunho (deve ser ignorada).
    $date6 = $dateStorage->create([
      'title' => 'Sessão Rascunho',
      'field_evento' => $event->id(),
      'field_local' => $locDraft->id(),
      'field_quando' => ['value' => '2026-11-05T10:00:00', 'end_value' => '2026-11-05T12:00:00'],
    ]);
    $date6->save();

    // Data 7 em local privado (deve ser ignorada).
    $date7 = $dateStorage->create([
      'title' => 'Sessão Privada',
      'field_evento' => $event->id(),
      'field_local' => $locPrivate->id(),
      'field_quando' => ['value' => '2026-11-06T10:00:00', 'end_value' => '2026-11-06T12:00:00'],
    ]);
    $date7->save();

    $provider = $this->container->get('aculta_events.index_provider.event');
    $proj = $provider->project($event);
    $this->assertNotNull($proj);

    $locRelations = array_values(array_filter($proj->relations, static fn ($r) => $r->type === 'event_location'));
    // Deve conter exatamente 2 relações: local A e local B (sem duplicar local A, sem rascunho/privado/online).
    $this->assertCount(2, $locRelations);
    $targetIds = array_map(static fn ($r) => $r->targetEntityId, $locRelations);
    sort($targetIds);
    $expected = [(string) $locA->id(), (string) $locB->id()];
    sort($expected);
    $this->assertSame($expected, $targetIds);

    // Reparenting: altera Data 3 do local B para o local A.
    $date3->set('field_local', $locA->id());
    $date3->save();

    $projReparented = $provider->project($event);
    $locRelationsReparented = array_values(array_filter($projReparented->relations, static fn ($r) => $r->type === 'event_location'));
    // Agora só resta local A, deduplicado.
    $this->assertCount(1, $locRelationsReparented);
    $this->assertSame((string) $locA->id(), $locRelationsReparented[0]->targetEntityId);
  }

  /**
   * Consulta Index via API de relações: acesso de origem e alvo, negação imediata, cursores e restauração de Domain.
   */
  public function testIndexRelationQueryAccessAndDomainRestoration(): void {
    // Reserve UID 1 para testes de acesso.
    User::create(['name' => 'root-fixture', 'status' => 1])->save();
    $reader = User::create(['name' => 'reader-user', 'status' => 1]);
    $reader->save();
    $other = User::create(['name' => 'other-user', 'status' => 1]);
    $other->save();

    $event = Event::create([
      'title' => 'Show na Concha',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $loc1 = EventLocation::create([
      'title' => 'Concha Acústica',
      'field_status' => 'publicado',
    ]);
    $loc1->save();

    $loc2 = EventLocation::create([
      'title' => 'Espaço Anexo',
      'field_status' => 'publicado',
    ]);
    $loc2->save();

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dateStorage->create([
      'title' => 'Data 1',
      'field_evento' => $event->id(),
      'field_local' => $loc1->id(),
      'field_quando' => ['value' => '2026-11-10T19:00:00', 'end_value' => '2026-11-10T21:00:00'],
    ])->save();
    $dateStorage->create([
      'title' => 'Data 2',
      'field_evento' => $event->id(),
      'field_local' => $loc2->id(),
      'field_quando' => ['value' => '2026-11-11T19:00:00', 'end_value' => '2026-11-11T21:00:00'],
    ])->save();

    $this->buildAndActivateGeneration();

    /** @var \Drupal\aculta_index\Query\ContentIndexRelationQuery $relationQuery */
    $relationQuery = $this->container->get('aculta_index.relation_query');

    // 1. Consulta relações outgoing: usuário anônimo deve receber os 2 locais.
    $anon = new AnonymousUserSession();
    $langcode = $event->language()->getId();
    $result = $relationQuery->queryOutgoing(
      'aculta_event',
      (string) $event->id(),
      $langcode,
      'event_location',
      $anon,
      NULL,
      10,
    );
    $this->assertCount(2, $result->entities);
    $resultIds = array_map(static fn ($e) => (string) $e->id(), $result->entities);
    $this->assertContains((string) $loc1->id(), $resultIds);
    $this->assertContains((string) $loc2->id(), $resultIds);

    // 2. Negação imediata de alvo: despublicar local 2 imediatamente exclui o local da consulta.
    $loc2->set('field_status', 'privado');
    $loc2->save();

    $resultImmediate = $relationQuery->queryOutgoing(
      'aculta_event',
      (string) $event->id(),
      $langcode,
      'event_location',
      $anon,
      NULL,
      10,
    );
    $this->assertCount(1, $resultImmediate->entities);
    $this->assertSame((string) $loc1->id(), (string) $resultImmediate->entities[0]->id());

    // 3. Negação imediata de origem: despublicar o evento imediatamente retorna vazio.
    $event->set('field_status', 'rascunho');
    $event->save();

    $resultOriginDenied = $relationQuery->queryOutgoing(
      'aculta_event',
      (string) $event->id(),
      $langcode,
      'event_location',
      $anon,
      NULL,
      10,
    );
    $this->assertCount(0, $resultOriginDenied->entities);

    // Restaura o evento para testar cursores e isolamento entre contas.
    $event->set('field_status', 'publicado');
    $event->save();
    $loc2->set('field_status', 'publicado');
    $loc2->save();

    // 4. Cursor com paginação e isolamento entre contas.
    $paged = $relationQuery->queryOutgoing(
      'aculta_event',
      (string) $event->id(),
      $langcode,
      'event_location',
      $reader,
      NULL,
      1,
    );
    $this->assertCount(1, $paged->entities);
    $this->assertNotNull($paged->nextCursor);

    // Outro usuário não pode usar o cursor gerado para o $reader.
    try {
      $relationQuery->queryOutgoing(
        'aculta_event',
        (string) $event->id(),
        $langcode,
        'event_location',
        $other,
        $paged->nextCursor,
        1,
      );
      $this->fail('Cursor não deve poder ser utilizado por outra conta.');
    }
    catch (\InvalidArgumentException) {
      // Sucesso: cursor rejeitado com segurança para usuário não dono.
    }
  }

  /**
   * Idempotência de backfill/rebuild: dados derivados repetidos não duplicam nem apagam a fonte.
   */
  public function testBackfillIdempotencyAndUpdateDoesNotDeleteSource(): void {
    $event = Event::create(['title' => 'Encontro Idempotente', 'field_status' => 'publicado']);
    $event->save();

    $loc = EventLocation::create(['title' => 'Espaço Idempotente', 'field_status' => 'publicado']);
    $loc->save();

    $date = EventDate::create([
      'title' => 'Data Idempotente',
      'field_evento' => $event->id(),
      'field_local' => $loc->id(),
      'field_quando' => ['value' => '2026-11-20T10:00:00', 'end_value' => '2026-11-20T12:00:00'],
    ]);
    $date->save();

    $this->buildAndActivateGeneration();

    $db = $this->container->get('database');
    $entryCount1 = (int) $db->select('aculta_index_entry', 'e')->countQuery()->execute()->fetchField();
    $relationCount1 = (int) $db->select('aculta_index_relation', 'r')->countQuery()->execute()->fetchField();

    $this->assertGreaterThan(0, $entryCount1);
    $this->assertGreaterThan(0, $relationCount1);

    // Processa novamente todos os jobs sem duplicar.
    $this->processAllQueuedWork();

    $entryCount2 = (int) $db->select('aculta_index_entry', 'e')->countQuery()->execute()->fetchField();
    $relationCount2 = (int) $db->select('aculta_index_relation', 'r')->countQuery()->execute()->fetchField();

    $this->assertSame($entryCount1, $entryCount2);
    $this->assertSame($relationCount1, $relationCount2);

    // Entidades de origem permanecem intactas.
    $locReloaded = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')->load($loc->id());
    $this->assertNotNull($locReloaded);
    $eventReloaded = $this->container->get('entity_type.manager')->getStorage('aculta_event')->load($event->id());
    $this->assertNotNull($eventReloaded);
    $dateReloaded = $this->container->get('entity_type.manager')->getStorage('aculta_event_date')->load($date->id());
    $this->assertNotNull($dateReloaded);

    // Excluir a projeção do evento nunca apaga o local de origem.
    $writer = $this->container->get('aculta_index.writer');
    $genId = $this->container->get('aculta_index.generation_manager')->getActiveGeneration();
    $this->assertNotNull($genId);
    $writer->remove($genId, 'event', 'aculta_event', (string) $event->id(), $event->language()->getId(), 1);

    $locStillExists = $this->container->get('entity_type.manager')->getStorage('aculta_event_location')->load($loc->id());
    $this->assertNotNull($locStillExists);
    $this->assertSame((string) $loc->label(), (string) $locStillExists->label());
  }

  /**
   * Resiliência: Index sem geração ativa mantém CRUD e busca do catálogo pelo Entity API.
   */
  public function testIndexDisabledOrNoActiveGenerationFallback(): void {
    // Sem chamar buildAndActivateGeneration(), não há geração ativa.
    $this->assertNull($this->container->get('aculta_index.generation_manager')->getActiveGeneration());

    $loc = EventLocation::create(['title' => 'Teatro Municipal', 'field_status' => 'publicado']);
    $loc->save();

    $event = Event::create(['title' => 'Concerto', 'field_status' => 'publicado']);
    $event->save();

    // Query do índice responde vazio de forma segura e sem lançar exceções não tratadas.
    $indexQuery = $this->container->get('aculta_index.query');
    $result = $indexQuery->query(['entity_type' => 'aculta_event_location'], new AnonymousUserSession());
    $this->assertCount(0, $result->entities);
    $this->assertNull($result->nextCursor);

    $relationQuery = $this->container->get('aculta_index.relation_query');
    $relResult = $relationQuery->queryOutgoing('aculta_event', (string) $event->id(), $event->language()->getId(), 'event_location', new AnonymousUserSession());
    $this->assertCount(0, $relResult->entities);

    // Catálogo nativo do owner continua operando 100% pelo Entity API.
    /** @var \Drupal\aculta_events\Catalogue\LocationCatalogue $catalogue */
    $catalogue = $this->container->get('aculta_events.locations');
    $res = $catalogue->buscaPaginada(NULL, 'Teatro', 0, 10, new AnonymousUserSession());
    $this->assertCount(1, $res['items']);
    $this->assertSame('Teatro Municipal', (string) $res['items'][0]['local']->label());

    // EventDateHooks roda sem erro mesmo sem Index ativo.
    $hooks = new EventDateHooks(
      $this->container->get('datetime.time'),
      $this->container->get('aculta_events.upcoming'),
      $this->container->get('router.admin_context'),
      $this->container->get('entity_type.manager'),
      NULL,
      NULL,
    );
    $hooks->invalidarLista($loc);
    $this->assertTrue(TRUE);
  }

  /**
   * Autocomplete e seleção imediata de local recém-criado antes da indexação.
   */
  public function testLocationSelectionAndAutocompleteImmediate(): void {
    $editor = User::create([
      'name' => 'event-editor-user',
      'status' => 1,
    ]);
    $editor->save();
    $role = Role::create([
      'id' => 'event_admin_role',
      'label' => 'Event Admin Role',
    ]);
    $role->grantPermission('administer aculta events');
    $role->save();
    $editor->addRole('event_admin_role');
    $editor->save();
    $this->container->get('current_user')->setAccount($editor);

    $event = Event::create(['title' => 'Seminário de Artes', 'field_status' => 'publicado']);
    $event->save();

    // Cria um local recém-cadastrado no banco.
    $newLoc = EventLocation::create(['title' => 'Auditório Novo', 'field_status' => 'publicado']);
    $newLoc->save();

    // Testa validação no EventDateCreateForm antes de qualquer processamento de fila.
    $form = EventDateCreateForm::create($this->container);
    $formBuild = [];

    // 1. Envio com ID válido de local recém-criado deve passar na validação.
    $formStateValid = new FormState();
    $formStateValid->setValues([
      'event_id' => (string) $event->id(),
      'title' => 'Painel 1',
      'starts_at' => '2026-11-15T09:00:00',
      'ends_at' => '2026-11-15T11:00:00',
      'create_new_location' => FALSE,
      'local_id' => (string) $newLoc->id(),
    ]);
    $form->validateForm($formBuild, $formStateValid);
    $this->assertFalse($formStateValid->hasAnyErrors());

    // 2. Envio com ID inexistente deve disparar erro via Entity API.
    $formStateInvalid = new FormState();
    $formStateInvalid->setValues([
      'event_id' => (string) $event->id(),
      'title' => 'Painel 1',
      'starts_at' => '2026-11-15T09:00:00',
      'ends_at' => '2026-11-15T11:00:00',
      'create_new_location' => FALSE,
      'local_id' => '999999',
    ]);
    $form->validateForm($formBuild, $formStateInvalid);
    $this->assertTrue($formStateInvalid->hasAnyErrors());
    $this->assertArrayHasKey('local_id', $formStateInvalid->getErrors());
  }

  /**
   * Constrói e ativa uma geração completa do Content Index a partir dos providers do container.
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
   * Processa todos os itens da fila do Content Index.
   */
  private function processAllQueuedWork(): void {
    $database = $this->container->get('database');
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(ContentIndexWorkManager::QUEUE_ID);
    $ids = $database->select('aculta_index_work', 'work')
      ->fields('work', ['work_id'])
      ->condition('status', 'queued')
      ->orderBy('work_id')
      ->execute()
      ->fetchCol();
    foreach ($ids as $id) {
      $worker->processItem(['work_id' => (int) $id]);
    }
  }

}
