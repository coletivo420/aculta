<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\MercadoPago;

use Drupal\aculta_portal\MercadoPago\RecurrenceRequestBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Payloads de planos e assinaturas: validação e formato enviado ao Mercado Pago.
 */
#[Group('aculta_portal')]
final class RecurrenceRequestBuilderTest extends UnitTestCase {

  private RecurrenceRequestBuilder $montagem;

  protected function setUp(): void {
    parent::setUp();
    $this->montagem = new RecurrenceRequestBuilder();
  }

  private function plano(array $sobrescrever = []): array {
    return $sobrescrever + [
      'motivo' => 'Apoio mensal à ACULTA',
      'valor' => '30.00',
      'moeda' => 'BRL',
      'frequencia' => 1,
      'back_url' => 'https://retorno.example.invalid/apoie/obrigado',
    ];
  }

  public function testPlanoMensalEmBrl(): void {
    $p = $this->montagem->plano($this->plano());
    $this->assertSame('Apoio mensal à ACULTA', $p['reason']);
    $this->assertSame(['frequency' => 1, 'frequency_type' => 'months', 'transaction_amount' => 30.0, 'currency_id' => 'BRL'], $p['auto_recurring']);
    $this->assertSame('https://retorno.example.invalid/apoie/obrigado', $p['back_url']);
  }

  public static function valoresInvalidos(): array {
    return [
      'zero' => ['0.00'],
      'negativo' => ['-5.00'],
      'três casas' => ['10.005'],
      'com vírgula' => ['10,00'],
      'texto' => ['dez'],
      'vazio' => [''],
    ];
  }

  #[DataProvider('valoresInvalidos')]
  public function testValorInvalidoEhRecusado(string $valor): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['valor' => $valor]));
  }

  public static function frequenciasInvalidas(): array {
    return [[0], [13], [-1]];
  }

  #[DataProvider('frequenciasInvalidas')]
  public function testFrequenciaForaDoIntervaloEhRecusada(int $frequencia): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['frequencia' => $frequencia]));
  }

  public function testMoedaDiferenteDeBrlEhRecusada(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['moeda' => 'USD']));
  }

  public static function urlsInseguras(): array {
    return [
      'http' => ['http://retorno.example.invalid/x'],
      'sem host' => ['https:///x'],
      'javascript' => ['javascript:alert(1)'],
      'vazia' => [''],
    ];
  }

  #[DataProvider('urlsInseguras')]
  public function testBackUrlSoAceitaHttpsComHost(string $url): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['back_url' => $url]));
  }

  public function testAssinaturaComPlanoHerdaRecorrenciaDoPlano(): void {
    $a = $this->montagem->assinatura([
      'email' => 'pagador@example.com',
      'back_url' => 'https://retorno.example.invalid/apoie/obrigado',
      'referencia' => 'doacao-123',
      'plano_id' => '2c938084726fca480172750000000000',
    ]);
    $this->assertSame('2c938084726fca480172750000000000', $a['preapproval_plan_id']);
    $this->assertSame('pending', $a['status']);
    $this->assertSame('doacao-123', $a['external_reference']);
    $this->assertArrayNotHasKey('auto_recurring', $a);
  }

  public function testAssinaturaSemPlanoExigeRecorrencia(): void {
    $a = $this->montagem->assinatura([
      'email' => 'pagador@example.com',
      'back_url' => 'https://retorno.example.invalid/apoie/obrigado',
      'referencia' => 'doacao-124',
      'motivo' => 'Apoio mensal',
      'valor' => '50.00',
      'moeda' => 'BRL',
      'frequencia' => 3,
    ]);
    $this->assertSame(3, $a['auto_recurring']['frequency']);
    $this->assertSame('Apoio mensal', $a['reason']);
    $this->assertArrayNotHasKey('preapproval_plan_id', $a);
  }

  public function testEmailInvalidoEhRecusado(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->assinatura([
      'email' => 'nao-e-email',
      'back_url' => 'https://retorno.example.invalid/x',
      'referencia' => 'r',
      'plano_id' => 'p',
    ]);
  }

  public function testReferenciaObrigatoria(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->assinatura([
      'email' => 'pagador@example.com',
      'back_url' => 'https://retorno.example.invalid/x',
      'referencia' => '  ',
      'plano_id' => 'p',
    ]);
  }

  public static function statusPermitidos(): array {
    return [['paused'], ['authorized'], ['cancelled']];
  }

  #[DataProvider('statusPermitidos')]
  public function testStatusAlteravelPelaAplicacao(string $status): void {
    $this->assertSame(['status' => $status], $this->montagem->statusAssinatura($status));
  }

  public function testStatusPendingNaoPodeSerImposto(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->statusAssinatura('pending');
  }

}
