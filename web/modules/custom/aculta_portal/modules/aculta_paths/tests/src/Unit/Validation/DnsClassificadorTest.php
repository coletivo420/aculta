<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_paths\Unit\Validation;

use Drupal\aculta_paths\Service\DnsClassificador;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Classificação do DNS de subdomínio em relação ao principal.
 */
#[Group('aculta_paths')]
final class DnsClassificadorTest extends UnitTestCase {

  private const PRINCIPAL = 'exemplo.org';

  public function testPrincipalSemResolucaoEhAviso(): void {
    $r = DnsClassificador::classificar(self::PRINCIPAL, ['ips' => [], 'cnames' => []], ['ips' => ['1.1.1.1'], 'cnames' => []]);
    $this->assertSame(['classe' => 'aviso', 'codigo' => 'principal_sem_resolucao'], $r);
  }

  public function testSubdominioSemRegistroEhErro(): void {
    $r = DnsClassificador::classificar(self::PRINCIPAL, ['ips' => ['1.1.1.1'], 'cnames' => []], ['ips' => [], 'cnames' => []]);
    $this->assertSame('nao_resolve', $r['codigo']);
    $this->assertSame('erro', $r['classe']);
  }

  public function testCnameParaPrincipalEhOk(): void {
    $r = DnsClassificador::classificar(self::PRINCIPAL, ['ips' => ['1.1.1.1'], 'cnames' => []], ['ips' => [], 'cnames' => ['Exemplo.org']]);
    $this->assertSame('cname_principal', $r['codigo']);
    $this->assertSame('ok', $r['classe']);
  }

  public function testMesmoIpDoPrincipalEhOk(): void {
    $r = DnsClassificador::classificar(self::PRINCIPAL, ['ips' => ['1.1.1.1', '2.2.2.2'], 'cnames' => []], ['ips' => ['2.2.2.2'], 'cnames' => []]);
    $this->assertSame('mesmo_ip', $r['codigo']);
  }

  public function testOutroDestinoEhErroComDestino(): void {
    $r = DnsClassificador::classificar(self::PRINCIPAL, ['ips' => ['1.1.1.1'], 'cnames' => []], ['ips' => ['9.9.9.9'], 'cnames' => []]);
    $this->assertSame('outro_destino', $r['codigo']);
    $this->assertSame('9.9.9.9', $r['destino']);
  }

}
