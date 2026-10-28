<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Embed\EmbedSourceAllowlist;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Allowlist do embed: caminho interno ou https de host da lista (S3).
 */
#[Group('aculta_calendar')]
class EmbedSourceAllowlistTest extends UnitTestCase {

  /**
   * Allowlist com um único host configurado.
   */
  private function allowlist(): EmbedSourceAllowlist {
    return new EmbedSourceAllowlist(['Eventos.exemplo.invalid']);
  }

  /**
   * Casos aceitos: caminho interno e https do host da lista.
   *
   * @return array<string, array{string}>
   */
  public static function aceitos(): array {
    return [
      'caminho interno' => ['/calendario'],
      'caminho com consulta' => ['/calendario?mes=10'],
      'https do host da lista' => ['https://eventos.exemplo.invalid/calendario'],
      'host em maiúsculas' => ['https://EVENTOS.exemplo.invalid/calendario'],
      'https com porta padrão' => ['https://eventos.exemplo.invalid:443/calendario'],
    ];
  }

  /**
   * Casos recusados, com o defeito que cada um representa.
   *
   * @return array<string, array{string}>
   */
  public static function recusados(): array {
    return [
      'javascript' => ['javascript:alert(1)'],
      'data' => ['data:text/html,<script>alert(1)</script>'],
      'http' => ['http://eventos.exemplo.invalid/calendario'],
      'host fora da lista' => ['https://evil.com/calendario'],
      'sufixo de host' => ['https://eventos.exemplo.invalid.evil.com/'],
      'protocolo relativo' => ['//evil.com/calendario'],
      'barra invertida após barra' => ['/\\evil.com'],
      'credencial na URL' => ['https://eventos.exemplo.invalid@evil.com/'],
      'porta fora do padrão' => ['https://eventos.exemplo.invalid:8443/'],
      'caminho sem barra inicial' => ['calendario'],
      'vazio' => [''],
      'espaço no meio' => ['/calendario x'],
      'quebra de linha' => ["/calendario\r\nLocation: x"],
    ];
  }

  /**
   * Casos aceitos passam.
   */
  #[DataProvider('aceitos')]
  public function testAceitaCaminhoInternoEHostDaLista(string $uri): void {
    $this->assertTrue($this->allowlist()->isAllowed($uri));
  }

  /**
   * Casos recusados não passam.
   */
  #[DataProvider('recusados')]
  public function testRecusaDestinoForaDaRegra(string $uri): void {
    $this->assertFalse($this->allowlist()->isAllowed($uri));
  }

}
