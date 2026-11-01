<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers creating an Event with several Dates and managing them from the Event.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventFormDatesKernelTest extends KernelTestBase {

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
    $this->container->get('current_user')->setAccount($this->editor);
  }

  /**
   * A new Event exposes one or more Date rows inside its own form.
   */
  public function testNewEventFormOffersMultipleDates(): void {
    $event = Event::create(['title' => 'Mostra de cinema', 'field_status' => 'publicado']);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_event', 'default');
    $formObject->setEntity($event);
    $state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $state);

    self::assertArrayHasKey('date_location', $form);
    self::assertArrayHasKey('dates', $form['date_location']);
    self::assertArrayHasKey(0, $form['date_location']['dates']);
    self::assertArrayHasKey('local_id', $form['date_location']['dates'][0]);
    self::assertArrayHasKey('date_add_more', $form);

    // Add a second row through the AJAX submit handler.
    $state->set('date_delta', 1);
    $formObject->addDateRow($form, $state);
    self::assertSame(2, $state->get('date_delta'));
  }

  /**
   * Saving a new Event creates every configured Date.
   */
  public function testSavingEventCreatesEveryDate(): void {
    $event = Event::create(['title' => 'Festival de verão', 'field_status' => 'publicado']);
    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_event', 'default');
    $formObject->setEntity($event);
    $state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $state);

    $state->setValue(['date_location', 'dates', 0], [
      'title' => 'Primeira sessão',
      'starts_at' => new DrupalDateTime('2026-12-10 18:00:00'),
      'ends_at' => new DrupalDateTime('2026-12-10 20:00:00'),
      'local_id' => ['target_id' => ''],
    ]);
    $state->setValue(['date_location', 'dates', 1], [
      'title' => 'Segunda sessão',
      'starts_at' => new DrupalDateTime('2026-12-11 18:00:00'),
      'ends_at' => new DrupalDateTime('2026-12-11 20:00:00'),
      'local_id' => ['target_id' => ''],
    ]);

    $formObject->save($form, $state);

    $dates = $this->container->get('entity_type.manager')
      ->getStorage('aculta_event_date')
      ->loadByProperties(['field_evento' => $event->id()]);
    self::assertCount(2, $dates);
    $titles = array_map(static fn (EventDate $date): string => (string) $date->label(), $dates);
    sort($titles);
    self::assertSame(['Primeira sessão', 'Segunda sessão'], $titles);
    $weights = array_map(static fn (EventDate $date): int => (int) $date->get('field_peso')->value, $dates);
    sort($weights);
    self::assertSame([0, 1], $weights);
  }

  /**
   * The Event edit form lists its Dates and links them inside the Event.
   */
  public function testEventEditFormListsOwnDates(): void {
    $event = Event::create(['title' => 'Concerto', 'field_status' => 'publicado']);
    $event->save();
    $date = EventDate::create([
      'title' => 'Noite de gala',
      'field_evento' => $event->id(),
      'field_quando' => ['value' => '2026-12-20T20:00:00', 'end_value' => '2026-12-20T22:00:00'],
    ]);
    $date->save();

    $formObject = $this->container->get('entity_type.manager')->getFormObject('aculta_event', 'default');
    $formObject->setEntity($event);
    $state = new FormState();
    $form = $this->container->get('form_builder')->buildForm($formObject, $state);

    self::assertArrayHasKey('event_dates', $form);
    $list = $form['event_dates']['list'];
    self::assertSame('event-dates-list', $list['#attributes']['id']);
    self::assertCount(1, $list['table']['#rows']);
    self::assertSame(
      'aculta_events.event_date_add',
      $list['actions']['add']['#url']->getRouteName()
    );
    self::assertContains('use-ajax', $list['actions']['add']['#attributes']['class']);
  }

}
