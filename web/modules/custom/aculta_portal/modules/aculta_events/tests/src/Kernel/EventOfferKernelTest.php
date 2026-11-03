<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventDate;
use Drupal\aculta_events\Entity\EventOffer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Confirma o modelo estrutural de ofertas sem ativar inscrição ou pagamento.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class EventOfferKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_events',
  ];

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
    $this->installEntitySchema('aculta_event_offer');
    $this->container->get('module_handler')->loadInclude('aculta_events', 'install');
  }

  /**
   * Oferta referencia Evento/Data sem duplicar entidades e nasce fechada.
   */
  public function testOfferReferencesExistingEventAndDateAndStartsInactive(): void {
    self::assertStringContainsString('instalada', aculta_events_update_10014());
    self::assertStringContainsString('já estava instalada', aculta_events_update_10014());

    $event = Event::create(['title' => 'Evento sintético', 'field_status' => 'rascunho']);
    $event->save();

    $date = EventDate::create([
      'title' => 'Data sintética',
      'field_evento' => $event->id(),
      'field_quando' => [
        'value' => '2026-12-01T18:00:00',
        'end_value' => '2026-12-01T20:00:00',
      ],
    ]);
    $date->save();

    $offer = EventOffer::create([
      'title' => 'Lote de teste',
      'field_evento' => $event->id(),
      'field_data' => $date->id(),
      'field_modalidade' => 'gratuita',
      'field_capacidade' => 25,
    ]);
    $offer->save();

    $loaded = EventOffer::load($offer->id());
    self::assertNotNull($loaded);
    self::assertSame((string) $event->id(), (string) $loaded->get('field_evento')->target_id);
    self::assertSame((string) $date->id(), (string) $loaded->get('field_data')->target_id);
    self::assertSame('gratuita', $loaded->get('field_modalidade')->value);
    self::assertSame(25, (int) $loaded->get('field_capacidade')->value);
    self::assertFalse((bool) $loaded->get('field_ativa')->value);
    self::assertFalse($loaded->hasField('field_participante'));
    self::assertFalse($loaded->hasField('field_credencial'));

    self::assertFalse($loaded->access('view', new UserSession(['uid' => 102]), TRUE)->isAllowed());
    self::assertFalse($loaded->access('update', new UserSession(['uid' => 102, 'roles' => ['content_editor']]), TRUE)->isAllowed());
    $accessHandler = $this->container->get('entity_type.manager')->getAccessControlHandler('aculta_event_offer');
    self::assertFalse($accessHandler->createAccess(NULL, new UserSession(['uid' => 102]), [], TRUE)->isAllowed());
    Role::create([
      'id' => 'events_offer_admin_test',
      'label' => 'Events offer test administrator',
      'permissions' => ['administer aculta events'],
    ])->save();
    $admin = User::create(['name' => 'events-offer-admin-test', 'status' => 1]);
    $admin->addRole('events_offer_admin_test');
    $admin->save();
    self::assertTrue($loaded->access('view', $admin, TRUE)->isAllowed());
    self::assertTrue($accessHandler->createAccess(NULL, $admin, [], TRUE)->isAllowed());
  }

  /**
   * O tipo não declara endpoint público nem rota de edição organizadora.
   */
  public function testOfferEntityHasNoPublicOrOrganizerRoutes(): void {
    $definition = $this->container->get('entity_type.manager')->getDefinition('aculta_event_offer');

    self::assertSame('administer aculta events', $definition->getAdminPermission());
    self::assertFalse($definition->getLinkTemplate('canonical'));
    self::assertFalse($definition->getLinkTemplate('add-form'));
    self::assertFalse($definition->getLinkTemplate('edit-form'));
  }

  /**
   * Valores negativos são rejeitados pelas constraints dos campos.
   */
  public function testOfferRejectsNegativePriceAndCapacity(): void {
    aculta_events_update_10014();

    $event = Event::create(['title' => 'Evento sintético', 'field_status' => 'rascunho']);
    $event->save();
    $offer = EventOffer::create([
      'title' => 'Oferta inválida',
      'field_evento' => $event->id(),
      'field_modalidade' => 'paga',
      'field_preco' => '-1.00',
      'field_capacidade' => -2,
    ]);

    $violations = $offer->validate();
    self::assertGreaterThanOrEqual(2, $violations->count());
  }

  /**
   * RSVP policy resolves only complete free contexts and fails closed.
   */
  public function testRsvpPolicyResolverValidatesCapacityContext(): void {
    aculta_events_update_10014();
    aculta_events_update_10015();

    $event = Event::create([
      'title' => 'RSVP policy event',
      'field_status' => 'publicado',
      'field_rsvp_ativo' => TRUE,
      'field_rsvp_escopo_capacidade' => 'data',
      'field_rsvp_capacidade' => 100,
      'field_rsvp_limite_pessoa' => 2,
      'field_rsvp_duplicidade' => 'impedir',
      'field_rsvp_cancelamento' => 'permitir',
      'field_rsvp_serie' => 'ocorrencia',
    ]);
    $event->save();
    $date = EventDate::create([
      'title' => 'RSVP occurrence',
      'field_evento' => $event->id(),
      'field_quando' => [
        'value' => '2026-12-01T18:00:00',
        'end_value' => '2026-12-01T20:00:00',
      ],
      'field_regra' => 'FREQ=DAILY;COUNT=3',
      'field_rsvp_capacidade' => 30,
    ]);
    $date->save();

    /** @var \Drupal\aculta_events\Rsvp\RsvpPolicyResolver $resolver */
    $resolver = $this->container->get('aculta_events.rsvp_policy_resolver');
    self::assertNull($resolver->resolve($event));
    $policy = $resolver->resolve($event, $date);
    self::assertNotNull($policy);
    self::assertSame('data', $policy->capacityScope);
    self::assertSame(30, $policy->capacity);
    self::assertSame(2, $policy->perPersonLimit);
    self::assertFalse($policy->allowDuplicates);
    self::assertSame('permitir', $policy->cancellationPolicy);
    self::assertSame((int) $date->id(), $policy->dateId);
    self::assertNull($policy->offerId);

    $event->set('field_rsvp_escopo_capacidade', 'evento');
    $event->save();
    self::assertNull($resolver->resolve($event));
    self::assertNotNull($resolver->resolve($event, $date));

    $otherEvent = Event::create(['title' => 'Other event']);
    $otherEvent->save();
    $wrongDate = EventDate::create([
      'title' => 'Wrong occurrence',
      'field_evento' => $otherEvent->id(),
      'field_quando' => ['value' => '2026-12-02T18:00:00'],
    ]);
    $wrongDate->save();
    self::assertNull($resolver->resolve($event, $wrongDate));

    $event->set('field_rsvp_escopo_capacidade', 'lote');
    $event->save();
    $inactiveOffer = EventOffer::create([
      'title' => 'Inactive free offer',
      'field_evento' => $event->id(),
      'field_modalidade' => 'gratuita',
      'field_capacidade' => 12,
      'field_ativa' => FALSE,
    ]);
    $inactiveOffer->save();
    self::assertNull($resolver->resolve($event, $date, $inactiveOffer));

    $paidOffer = EventOffer::create([
      'title' => 'Paid offer',
      'field_evento' => $event->id(),
      'field_modalidade' => 'paga',
      'field_preco' => '10.00',
      'field_ativa' => TRUE,
    ]);
    $paidOffer->save();
    self::assertNull($resolver->resolve($event, $date, $paidOffer));

    $freeOffer = EventOffer::create([
      'title' => 'Active free offer',
      'field_evento' => $event->id(),
      'field_modalidade' => 'gratuita',
      'field_capacidade' => 8,
      'field_ativa' => TRUE,
    ]);
    $freeOffer->save();
    $lotPolicy = $resolver->resolve($event, $date, $freeOffer);
    self::assertNotNull($lotPolicy);
    self::assertSame('lote', $lotPolicy->capacityScope);
    self::assertSame(8, $lotPolicy->capacity);
    self::assertSame((int) $freeOffer->id(), $lotPolicy->offerId);
  }

}
