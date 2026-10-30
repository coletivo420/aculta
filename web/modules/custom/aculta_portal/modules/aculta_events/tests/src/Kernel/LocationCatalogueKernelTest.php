<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_events\Kernel;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\aculta_events\Catalogue\LocationCatalogue;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests location precision privacy and type filtering.
 */
#[Group('aculta_events')]
#[RunTestsInSeparateProcesses]
final class LocationCatalogueKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'key', 'options', 'link', 'text', 'filter', 'taxonomy',
    'datetime', 'datetime_range', 'address', 'geofield', 'aculta_calendar',
    'aculta_maps', 'aculta_events',
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
    $this->installEntitySchema('aculta_event_location');
    $this->installEntitySchema('aculta_event_override');
  }

  /**
   * Local com o endereço e a precisão informados.
   */
  private function local(string $nome, string $precisao, ?Term $tipo = NULL): EventLocation {
    $valores = [
      'title' => $nome,
      'field_precisao' => $precisao,
      'field_coordenadas' => [
        'lat' => -16.68,
        'lon' => -49.26,
        'value' => 'POINT (-49.26 -16.68)',
        'geo_type' => 'Point',
      ],
      'field_endereco' => [
        'country_code' => 'BR',
        'administrative_area' => 'GO',
        'locality' => 'Goiânia',
        'dependent_locality' => 'Setor Central',
        'postal_code' => '74000-000',
        'address_line1' => 'Rua 10, 123',
      ],
    ];
    if ($tipo !== NULL) {
      $valores['field_tipo'] = $tipo->id();
    }
    $local = EventLocation::create($valores);
    $local->save();
    return $local;
  }

  /**
   * Local exato traz rua e CEP; aproximado traz só bairro e município.
   */
  public function testPrecisaoControlaEndereco(): void {
    $exato = $this->local('Casa de cultura', 'exato');
    $aproximado = $this->local('Praça pública', 'aproximado');
    $servico = $this->container->get('aculta_events.locations');

    $this->assertSame('Rua 10, 123, Setor Central — Goiânia — GO, CEP 74000-000', $servico->enderecoPublico($exato));
    $this->assertSame('Setor Central — Goiânia — GO', $servico->enderecoPublico($aproximado));
    $this->assertStringNotContainsString('Rua 10', $servico->enderecoPublico($aproximado));
  }

  /**
   * Filtro por tipo devolve só os locais desse tipo, em ordem alfabética.
   */
  public function testFiltraPorTipo(): void {
    Vocabulary::create(['vid' => 'aculta_location_type', 'name' => 'Tipo de local'])->save();
    $teatro = Term::create(['vid' => 'aculta_location_type', 'name' => 'Teatro']);
    $teatro->save();
    $museu = Term::create(['vid' => 'aculta_location_type', 'name' => 'Museu']);
    $museu->save();
    $this->local('Teatro Municipal', 'exato', $teatro);
    $this->local('Museu da Cidade', 'exato', $museu);
    $this->local('Sala sem tipo', 'exato');

    $servico = $this->container->get('aculta_events.locations');
    $nomes = array_map(static fn (array $i): string => (string) $i['local']->label(), $servico->busca((int) $teatro->id()));
    $this->assertSame(['Teatro Municipal'], $nomes);
    $todos = array_map(static fn (array $i): string => (string) $i['local']->label(), $servico->busca(NULL));
    $this->assertSame(['Museu da Cidade', 'Sala sem tipo', 'Teatro Municipal'], $todos);
  }

  /**
   * Catálogo aplica view access antes de calcular total e paginação, inclusive
   * quando não há conta de requisição disponível.
   */
  public function testBuscaPaginadaFiltraAcessoAntesDaPaginacao(): void {
    $publico = $this->local('Local público', 'exato');
    $privado = $this->local('Local privado', 'exato');
    $privado->set('field_status', 'privado');
    $privado->save();

    $anonimo = new AnonymousUserSession();
    $catalogue = new LocationCatalogue($this->container->get('entity_type.manager'));
    $resultado = $catalogue->buscaPaginada(NULL, NULL, 0, 10, $anonimo);

    $this->assertSame(1, $resultado['total']);
    $this->assertCount(1, $resultado['items']);
    $this->assertSame((string) $publico->id(), (string) $resultado['items'][0]['local']->id());

    // Sem conta explícita, a ausência de contexto não pode abrir locais privados.
    $semConta = new LocationCatalogue($this->container->get('entity_type.manager'));
    $resultadoSemConta = $semConta->buscaPaginada(NULL, NULL, 0, 10);
    $this->assertSame(1, $resultadoSemConta['total']);
    $this->assertSame((string) $publico->id(), (string) $resultadoSemConta['items'][0]['local']->id());
  }

  /**
   * Controle de acesso: view é pública; mutações exigem permissão.
   */
  public function testLocationAccessAndMutations(): void {
    $local = $this->local('Centro Cultural Teste', 'exato');

    // Cria o UID 1 (superadmin) para que os usuários seguintes não
    // recebam privilégios totais do core.
    $this->createUser();

    $anonimo = new AnonymousUserSession();
    $comum = $this->createUser();
    $admin = $this->createUser(['administer aculta events']);

    $accessControl = $this->container->get('entity_type.manager')->getAccessControlHandler('aculta_event_location');

    // Leitura permitida para todos.
    $this->assertTrue($local->access('view', $anonimo));
    $this->assertTrue($local->access('view', $comum));
    $this->assertTrue($local->access('view', $admin));

    // Mutações proibidas para anônimo e usuário comum.
    $this->assertFalse($local->access('update', $anonimo));
    $this->assertFalse($local->access('update', $comum));
    $this->assertFalse($local->access('delete', $anonimo));
    $this->assertFalse($local->access('delete', $comum));
    $this->assertFalse($accessControl->createAccess(NULL, $anonimo));
    $this->assertFalse($accessControl->createAccess(NULL, $comum));

    // Mutações permitidas para administrador de eventos.
    $this->assertTrue($local->access('update', $admin));
    $this->assertTrue($local->access('delete', $admin));
    $this->assertTrue($accessControl->createAccess(NULL, $admin));
  }

}
