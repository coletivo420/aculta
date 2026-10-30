<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Form\EventForm;
use Drupal\aculta_events\Form\EventLocationForm;
use Drupal\aculta_events\Form\EventLocationModalForm;
use Drupal\aculta_events\Controller\EventAdminOverviewController;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Testes integrados para o vínculo de locais em Activity, modal e painel administrativo.
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
    'aculta_activity',
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

    if (!NodeType::load('activity')) {
      NodeType::create([
        'type' => 'activity',
        'name' => 'Atividade',
      ])->save();
    }

    $this->createField('node', 'activity', 'field_modality', 'list_string');
    $this->createField('node', 'activity', 'field_place_name', 'string');
    $this->createField('node', 'activity', 'field_event_street', 'string');
    $this->createField('node', 'activity', 'field_event_city', 'string');
    $this->createField('node', 'activity', 'field_event_region', 'string');
    $this->createField('node', 'activity', 'field_event_postal', 'string');
    $this->createField('node', 'activity', 'field_event_country', 'string');
    $this->createField('node', 'activity', 'field_online_url', 'string');
    $this->createField('node', 'activity', 'field_event_start', 'string');
    $this->createField('node', 'activity', 'field_event_end', 'string');

    // Instala field_event_location referenciando aculta_event_location.
    if (!FieldStorageConfig::loadByName('node', 'field_event_location')) {
      FieldStorageConfig::create([
        'field_name' => 'field_event_location',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'aculta_event_location'],
        'cardinality' => 1,
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'activity', 'field_event_location')) {
      FieldConfig::create([
        'field_name' => 'field_event_location',
        'entity_type' => 'node',
        'bundle' => 'activity',
        'label' => 'Local do evento',
      ])->save();
    }

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

    self::assertArrayHasKey('local_id', $form['date_location']);
    self::assertArrayNotHasKey('cadastrar_novo_local', $form['date_location']);
    self::assertArrayNotHasKey('novo_local', $form['date_location']);

    $formState->setValue('local_id', (string) $location->id());
    $formObject->save($form, $formState);
    self::assertFalse($event->isNew());
  }

  /**
   * Valida sincronização e regras de validação de local em Activity.
   */
  public function testActivityFormLocationIntegration(): void {
    $location = EventLocation::create([
      'title' => 'Espaço Cultural 420',
      'field_status' => 'publicado',
      'field_endereco' => [
        'country_code' => 'BR',
        'address_line1' => 'Av. Paulista, 1000',
        'locality' => 'São Paulo',
        'administrative_area' => 'SP',
        'postal_code' => '01310-100',
      ],
    ]);
    $location->save();

    $activity = Node::create([
      'type' => 'activity',
      'title' => 'Oficina de Plantio',
      'field_modality' => 'presencial',
      'field_event_location' => $location->id(),
    ]);
    $activity->save();

    self::assertSame('Espaço Cultural 420', $activity->get('field_place_name')->value);
    self::assertSame('Av. Paulista, 1000', $activity->get('field_event_street')->value);
    self::assertSame('São Paulo', $activity->get('field_event_city')->value);
    self::assertSame('SP', $activity->get('field_event_region')->value);
    self::assertSame('01310-100', $activity->get('field_event_postal')->value);
    self::assertSame('BR', $activity->get('field_event_country')->value);

    // Validação rejeita presencial sem local
    $emptyActivity = Node::create([
      'type' => 'activity',
      'title' => 'Sem Local',
      'field_modality' => 'presencial',
    ]);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('node', 'default');
    $formObject->setEntity($emptyActivity);
    $formState = new FormState();
    $formState->setFormObject($formObject);
    $form = [];
    $this->container->get('aculta_activity.form_callbacks')->validateActivity($form, $formState);
    self::assertTrue($formState->hasAnyErrors());
  }

  /**
   * Valida renderização do painel administrativo de eventos.
   */
  public function testEventAdminOverviewController(): void {
    $controller = new EventAdminOverviewController($this->container->get('entity_type.manager'));
    $build = $controller->overview();

    self::assertArrayHasKey('metrics', $build);
    self::assertArrayHasKey('quick_actions', $build);
    self::assertCount(4, $build['metrics']['#rows']);
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

    $modalForm = new EventLocationModalForm($this->container->get('entity_type.manager'));

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
