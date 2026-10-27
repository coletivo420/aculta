<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

/**
 * Resultado do simulador de frete, com estado explícito para a interface.
 *
 * Estados:
 * - Available: há opções de entrega para o CEP.
 * - Unavailable: a cotação não pode ser apresentada; o motivo é interno e nunca vai ao visitante como texto técnico.
 * - Empty: a API respondeu, mas não há serviço para o destino e o pacote.
 */
final class ShippingSimulation {

  public const STATE_AVAILABLE = 'available';

  public const STATE_UNAVAILABLE = 'unavailable';

  public const STATE_EMPTY = 'empty';

  /**
   * Motivos de indisponibilidade.
   */
  public const REASON_NO_ORIGIN = 'no_origin';

  public const REASON_NO_CREDENTIALS = 'no_credentials';

  public const REASON_NO_PACKAGE = 'no_package';

  public const REASON_INVALID_POSTAL_CODE = 'invalid_postal_code';

  public const REASON_FAILURE = 'failure';

  public const REASON_RATE_LIMITED = 'rate_limited';

  public const REASON_EMPTY_CART = 'empty_cart';

  /**
   * Cria o resultado.
   *
   * @param string $state
   *   Uma das constantes STATE_*.
   * @param string|null $reason
   *   Uma das constantes REASON_*, quando o estado é indisponível.
   * @param list<\Drupal\aculta_commerce_melhorenvio\Quote\QuoteOption> $options
   *   Opções disponíveis, quando o estado é disponível.
   */
  public function __construct(
    public readonly string $state,
    public readonly ?string $reason = NULL,
    public readonly array $options = [],
  ) {}

  /**
   * Resultado indisponível com o motivo informado.
   */
  public static function unavailable(string $reason): self {
    return new self(self::STATE_UNAVAILABLE, $reason);
  }

  /**
   * Forma serializável para a resposta JSON do endpoint. Preço formatado em reais, sem float.
   *
   * A mensagem ao visitante é definida aqui, em um único lugar; o componente só a apresenta.
   *
   * @return array{state: string, reason: ?string, message: string, options: list<array{name: string, company: string, price: string, days: ?int}>}
   */
  public function toArray(): array {
    $options = [];
    foreach ($this->options as $option) {
      $options[] = [
        'name' => $option->label(),
        'company' => $option->company,
        'price' => self::brl($option->amount),
        'days' => $option->deliveryDays,
      ];
    }
    return [
      'state' => $this->state,
      'reason' => $this->reason,
      'message' => $this->message(),
      'options' => $options,
    ];
  }

  /**
   * Texto curto para o visitante. Estados disponíveis não têm mensagem. Motivos internos (origem, credencial, pacote)
   * viram a mesma frase genérica: o visitante não precisa saber qual configuração falta.
   */
  public function message(): string {
    if ($this->state === self::STATE_AVAILABLE) {
      return '';
    }
    if ($this->state === self::STATE_EMPTY) {
      return 'Nenhuma opção de entrega para este CEP.';
    }
    return match ($this->reason) {
      self::REASON_INVALID_POSTAL_CODE => 'CEP inválido.',
      self::REASON_FAILURE => 'Não foi possível calcular o frete agora. Tente novamente.',
      self::REASON_RATE_LIMITED => 'Muitas consultas em pouco tempo. Aguarde um minuto.',
      self::REASON_EMPTY_CART => 'Seu carrinho está vazio.',
      default => 'Cálculo de frete indisponível no momento.',
    };
  }

  /**
   * Valor com duas casas, como "37.79", em "R$ 37,79". Trabalha sobre o texto, sem conversão numérica.
   */
  public static function brl(string $amount): string {
    [$integer, $fraction] = explode('.', $amount) + [1 => '00'];
    $grouped = strrev(implode('.', str_split(strrev($integer), 3)));
    return 'R$ ' . $grouped . ',' . str_pad($fraction, 2, '0');
  }

}
