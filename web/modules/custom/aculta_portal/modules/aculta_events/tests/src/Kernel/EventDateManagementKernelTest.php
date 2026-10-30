<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Access\EventDateRouteAccess;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Form\EventDateCreateForm;
use Drupal\aculta_events\Form\EventDateDeleteForm;
use Drupal\aculta_events\Form\EventDateEditForm;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Covers centralized date management inside the Event context.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDateManagementKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'field', 'key', 'options', 'link', 'text', 'filter',
    'taxonomy', 'datetime', 'datetime_range', 'address', 'geofield',
    'aculta_calendar', 'aculta_events',
  ];

  /**
   * Editor account with permission to administer events.
   */
  private User $editor;

  /**
   * Account without event administration permission.
   */
  private User $reader;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'user', 'taxonomy']);
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
    $this->installEntitySchema('aculta_event_location');

    $role = Role::create(['id' => 'event_admin_role', 'label' => 'Event Admin']);
    $role->grantPermission('administer aculta events');
    $role->save();

    $this->editor = User::create(['name' => 'editor', 'mail' => 'editor@example.com', 'status' => 1]);
    $this->editor->addRole('event_admin_role');
    $this->editor->save();

    $this->reader = User::create(['name' => 'reader', 'mail' => 'reader@example.com', 'status' => 1]);
    $this->reader->save();
  }

  /**
   * The global standalone date creation route must not exist anymore.
   */
  public function testGlobalDateCreationRouteIsRetired(): void {
    $this->expectException(RouteNotFoundException::class);
    $this->container->get('router.route_provider')->getRouteByName('aculta_events.date_add');
  }

  /**
   * Date routes require an authorized Event in the URL.
   */
  public function testDateRouteRequiresAuthorizedEventContext(): void {
    $event = $this->event('publicado');
    $this->setAccount($this->editor);
    self::assertTrue($this->container->get('access_manager')
      ->checkNamedRoute('aculta_events.event_date_add', ['aculta_event' => $event->id()], $this->editor, TRUE)->isAllowed());

    self::assertFalse($this->container->get('access_manager')
      ->checkNamedRoute('aculta_events.event_date_add', ['aculta_event' => $event->id()], $this->reader, TRUE)->isAllowed());

    $draft = $this->event('rascunho');
    self::assertFalse($this->container->get('access_manager')
      ->checkNamedRoute('aculta_events.event_date_add', ['aculta_event' => $draft->id()], $this->reader, TRUE)->isAllowed());
  }

  /**
   * Creating a Date always stores the originating Event.
   */
  public function testCreateDateBindsToEventAndReturnsToIt(): void {
    $event = $this->event('publicado');
    $location = $this->location('Auditório Central');
    $this->setAccount($this->editor);

    $form = EventDateCreateForm::create($this->container);
    $state = new FormState();
    $state->setValues([
      'event_id' => (string) $event->id(),
      'title' => 'Sessão de abertura',
      'starts_at' => new DrupalDateTime('2026-12-01 19:00:00'),
      'ends_at' => new DrupalDateTime('2026-12-01 21:00:00'),
      'local_id' => (string) $location->id(),
    ]);
    $formArray = [];
    $form->submitForm($formArray, $state);

    $dates = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_date')
      ->loadByProperties(['field_evento' => $event->id()]);
    self::assertCount(1, $dates);
    /** @var \Drupal\aculta_events\Entity\EventDate $date */
    $date = reset($dates);
    self::assertSame((int) $event->id(), (int) $date->get('field_evento')->target_id);
    self::assertSame((int) $location->id(), (int) $date->get('field_local')->target_id);

    $redirect = $state->getRedirect();
    self::assertSame('aculta_events.event_edit', $redirect->getRouteName());
    self::assertSame((string) $event->id(), (string) $redirect->getRouteParameters()['aculta_event']);
  }

  /**
   * Editing a Date updates it and returns to the origin Event.
   */
  public function testEditDateUpdatesAndReturnsToEvent(): void {
    $event = $this->event('publicado');
    $date = $this->date($event, 'Sessão original');
    $location = $this->location('Sala nova');
    $this->setAccount($this->editor);

    $form = EventDateEditForm::create($this->container);
    $state = new FormState();
    $state->set('event_id', (int) $event->id());
    $state->set('date_id', (int) $date->id());
    $state->setValues([
      'event_id' => (string) $event->id(),
      'date_id' => (string) $date->id(),
      'title' => 'Sessão revista',
      'starts_at' => new DrupalDateTime('2026-12-02 18:00:00'),
      'ends_at' => new DrupalDateTime('2026-12-02 20:00:00'),
      'local_id' => ['target_id' => (string) $location->id()],
    ]);
    $formArray = [];
    $form->submitForm($formArray, $state);

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $storage->resetCache([$date->id()]);
    $reloaded = $storage->load($date->id());
    self::assertSame('Sessão revista', (string) $reloaded->label());
    self::assertSame((int) $location->id(), (int) $reloaded->get('field_local')->target_id);
    self::assertSame('aculta_events.event_edit', $state->getRedirect()->getRouteName());
  }

  /**
   * A Date from another Event is denied on the server.
   */
  public function testDateCannotBeManagedThroughAnotherEvent(): void {
    $eventA = $this->event('publicado');
    $eventB = $this->event('publicado');
    $dateB = $this->date($eventB, 'Data do evento B');

    self::assertFalse(EventDateRouteAccess::access($eventA, $dateB, $this->editor)->isAllowed());
    self::assertTrue(EventDateRouteAccess::access($eventB, $dateB, $this->editor)->isAllowed());

    $this->setAccount($this->editor);
    $accessManager = $this->container->get('access_manager');
    self::assertTrue($accessManager->checkNamedRoute('aculta_events.event_date_edit', [
      'aculta_event' => $eventB->id(),
      'aculta_event_date' => $dateB->id(),
    ], $this->editor, TRUE)->isAllowed());
    self::assertFalse($accessManager->checkNamedRoute('aculta_events.event_date_edit', [
      'aculta_event' => $eventA->id(),
      'aculta_event_date' => $dateB->id(),
    ], $this->editor, TRUE)->isAllowed());

    $this->expectException(NotFoundHttpException::class);
    EventDateEditForm::create($this->container)->buildForm([], new FormState(), $eventA, $dateB);
  }

  /**
   * Deleting a Date is scoped to the Event and returns to it.
   */
  public function testDeleteDateRemovesOnlyThatDate(): void {
    $event = $this->event('publicado');
    $date = $this->date($event, 'Sessão a remover');
    $this->setAccount($this->editor);

    $form = EventDateDeleteForm::create($this->container);
    $state = new FormState();
    $state->set('event_id', (int) $event->id());
    $state->set('date_id', (int) $date->id());
    $formArray = [];
    $form->submitForm($formArray, $state);

    $storage = $this->container->get('entity_type.manager')->getStorage('aculta_event_date');
    $storage->resetCache([$date->id()]);
    self::assertNull($storage->load($date->id()));
    self::assertSame('aculta_events.event_edit', $state->getRedirect()->getRouteName());
  }

  /**
   * Creates an Event with the given editorial status.
   */
  private function event(string $status): Event {
    $event = Event::create(['title' => 'Evento de teste', 'field_status' => $status]);
    $event->save();
    return $event;
  }

  /**
   * Creates a published reusable Location.
   */
  private function location(string $title): EventLocation {
    $location = EventLocation::create(['title' => $title, 'field_status' => 'publicado']);
    $location->save();
    return $location;
  }

  /**
   * Creates a Date linked to an Event.
   */
  private function date(Event $event, string $title): EventDate {
    $date = EventDate::create([
      'title' => $title,
      'field_evento' => $event->id(),
      'field_quando' => ['value' => '2026-12-01T15:00:00', 'end_value' => '2026-12-01T17:00:00'],
    ]);
    $date->save();
    return $date;
  }

  /**
   * Sets the effective account for access checks.
   */
  private function setAccount(User $user): void {
    $this->container->get('current_user')->setAccount($user);
  }

}
