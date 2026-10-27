<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_pagamentos_mercadopago\Unit\MercadoPago;

use Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Payloads de planos e assinaturas: validação e formato enviado ao Mercado Pago.
 */
#[Group('aculta_pagamentos_mercadopago')]
final class RecurrenceRequestBuilderTest extends UnitTestCase {

  /**
   * Construtor de payloads sob teste.
   *
   * @var \Drupal\aculta_pagamentos_mercadopago\MercadoPago\RecurrenceRequestBuilder
   */
  private RecurrenceRequestBuilder $montagem;

  /**
   * Sets up the test fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->montagem = new RecurrenceRequestBuilder();
  }

  /**
   * Performs the plano operation.
   */
  private function plano(array $sobrescrever = []): array {
    return $sobrescrever + [
      'motivo' => 'Apoio mensal à ACULTA',
      'valor' => '30.00',
      'moeda' => 'BRL',
      'frequencia' => 1,
      'back_url' => 'https://retorno.example.invalid/apoie/obrigado',
    ];
  }

  /**
   * Tests the plano mensal em brl behavior.
   */
  public function testPlanoMensalEmBrl(): void {
    $p = $this->montagem->plano($this->plano());
    $this->assertSame('Apoio mensal à ACULTA', $p['reason']);
    $this->assertSame([
      'frequency' => 1,
      'frequency_type' => 'months',
      'transaction_amount' => 30.0,
      'currency_id' => 'BRL',
    ], $p['auto_recurring']);
    $this->assertSame('https://retorno.example.invalid/apoie/obrigado', $p['back_url']);
  }

  /**
   * Performs the valores invalidos operation.
   */
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

  /**
   * Tests the valor invalido eh recusado behavior.
   */
  #[DataProvider('valoresInvalidos')]
  public function testValorInvalidoEhRecusado(string $valor): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['valor' => $valor]));
  }

  /**
   * Performs the frequencias invalidas operation.
   */
  public static function frequenciasInvalidas(): array {
    return [[0], [13], [-1]];
  }

  /**
   * Tests the frequencia fora do intervalo eh recusada behavior.
   */
  #[DataProvider('frequenciasInvalidas')]
  public function testFrequenciaForaDoIntervaloEhRecusada(int $frequencia): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['frequencia' => $frequencia]));
  }

  /**
   * Tests the moeda diferente de brl eh recusada behavior.
   */
  public function testMoedaDiferenteDeBrlEhRecusada(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['moeda' => 'USD']));
  }

  /**
   * Performs the urls inseguras operation.
   */
  public static function urlsInseguras(): array {
    return [
      'http' => ['http://retorno.example.invalid/x'],
      'sem host' => ['https:///x'],
      'javascript' => ['javascript:alert(1)'],
      'vazia' => [''],
    ];
  }

  /**
   * Tests the back url so aceita https com host behavior.
   */
  #[DataProvider('urlsInseguras')]
  public function testBackUrlSoAceitaHttpsComHost(string $url): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->plano($this->plano(['back_url' => $url]));
  }

  /**
   * Tests the assinatura com plano herda recorrencia do plano behavior.
   */
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

  /**
   * Tests the assinatura sem plano exige recorrencia behavior.
   */
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

  /**
   * Tests the email invalido eh recusado behavior.
   */
  public function testEmailInvalidoEhRecusado(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->assinatura([
      'email' => 'nao-e-email',
      'back_url' => 'https://retorno.example.invalid/x',
      'referencia' => 'r',
      'plano_id' => 'p',
    ]);
  }

  /**
   * Tests the referencia obrigatoria behavior.
   */
  public function testReferenciaObrigatoria(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->assinatura([
      'email' => 'pagador@example.com',
      'back_url' => 'https://retorno.example.invalid/x',
      'referencia' => '  ',
      'plano_id' => 'p',
    ]);
  }

  /**
   * Performs the status permitidos operation.
   */
  public static function statusPermitidos(): array {
    return [['paused'], ['authorized'], ['cancelled']];
  }

  /**
   * Tests the status alteravel pela aplicacao behavior.
   */
  #[DataProvider('statusPermitidos')]
  public function testStatusAlteravelPelaAplicacao(string $status): void {
    $this->assertSame(['status' => $status], $this->montagem->statusAssinatura($status));
  }

  /**
   * Tests the status pending nao pode ser imposto behavior.
   */
  public function testStatusPendingNaoPodeSerImposto(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->montagem->statusAssinatura('pending');
  }

}
