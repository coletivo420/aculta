<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\MercadoPago;

/**
 * Monta e valida os payloads de planos e assinaturas (preapproval) do Mercado Pago.
 *
 * Função pura, sem SDK nem banco, testável em unidade. Regras do Portal:
 * - valores em decimal com até duas casas, maiores que zero;
 * - moeda BRL (o site opera só em real);
 * - frequência mensal de 1 a 12 meses;
 * - back_url sempre https: o endereço vem do mapeamento de endereços por ambiente, nunca do código;
 * - e-mail do pagador só é usado no payload; nunca é registrado em log.
 */
final class RecurrenceRequestBuilder {

  public const MOEDAS = ['BRL'];

  public const FREQUENCIA_MAXIMA_MESES = 12;

  /**
   * Status que a aplicação pode impor a uma assinatura existente.
   *
   * Assinaturas nascem em pending e são autorizadas pelo pagador no Mercado Pago; a aplicação só pausa,
   * reativa (authorized) ou cancela.
   */
  public const STATUS_ALTERAVEIS = ['paused', 'authorized', 'cancelled'];

  /**
   * Payload de criação de plano de assinatura.
   *
   * @param array{motivo: string, valor: string, moeda: string, frequencia: int, back_url: string} $dados
   *
   * @return array<string, mixed>
   */
  public function plano(array $dados): array {
    $this->motivo($dados['motivo'] ?? '');
    return [
      'reason' => $dados['motivo'],
      'auto_recurring' => $this->recorrencia($dados),
      'back_url' => $this->url($dados['back_url'] ?? ''),
    ];
  }

  /**
   * Payload de criação de assinatura.
   *
   * Com $planoId, a assinatura herda valor e frequência do plano. Sem plano, os campos de recorrência são
   * obrigatórios e a assinatura é independente.
   *
   * @param array{email: string, back_url: string, referencia: string, plano_id?: ?string, motivo?: string, valor?: string, moeda?: string, frequencia?: int} $dados
   *
   * @return array<string, mixed>
   */
  public function assinatura(array $dados): array {
    $email = (string) ($dados['email'] ?? '');
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new \InvalidArgumentException('E-mail do pagador inválido.');
    }
    $referencia = trim((string) ($dados['referencia'] ?? ''));
    if ($referencia === '') {
      throw new \InvalidArgumentException('Referência externa da assinatura é obrigatória.');
    }

    $payload = [
      'payer_email' => $email,
      'back_url' => $this->url($dados['back_url'] ?? ''),
      'external_reference' => $referencia,
      'status' => 'pending',
    ];

    $planoId = trim((string) ($dados['plano_id'] ?? ''));
    if ($planoId !== '') {
      $payload['preapproval_plan_id'] = $planoId;
      return $payload;
    }

    $this->motivo($dados['motivo'] ?? '');
    $payload['reason'] = $dados['motivo'];
    $payload['auto_recurring'] = $this->recorrencia($dados);
    return $payload;
  }

  /**
   * Payload de alteração de status de uma assinatura existente.
   *
   * @return array{status: string}
   */
  public function statusAssinatura(string $status): array {
    if (!in_array($status, self::STATUS_ALTERAVEIS, TRUE)) {
      throw new \InvalidArgumentException("Status de assinatura não permitido pela aplicação: $status.");
    }
    return ['status' => $status];
  }

  /**
   * Bloco auto_recurring: frequência mensal, valor decimal e moeda.
   *
   * @return array{frequency: int, frequency_type: string, transaction_amount: float, currency_id: string}
   */
  private function recorrencia(array $dados): array {
    $frequencia = (int) ($dados['frequencia'] ?? 0);
    if ($frequencia < 1 || $frequencia > self::FREQUENCIA_MAXIMA_MESES) {
      throw new \InvalidArgumentException('Frequência deve ser de 1 a 12 meses.');
    }
    $moeda = (string) ($dados['moeda'] ?? '');
    if (!in_array($moeda, self::MOEDAS, TRUE)) {
      throw new \InvalidArgumentException('Moeda não suportada para recorrência.');
    }
    $valor = (string) ($dados['valor'] ?? '');
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $valor) || bccomp($valor, '0', 2) !== 1) {
      throw new \InvalidArgumentException('Valor da recorrência deve ser decimal, maior que zero, com até duas casas.');
    }
    return [
      'frequency' => $frequencia,
      'frequency_type' => 'months',
      'transaction_amount' => (float) $valor,
      'currency_id' => $moeda,
    ];
  }

  private function motivo(string $motivo): void {
    if (trim($motivo) === '') {
      throw new \InvalidArgumentException('Motivo da assinatura é obrigatório.');
    }
  }

  /**
   * URL de retorno: só https, com host.
   */
  private function url(string $url): string {
    $partes = parse_url($url);
    if (!is_array($partes) || ($partes['scheme'] ?? '') !== 'https' || empty($partes['host'])) {
      throw new \InvalidArgumentException('back_url deve ser uma URL https com host.');
    }
    return $url;
  }

}
