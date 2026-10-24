<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\FrontPathPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guarda do hub do Studio na raiz do ACCOUNT (decisão pura, sem roteamento).
 */
#[Group('aculta_portal')]
#[CoversClass(FrontPathPolicy::class)]
final class DomainPurposeRootGuardTest extends UnitTestCase {

  /**
   * Casos: purpose, caminho original, rota, autenticado, ação esperada.
   */
  public static function hubActionProvider(): array {
    return [
      'anônimo na raiz da Conta vai ao login' => ['account', '/', 'aculta_studio.hub', FALSE, FrontPathPolicy::ACAO_LOGIN],
      'logado na raiz da Conta é servido' => ['account', '/', 'aculta_studio.hub', TRUE, NULL],
      '/studio direto responde 404, mesmo logado' => ['account', '/studio', 'aculta_studio.hub', TRUE, FrontPathPolicy::ACAO_NAO_ENCONTRADO],
      '/studio direto responde 404, anônimo' => ['account', '/studio', 'aculta_studio.hub', FALSE, FrontPathPolicy::ACAO_NAO_ENCONTRADO],
      'outro purpose não sofre a guarda' => ['main', '/', 'aculta_studio.hub', FALSE, NULL],
      'outra rota na raiz da Conta não sofre a guarda' => ['account', '/', 'aculta_portal.dashboard', FALSE, NULL],
      'sem purpose não sofre a guarda' => [NULL, '/', 'aculta_studio.hub', FALSE, NULL],
    ];
  }

  #[DataProvider('hubActionProvider')]
  public function testHubAction(?string $purpose, string $path, string $route, bool $autenticado, ?string $expected): void {
    self::assertSame($expected, FrontPathPolicy::acaoDoHubNaConta($purpose, $path, $route, $autenticado));
  }

}
