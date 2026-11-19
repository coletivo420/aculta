<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Form\EventDateCreateForm;
use Drupal\aculta_events\Form\EventDateDeleteForm;
use Drupal\aculta_events\Form\EventDateEditForm;
use Drupal\aculta_events\Form\EventDateOrderForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Garante que os envios de Datas usam HTMX com o fragmento certo.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDateHtmxKernelTest extends KernelTestBase {

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
   * Evento usado nos formulários.
   */
  private Event $event;

  /**
   * Data do evento.
   */
  private EventDate $date;

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

    $editor = User::create(['name' => 'editor', 'mail' => 'editor@example.com', 'status' => 1]);
    $editor->addRole('event_admin_role');
    $editor->save();
    $this->container->get('current_user')->setAccount($editor);

    $this->event = Event::create(['title' => 'Mostra de cinema', 'field_status' => 'publicado']);
    $this->event->save();
    $this->date = EventDate::create([
      'title' => 'Sessão de abertura',
      'field_evento' => $this->event->id(),
      'field_quando' => ['value' => '2026-12-10T18:00:00', 'end_value' => '2026-12-10T20:00:00'],
      'field_peso' => 0,
    ]);
    $this->date->save();
  }

  /**
   * Os quatro envios declaram hx-post, hx-select, hx-target e hx-swap.
   */
  public function testDateFormsUseHtmx(): void {
    $builder = $this->container->get('form_builder');
    $this->assertHtmx($builder->getForm(EventDateCreateForm::class, $this->event), 'aculta-event-date-form');
    $this->assertHtmx($builder->getForm(EventDateEditForm::class, $this->event, $this->date), 'aculta-event-date-edit-form');
    $this->assertHtmx($builder->getForm(EventDateDeleteForm::class, $this->event, $this->date), 'aculta-event-date-delete-form');
    $this->assertHtmx($builder->getForm(EventDateOrderForm::class, $this->event), 'aculta-event-date-order-form');
  }

  /**
   * Confere os atributos HTMX do botão de envio.
   */
  private function assertHtmx(array $form, string $wrapper): void {
    $button = $form['actions']['submit'];
    self::assertSame('submit', $button['#type']);
    self::assertNotEmpty((string) $button['#attributes']['data-hx-post']);
    self::assertSame('#' . $wrapper, (string) $button['#attributes']['data-hx-select']);
    self::assertSame('#' . $wrapper, (string) $button['#attributes']['data-hx-target']);
    self::assertStringStartsWith('outerHTML', (string) $button['#attributes']['data-hx-swap']);
    self::assertArrayHasKey('data-hx-drupal-only-main-content', $button['#attributes']);
    self::assertContains('core/drupal.htmx', $button['#attached']['library']);
    self::assertStringContainsString($wrapper, (string) $form['#prefix']);
  }

}
