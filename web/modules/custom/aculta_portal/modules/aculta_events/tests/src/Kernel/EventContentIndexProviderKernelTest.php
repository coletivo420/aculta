<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

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
    'system', 'user', 'key', 'address', 'geofield', 'options', 'link', 'text',
    'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar',
    'aculta_index', 'aculta_events',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

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
  }

  /**
   * Provedor de eventos projeta publicados e ignora rascunhos.
   */
  public function testEventIndexProvider(): void {
    Vocabulary::create(['vid' => 'aculta_event_type', 'name' => 'Tipo de evento'])->save();
    $tipo = Term::create(['vid' => 'aculta_event_type', 'name' => 'Oficina']);
    $tipo->save();

    $eventoPublicado = Event::create([
      'title' => 'Oficina de Argila',
      'field_status' => 'publicado',
      'field_tipo' => $tipo->id(),
    ]);
    $eventoPublicado->save();

    $eventoRascunho = Event::create([
      'title' => 'Ensaio Fechado',
      'field_status' => 'rascunho',
    ]);
    $eventoRascunho->save();

    $provider = $this->container->get('aculta_events.index_provider.event');
    $this->assertSame('event', $provider->getProviderId());
    $this->assertSame('eventos', $provider->getAccessPurpose($eventoPublicado));

    $this->assertTrue($provider->isIndexable($eventoPublicado));
    $this->assertFalse($provider->isIndexable($eventoRascunho));

    $proj = $provider->project($eventoPublicado);
    $this->assertNotNull($proj);
    $this->assertSame('aculta_event', $proj->entityTypeId);
    $this->assertSame((string) $eventoPublicado->id(), $proj->entityId);
    $this->assertSame('Oficina de Argila', $proj->label);
    $this->assertSame('eventos_aculta_org', $proj->domainId);
    $this->assertTrue($proj->published);
    $this->assertCount(1, $proj->relations);
    $this->assertSame('event_type', $proj->relations[0]->type);
    $this->assertSame((string) $tipo->id(), $proj->relations[0]->targetEntityId);

    $this->assertNull($provider->project($eventoRascunho));
  }

  /**
   * Provedor de locais projeta entidades com relações de tipo.
   */
  public function testLocationIndexProvider(): void {
    Vocabulary::create(['vid' => 'aculta_location_type', 'name' => 'Tipo de local'])->save();
    $tipo = Term::create(['vid' => 'aculta_location_type', 'name' => 'Galeria']);
    $tipo->save();

    $local = EventLocation::create([
      'title' => 'Galeria Central',
      'field_tipo' => $tipo->id(),
    ]);
    $local->save();

    $provider = $this->container->get('aculta_events.index_provider.location');
    $this->assertSame('event_location', $provider->getProviderId());
    $this->assertSame('eventos', $provider->getAccessPurpose($local));
    $this->assertTrue($provider->isIndexable($local));

    $proj = $provider->project($local);
    $this->assertNotNull($proj);
    $this->assertSame('aculta_event_location', $proj->entityTypeId);
    $this->assertSame((string) $local->id(), $proj->entityId);
    $this->assertSame('Galeria Central', $proj->label);
    $this->assertTrue($proj->published);
    $this->assertCount(1, $proj->relations);
    $this->assertSame('location_type', $proj->relations[0]->type);
  }

}
