<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\ContentSiteKeyResolver;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Canonical event routes resolve to the eventos site key.
 */
#[Group('aculta_portal')]
class ContentSiteKeyResolverEventsUnitTest extends UnitTestCase {

  /**
   * Rotas canônicas de evento, data e local.
   *
   * @return array<string, array{string}>
   *   Rotas de evento indexadas por tipo.
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
    $resolver = new ContentSiteKeyResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainSiteManagerInterface::class),
    );
    $request = Request::create('/');
    $request->attributes->set('_route', $rota);

    $this->assertSame('eventos', $resolver->canonicalContentSiteKey($request));
  }

}
