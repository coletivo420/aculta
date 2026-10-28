<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Controller\MapController;
use Drupal\aculta_events\Entity\EventLocation;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Mapa sem chave: sem carregamento do Google, lista textual e aviso de privacidade.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class MapControllerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'address', 'geofield', 'key', 'options', 'text', 'filter', 'taxonomy', 'datetime', 'datetime_range', 'aculta_calendar', 'aculta_events'];

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
  }

  /**
   * Sem chave provisionada: a página não carrega o mapa, mas mantém a lista e o aviso.
   */
  public function testSemChaveMostraListaESemMapa(): void {
    EventLocation::create([
      'title' => 'Casa de cultura',
      'field_precisao' => 'exato',
      'field_endereco' => ['country_code' => 'BR', 'locality' => 'Goiânia', 'administrative_area' => 'GO', 'address_line1' => 'Rua 10, 123', 'postal_code' => '74000000'],
    ])->save();

    $pagina = MapController::create($this->container)->page();
    $this->assertArrayNotHasKey('mapa', $pagina);
    $this->assertArrayNotHasKey('#attached', $pagina);
    $this->assertStringContainsString('Casa de cultura', (string) \Drupal::service('renderer')->renderInIsolation($pagina['lista']));
    $this->assertStringContainsString('seu navegador envia', (string) \Drupal::service('renderer')->renderInIsolation($pagina['aviso']));
  }

}
