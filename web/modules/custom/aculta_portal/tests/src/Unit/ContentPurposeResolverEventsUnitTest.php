<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\ContentPurposeResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tipos do módulo de eventos resolvem para o purpose "eventos" (MODELO-DE-DADOS.md).
 */
#[Group('aculta_portal')]
class ContentPurposeResolverEventsUnitTest extends UnitTestCase {

  /**
   * Rotas canônicas de evento, data e local.
   *
   * @return array<string, array{string}>
   */
  public static function rotasDeEvento(): array {
    return [
      'evento' => ['entity.aculta_event.canonical'],
      'data' => ['entity.aculta_event_date.canonical'],
      'local' => ['entity.aculta_event_location.canonical'],
    ];
  }

  /**
   * Cada rota de evento cai no purpose eventos, sem consultar entidades.
   */
  #[DataProvider('rotasDeEvento')]
  public function testRotaDeEventoResolveParaEventos(string $rota): void {
    $resolver = new ContentPurposeResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      (new \ReflectionClass(DomainPurposeManager::class))->newInstanceWithoutConstructor(),
    );
    $request = Request::create('/');
    $request->attributes->set('_route', $rota);

    $this->assertSame('eventos', $resolver->canonicalContentPurpose($request));
  }

}
