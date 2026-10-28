<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Form\EventDateCreateForm;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers E4's reference-only event prefill and access checks.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventDateCreatePrefillKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'taxonomy', 'link', 'text', 'filter', 'options',
    'datetime', 'datetime_range',
    'aculta_calendar', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installConfig(['system', 'user']);
    $this->createUser();
    $this->installEntitySchema('aculta_event');
    $this->installEntitySchema('aculta_event_date');
  }

  /**
   * An editor gets only the editable event reference as the default.
   */
  public function testEditorGetsEditableEventReference(): void {
    $editor = $this->createUser(['administer aculta events']);
    $event = $this->event('publicado');
    $this->setCurrentUser($editor);
    $form = $this->buildFormForEventId((string) $event->id());

    self::assertSame($event->id(), $form['event_id']['#default_value']->id());
    self::assertArrayNotHasKey('field_people_reference', $form);
    self::assertArrayNotHasKey('field_organizador', $form);
    self::assertTrue($this->container->get('access_manager')
      ->checkNamedRoute('aculta_events.date_add', [], $editor, TRUE)->isAllowed());
  }

  /**
   * A view-only account never receives a link to an event in the form.
   */
  public function testAccountWithoutEventEditAccessGetsNoReference(): void {
    $reader = $this->createUser();
    $event = $this->event('publicado');
    $this->setCurrentUser($reader);
    $form = $this->buildFormForEventId((string) $event->id());

    self::assertNull($form['event_id']['#default_value']);
    self::assertFalse($this->container->get('access_manager')
      ->checkNamedRoute('aculta_events.date_add', [], $reader, TRUE)->isAllowed());
  }

  /**
   * An unknown ID and a non-editable unpublished event leave a blank form.
   */
  public function testMissingOrUneditableEventDoesNotBreakForm(): void {
    $reader = $this->createUser();
    $draft = $this->event('rascunho');
    $this->setCurrentUser($reader);
    foreach (['999999', (string) $draft->id()] as $event_id) {
      $form = $this->buildFormForEventId($event_id);
      self::assertNull($form['event_id']['#default_value']);
      self::assertArrayHasKey('starts_at', $form);
      self::assertArrayHasKey('ends_at', $form);
    }
  }

  /**
   * Creates an event with a selected publication state.
   */
  private function event(string $status): Event {
    $event = Event::create(['title' => 'Evento para prefill', 'field_status' => $status]);
    $event->save();
    return $event;
  }

  /**
   * Sets the authenticated account for route and entity access checks.
   */
  private function setCurrentUser(User $user): void {
    $this->container->get('current_user')->setAccount($user);
  }

  /**
   * Pushes the request query used by the creation form.
   */
  private function buildFormForEventId(string $event_id): array {
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create('/painel-administrativo/eventos/datas/adicionar', 'GET', ['event_id' => $event_id]));
    try {
      return EventDateCreateForm::create($this->container)->buildForm([], new FormState());
    }
    finally {
      $request_stack->pop();
    }
  }

}
