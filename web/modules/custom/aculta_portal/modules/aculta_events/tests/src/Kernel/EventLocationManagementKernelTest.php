<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Form\EventDateCreateForm;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Valida gestão, ciclo de vida, permissões e agenda de locais (LOC-01 a 06).
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventLocationManagementKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'options',
    'link',
    'text',
    'filter',
    'taxonomy',
    'datetime',
    'datetime_range',
    'address',
    'geofield',
    'aculta_calendar',
    'aculta_maps',
    'aculta_events',
  ];

  /**
   * Usuário administrador.
   */
  private User $adminUser;

  /**
   * Usuário organizador.
   */
  private User $organizerUser;

  /**
   * Usuário comum / anônimo.
   */
  private User $commonUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');

    $roleAdmin = Role::create([
      'id' => 'admin_events',
      'label' => 'Admin Events',
    ]);
    $roleAdmin->grantPermission('administer aculta events');
    $roleAdmin->save();

    $this->adminUser = User::create(['name' => 'admin', 'mail' => 'admin@example.com', 'status' => 1]);
    $this->adminUser->addRole('admin_events');
    $this->adminUser->save();

    $this->organizerUser = User::create(['name' => 'organizer', 'mail' => 'org@example.com', 'status' => 1]);
    $this->organizerUser->save();

    $this->commonUser = User::create(['name' => 'common', 'mail' => 'common@example.com', 'status' => 1]);
    $this->commonUser->save();
  }

  /**
   * Valida controle de acesso e visibilidade por status (LOC-01).
   */
  public function testLocationAccessAndStatus(): void {
    $publicLocation = EventLocation::create([
      'title' => 'Espaço Cultural Aberto',
      'field_status' => 'publicado',
      'field_organizador' => $this->organizerUser->id(),
    ]);
    $publicLocation->save();

    $draftLocation = EventLocation::create([
      'title' => 'Espaço em Reforma (Rascunho)',
      'field_status' => 'rascunho',
      'field_organizador' => $this->organizerUser->id(),
    ]);
    $draftLocation->save();

    $privateLocation = EventLocation::create([
      'title' => 'Galpão Privado',
      'field_status' => 'privado',
      'field_organizador' => $this->organizerUser->id(),
    ]);
    $privateLocation->save();

    // 1. Anônimo tem acesso apenas ao público.
    $anon = new AnonymousUserSession();
    $this->assertTrue($publicLocation->access('view', $anon));
    $this->assertFalse($draftLocation->access('view', $anon));
    $this->assertFalse($privateLocation->access('view', $anon));

    // 2. Usuário comum (não organizador) também não vê rascunho nem privado.
    $this->assertTrue($publicLocation->access('view', $this->commonUser));
    $this->assertFalse($draftLocation->access('view', $this->commonUser));
    $this->assertFalse($privateLocation->access('view', $this->commonUser));

    // 3. Administrador vê todos.
    $this->assertTrue($publicLocation->access('view', $this->adminUser));
    $this->assertTrue($draftLocation->access('view', $this->adminUser));
    $this->assertTrue($privateLocation->access('view', $this->adminUser));

    // 4. Organizador vê seus próprios locais em rascunho e privados.
    $this->assertTrue($publicLocation->access('view', $this->organizerUser));
    $this->assertTrue($draftLocation->access('view', $this->organizerUser));
    $this->assertTrue($privateLocation->access('view', $this->organizerUser));
  }

  /**
   * Valida bloqueio de exclusão de local em uso por datas (LOC-01/02).
   */
  public function testLocationInUseCannotBeDeleted(): void {
    $location = EventLocation::create([
      'title' => 'Teatro Municipal',
      'field_status' => 'publicado',
      'field_organizador' => $this->organizerUser->id(),
    ]);
    $location->save();

    // Sem datas vinculadas: administrador pode excluir.
    $this->assertTrue($location->access('delete', $this->adminUser));

    $event = Event::create([
      'title' => 'Peça de Teatro',
      'field_status' => 'publicado',
    ]);
    $event->save();

    $date = EventDate::create([
      'title' => 'Sessão 1',
      'field_evento' => $event->id(),
      'field_local' => $location->id(),
      'field_quando' => ['value' => '2026-12-01T20:00:00', 'end_value' => '2026-12-01T22:00:00'],
    ]);
    $date->save();

    // Recarrega o local e reseta o cache de acesso após a data ser associada.
    $locStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_location');
    $accessHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('aculta_event_location');
    $locStorage->resetCache([$location->id()]);
    $accessHandler->resetCache();
    $reloaded = $locStorage->load($location->id());

    // Com data vinculada: exclusão é bloqueada (LOC-01/02).
    $this->assertFalse($reloaded->access('delete', $this->adminUser));

    // Ao remover a associação da data, a exclusão volta a ser permitida.
    $date->delete();
    $locStorage->resetCache([$location->id()]);
    $accessHandler->resetCache();
    $reloadedAfterDelete = $locStorage->load($location->id());
    $this->assertTrue($reloadedAfterDelete->access('delete', $this->adminUser));
  }

  /**
   * Valida criação inline e reuso de local no formulário de data (LOC-03).
   */
  public function testLocationCreationAndReuseInDateForm(): void {
    $event1 = Event::create(['title' => 'Festival Primavera', 'field_status' => 'publicado']);
    $event1->save();
    $event2 = Event::create(['title' => 'Feira Outono', 'field_status' => 'publicado']);
    $event2->save();

    $this->container->get('current_user')->setAccount($this->adminUser);

    // 1. Cria nova data com cadastro inline de local.
    $form1 = EventDateCreateForm::create($this->container);
    $formState1 = new FormState();
    $formState1->setValues([
      'event_id' => (string) $event1->id(),
      'title' => 'Abertura do Festival',
      'starts_at' => new DrupalDateTime('2026-11-10 14:00:00'),
      'ends_at' => new DrupalDateTime('2026-11-10 18:00:00'),
      'create_new_location' => 1,
      'new_location_name' => 'Parque das Flores',
      'new_location_precision' => 'exato',
      'new_location_city' => 'Goiânia',
      'new_location_state' => 'GO',
      'new_location_address' => 'Av. Central, 500',
    ]);

    $rawForm1 = [];
    $form1->submitForm($rawForm1, $formState1);

    // Verifica que o local foi criado e vinculado à data do Evento 1.
    $locStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_location');
    $createdLocations = $locStorage->loadByProperties(['title' => 'Parque das Flores']);
    $this->assertCount(1, $createdLocations);
    /** @var \Drupal\aculta_events\Entity\EventLocation $newLoc */
    $newLoc = reset($createdLocations);
    $this->assertSame('Parque das Flores', (string) $newLoc->label());
    $this->assertSame('publicado', (string) $newLoc->get('field_status')->value);

    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dates1 = $dateStorage->loadByProperties(['field_evento' => $event1->id()]);
    $this->assertCount(1, $dates1);
    $d1 = reset($dates1);
    $this->assertSame((int) $newLoc->id(), (int) $d1->get('field_local')->target_id);

    // 2. Evento 2 reutiliza o mesmo local sem duplicá-lo.
    $form2 = EventDateCreateForm::create($this->container);
    $formState2 = new FormState();
    $formState2->setValues([
      'event_id' => (string) $event2->id(),
      'title' => 'Dia Principal da Feira',
      'starts_at' => new DrupalDateTime('2026-11-15 09:00:00'),
      'ends_at' => new DrupalDateTime('2026-11-15 17:00:00'),
      'create_new_location' => 0,
      'local_id' => (string) $newLoc->id(),
    ]);

    $rawForm2 = [];
    $form2->submitForm($rawForm2, $formState2);

    $dates2 = $dateStorage->loadByProperties(['field_evento' => $event2->id()]);
    $this->assertCount(1, $dates2);
    $d2 = reset($dates2);
    $this->assertSame((int) $newLoc->id(), (int) $d2->get('field_local')->target_id);

    // Confirma que não duplicou: segue existindo 1 Parque das Flores.
    $allLocations = $locStorage->loadByProperties(['title' => 'Parque das Flores']);
    $this->assertCount(1, $allLocations);
  }

  /**
   * Valida página canônica de Local e renderização de sua agenda (LOC-05).
   */
  public function testLocationCanonicalPageAndAgenda(): void {
    $location = EventLocation::create([
      'title' => 'Centro Cultural ACULTA',
      'field_status' => 'publicado',
      'field_descricao' => 'Espaço dedicado a eventos e oficinas.',
      'field_horario' => 'Segunda a Sexta, das 9h às 18h',
      'field_organizador' => $this->organizerUser->id(),
    ]);
    $location->save();

    $event = Event::create([
      'title' => 'Sarau Poético',
      'field_status' => 'publicado',
      'field_slug' => 'sarau-poetico-2026',
    ]);
    $event->save();

    $date = EventDate::create([
      'title' => 'Noite de Declamação',
      'field_evento' => $event->id(),
      'field_local' => $location->id(),
      'field_quando' => ['value' => '2026-12-10T19:00:00', 'end_value' => '2026-12-10T22:00:00'],
      'field_gratuito' => TRUE,
    ]);
    $date->save();

    /** @var \Drupal\aculta_events\Controller\LocationPageController $controller */
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(
      '\Drupal\aculta_events\Controller\LocationPageController'
    );

    $build = $controller->page($location);
    $this->assertIsArray($build);
    $this->assertSame('Centro Cultural ACULTA', (string) $build['header']['title']['#value']);
    $this->assertNotEmpty($build['agenda']);

    // Usuário anônimo em local rascunho recebe 404.
    $draftLoc = EventLocation::create(['title' => 'Privado', 'field_status' => 'rascunho']);
    $draftLoc->save();

    $this->expectException(NotFoundHttpException::class);
    $controller->page($draftLoc);
  }

  /**
   * Valida diretório paginado e busca textual (LOC-05).
   */
  public function testLocationCataloguePaginationAndSearch(): void {
    for ($i = 1; $i <= 5; $i++) {
      EventLocation::create([
        'title' => 'Espaço Alfa ' . $i,
        'field_status' => 'publicado',
      ])->save();
    }
    for ($i = 1; $i <= 3; $i++) {
      EventLocation::create([
        'title' => 'Galpão Beta ' . $i,
        'field_status' => 'publicado',
      ])->save();
    }

    /** @var \Drupal\aculta_events\Catalogue\LocationCatalogue $catalogue */
    $catalogue = $this->container->get('aculta_events.locations');

    // Busca textual por 'Alfa' deve retornar exatamente 5 itens.
    $resAlfa = $catalogue->buscaPaginada(NULL, 'Alfa', 0, 10, $this->adminUser);
    $this->assertSame(5, $resAlfa['total']);
    $this->assertCount(5, $resAlfa['items']);

    // Busca textual por 'Beta' deve retornar exatamente 3 itens.
    $resBeta = $catalogue->buscaPaginada(NULL, 'Beta', 0, 10, $this->adminUser);
    $this->assertSame(3, $resBeta['total']);
    $this->assertCount(3, $resBeta['items']);

    // Paginação com limite 2 na busca geral (8 locais no total).
    $page0 = $catalogue->buscaPaginada(NULL, NULL, 0, 2, $this->adminUser);
    $this->assertSame(8, $page0['total']);
    $this->assertCount(2, $page0['items']);

    $page1 = $catalogue->buscaPaginada(NULL, NULL, 1, 2, $this->adminUser);
    $this->assertSame(8, $page1['total']);
    $this->assertCount(2, $page1['items']);
  }

  /**
   * Valida formulário próprio de cadastro de local com campos (LOC-02).
   */
  public function testEventLocationStandaloneForm(): void {
    $this->container->get('current_user')->setAccount($this->adminUser);
    $location = EventLocation::create([]);
    /** @var \Drupal\Core\Entity\EntityFormInterface $formObject */
    $formObject = $this->container->get('entity_type.manager')
      ->getFormObject('aculta_event_location', 'add')
      ->setEntity($location);
    $formState = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $formState);

    // Confirma que os campos próprios do formulário de local estão presentes.
    $this->assertArrayHasKey('title', $form);
    $this->assertArrayHasKey('field_tipo', $form);
    $this->assertArrayHasKey('field_status', $form);
    $this->assertArrayHasKey('field_endereco', $form);
    $this->assertArrayHasKey('field_precisao', $form);
    $this->assertArrayHasKey('field_horario', $form);
    $this->assertArrayHasKey('field_descricao', $form);
    $this->assertArrayHasKey('field_telefone', $form);
    $this->assertArrayHasKey('field_site', $form);
  }

  /**
   * Valida formulário de evento com seleção de local (campos avulsos removidos).
   */
  public function testEventFormWithInlineLocationCreation(): void {
    $this->container->get('current_user')->setAccount($this->adminUser);

    // Cria o local previamente via entidade canônica.
    $loc = EventLocation::create([
      'title' => 'Auditório Principal',
      'field_status' => 'publicado',
      'field_precisao' => 'exato',
      'field_endereco' => [
        'country_code' => 'BR',
        'address_line1' => 'Rua da Cultura, 100',
        'locality' => 'São Paulo',
        'administrative_area' => 'SP',
        'postal_code' => '01001-000',
      ],
    ]);
    $loc->save();

    $event = Event::create([]);
    /** @var \Drupal\Core\Entity\EntityFormInterface $formObject */
    $formObject = $this->container->get('entity_type.manager')
      ->getFormObject('aculta_event', 'add')
      ->setEntity($event);
    $formState = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $formState);

    $this->assertArrayHasKey('title', $form);
    $this->assertArrayHasKey('body', $form);
    $this->assertArrayHasKey('field_tipo', $form);
    $this->assertArrayHasKey('field_status', $form);
    $this->assertArrayHasKey('date_location', $form);
    $this->assertArrayHasKey('local_id', $form['date_location']);
    $this->assertArrayNotHasKey('cadastrar_novo_local', $form['date_location']);
    $this->assertArrayNotHasKey('novo_local', $form['date_location']);

    // Simula submissão do formulário de evento com seleção do local.
    $formState->setValues([
      'title' => [['value' => 'Concerto Noturno']],
      'field_status' => [['value' => 'publicado']],
      'local_id' => (string) $loc->id(),
      'starts_at' => new DrupalDateTime('2026-12-15 20:00:00'),
      'ends_at' => new DrupalDateTime('2026-12-15 22:00:00'),
    ]);

    $formObject->validateForm($form, $formState);
    $this->assertEmpty($formState->getErrors());

    $formObject->submitForm($form, $formState);
    $formObject->save($form, $formState);
    $savedEvent = $formObject->getEntity();
    $this->assertNotEmpty($savedEvent->id());

    // Confirma que a data associada foi criada vinculando ao local selecionado.
    $dateStorage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $dates = $dateStorage->loadByProperties(['field_evento' => $savedEvent->id()]);
    $this->assertCount(1, $dates);
    $newDate = reset($dates);
    $this->assertSame((int) $loc->id(), (int) $newDate->get('field_local')->target_id);
  }

}
