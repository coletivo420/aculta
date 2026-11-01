<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\FrontPathPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Guarda da entrada da Conta na raiz do ACCOUNT (decisão pura, sem roteamento).
 */
#[Group('aculta_portal')]
#[CoversClass(FrontPathPolicy::class)]
final class DomainPurposeRootGuardTest extends UnitTestCase {

  /**
   * Casos: purpose, caminho original, rota, autenticado, ação esperada.
   */
  public static function accountActionProvider(): array {
    return [
      'anônimo na raiz da Conta vai ao login' => ['account', '/', 'aculta_studio.account', FALSE, FrontPathPolicy::ACAO_LOGIN],
      'logado na raiz da Conta é servido' => ['account', '/', 'aculta_studio.account', TRUE, NULL],
      'rota Conta direta não sofre a guarda da raiz' => ['account', '/conta', 'aculta_studio.account', TRUE, NULL],
      'outra rota na raiz da Conta não sofre a guarda' => ['account', '/', 'aculta_portal.dashboard', FALSE, NULL],
      'outro purpose não sofre a guarda' => ['main', '/', 'aculta_studio.account', FALSE, NULL],
      'sem purpose não sofre a guarda' => [NULL, '/', 'aculta_studio.account', FALSE, NULL],
    ];
  }

  #[DataProvider('accountActionProvider')]
  public function testAccountAction(?string $purpose, string $path, string $route, bool $autenticado, ?string $expected): void {
    self::assertSame($expected, FrontPathPolicy::acaoDaContaNaRaiz($purpose, $path, $route, $autenticado));
  }

}
