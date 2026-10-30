<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\EventAdminOverviewController;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\EventSubscriber\ActivityRetiredRouteSubscriber;
use Drupal\aculta_events\Form\EventForm;
use Drupal\aculta_events\Form\EventLocationForm;
use Drupal\aculta_events\Form\EventLocationModalForm;
use Drupal\aculta_events\Hook\ActivityRetirementHooks;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Testes integrados para o painel administrativo de eventos, exclusão de atividades e modal de locais.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventLocationModalAndAdminKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
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
    'node',
    'aculta_calendar',
    'aculta_maps',
    'aculta_events',
  ];

  private User $adminUser;
  private User $regularUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system']);

    $this->adminUser = User::create([
      'name' => 'admin_test',
      'mail' => 'admin@example.com',
      'status' => 1,
    ]);
    $this->adminUser->save();

    $this->regularUser = User::create([
      'name' => 'regular_test',
      'mail' => 'regular@example.com',
      'status' => 1,
    ]);
    $this->regularUser->save();

    $this->container->get('current_user')->setAccount($this->adminUser);
  }

  private function createField(string $entityType, string $bundle, string $fieldName, string $type): void {
    if (!FieldStorageConfig::loadByName($entityType, $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'type' => $type,
      ])->save();
    }
    if (!FieldConfig::loadByName($entityType, $bundle, $fieldName)) {
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entityType,
        'bundle' => $bundle,
        'label' => $fieldName,
      ])->save();
    }
  }

  /**
   * Valida a regra de país fixo no Brasil (ADR-034).
   */
  public function testCountryFixedToBrazil(): void {
    $location = EventLocation::create([
      'title' => 'Centro Cultural',
      'field_status' => 'publicado',
      'field_endereco' => [
        'country_code' => 'US',
        'address_line1' => 'Rua das Flores, 123',
        'locality' => 'São Paulo',
        'administrative_area' => 'SP',
        'postal_code' => '01310-100',
      ],
    ]);
    $location->save();

    self::assertSame('BR', $location->get('field_endereco')->country_code);

    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_event_location', 'default');
    $formObject->setEntity(EventLocation::create());
    $formState = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $formState);
    $formState->setValue('field_endereco', [
      [
        'address' => [
          'country_code' => 'FR',
          'address_line1' => 'Rue de Paris',
        ],
      ],
    ]);
    $formObject->validateForm($form, $formState);
    self::assertTrue($formState->hasAnyErrors());
    self::assertArrayHasKey('field_endereco', $formState->getErrors());
  }

  /**
   * Valida remoção de campos de endereço do EventForm e preservação do local_id.
   */
  public function testEventFormAddressRemoval(): void {
    $location = EventLocation::create([
      'title' => 'Teatro Municipal',
      'field_status' => 'publicado',
    ]);
    $location->save();

    $event = Event::create(['title' => 'Concerto']);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_event', 'default');
    $formObject->setEntity($event);
    $formState = new FormState();

    $form = $this->container->get('form_builder')->buildForm($formObject, $formState);

    self::assertArrayHasKey('dates', $form['date_location']);
    self::assertArrayHasKey('local_id', $form['date_location']['dates'][0]);
    self::assertArrayNotHasKey('cadastrar_novo_local', $form['date_location']);
    self::assertArrayNotHasKey('novo_local', $form['date_location']);

    $formState->setValue(['date_location', 'dates', 0, 'local_id', 'target_id'], (string) $location->id());
    $formObject->save($form, $formState);
    self::assertFalse($event->isNew());
  }

  /**
   * Valida que rotas legadas de atividade (/node/add/activity) respondem 404 e são bloqueadas.
   */
  public function testActivityRouteIsRetiredAndBlocked(): void {
    $request = Request::create('/node/add/activity');
    $subscriber = new ActivityRetiredRouteSubscriber();
    $kernel = $this->createMock(HttpKernelInterface::class);
    $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

    $this->expectException(NotFoundHttpException::class);
    $subscriber->onKernelRequest($event);
  }

  /**
   * Valida que o link legado de criação de atividade é removido do menu Criar e eventos são adicionados.
   */
  public function testActivityNavigationLinkRemoved(): void {
    $hooks = new ActivityRetirementHooks();
    $links = [
      'navigation.create' => ['title' => 'Criar'],
      'navigation.content.node_type.activity' => ['title' => 'Atividade / evento'],
      'navigation.content.node_type.article' => ['title' => 'Artigo'],
    ];

    $hooks->menuLinksDiscoveredAlter($links);

    self::assertArrayNotHasKey('navigation.content.node_type.activity', $links);
    self::assertArrayHasKey('navigation.content.node_type.article', $links);
    self::assertArrayHasKey('navigation.content.aculta_event.create', $links);
    self::assertSame('aculta_events.event_add', $links['navigation.content.aculta_event.create']['route_name']);
    self::assertArrayHasKey('navigation.content.aculta_event_location.create', $links);
    self::assertSame('aculta_events.location_add', $links['navigation.content.aculta_event_location.create']['route_name']);
  }

  /**
   * Valida renderização do painel administrativo de eventos (somente eventos, datas e locais).
   */
  public function testEventAdminOverviewController(): void {
    $controller = new EventAdminOverviewController($this->container->get('entity_type.manager'));
    $build = $controller->overview();

    self::assertArrayHasKey('metrics', $build);
    self::assertArrayHasKey('quick_actions', $build);
    self::assertCount(3, $build['metrics']['#rows']);
    self::assertCount(7, $build['quick_actions']['#items']);
  }

  /**
   * Valida o funcionamento das duas etapas do modal AJAX de local.
   */
  public function testEventLocationModalFormWorkflow(): void {
    $existing = EventLocation::create([
      'title' => 'Galeria Arte Livre',
      'field_status' => 'publicado',
    ]);
    $existing->save();

    $modalForm = new EventLocationModalForm(
      $this->container->get('entity_type.manager'),
      $this->container->get('aculta_events.permission_policy'),
    );

    // 1. Etapa de busca
    $formStateSearch = new FormState();
    $formStateSearch->set('mode', 'search');
    $formSearch = $modalForm->buildForm([], $formStateSearch);

    self::assertArrayHasKey('search_bar', $formSearch);
    self::assertArrayHasKey('locations_table', $formSearch);
    self::assertArrayHasKey((int) $existing->id(), $formSearch['locations_table']['#rows']);

    // Selecionar existente
    $formStateSearch->set('selected_location_id', (int) $existing->id());
    $formStateSearch->set('field_name', 'field_event_location');
    $ajaxResponse = $modalForm->ajaxSelectLocation($formSearch, $formStateSearch);

    $commands = $ajaxResponse->getCommands();
    self::assertNotEmpty($commands);
    $closeFound = false;
    $replaceFound = false;
    $invokeFound = false;
    foreach ($commands as $cmd) {
      if ($cmd instanceof CloseModalDialogCommand || ($cmd['command'] ?? '') === 'closeDialog') {
        $closeFound = true;
      }
      if ($cmd instanceof ReplaceCommand || ($cmd['command'] ?? '') === 'insert') {
        $replaceFound = true;
      }
      if ($cmd instanceof InvokeCommand || ($cmd['command'] ?? '') === 'invoke') {
        $invokeFound = true;
      }
    }
    self::assertTrue($closeFound);
    self::assertTrue($replaceFound);
    self::assertTrue($invokeFound);

    // 2. Etapa de criação de novo local
    $formStateCreate = new FormState();
    $formStateCreate->set('mode', 'create');
    $formCreate = $modalForm->buildForm([], $formStateCreate);

    self::assertArrayHasKey('novo_nome', $formCreate);
    self::assertArrayHasKey('field_endereco', $formCreate);

    $formStateCreate->setValue('novo_nome', 'Novo Galpão Criativo');
    $formStateCreate->setValue(['field_endereco', 0], [
      'country_code' => 'BR',
      'address_line1' => 'Rua Criativa, 42',
      'locality' => 'Curitiba',
      'administrative_area' => 'PR',
      'postal_code' => '80000-000',
    ]);
    $modalForm->submitCreateLocation($formCreate, $formStateCreate);
    $newLocId = (int) $formStateCreate->get('selected_location_id');
    self::assertGreaterThan(0, $newLocId);

    $newLoc = EventLocation::load($newLocId);
    self::assertNotNull($newLoc);
    self::assertSame('Novo Galpão Criativo', $newLoc->label());
    self::assertSame('BR', $newLoc->get('field_endereco')->country_code);
  }

}
