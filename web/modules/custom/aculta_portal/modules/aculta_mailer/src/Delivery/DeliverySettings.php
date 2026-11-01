<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Delivery;

/**
 * Configuração de entrega, alertas e resumo (`aculta_mailer.delivery`), e leitura das listas de destinatários.
 */
final class DeliverySettings {

  public const CONFIG = 'aculta_mailer.delivery';

  /**
   * Transportes por API, que não usam o endereço de retorno.
   *
   * Neles o envelope vira o remetente visível, então o endereço de retorno não se aplica. Ao criar outro transporte
   * por API, incluir aqui.
   */
  public const API_TRANSPORTS = ['brevo'];

  /**
   * Endereços válidos de uma lista, sem repetição.
   *
   * Separadores: vírgula, ponto e vírgula, espaço ou quebra de linha.
   *
   * @return string[]
   *   Endereços em minúsculas.
   */
  public static function recipients(string $text): array {
    $valid = [];
    foreach (self::tokens($text) as $address) {
      if (filter_var($address, FILTER_VALIDATE_EMAIL) !== FALSE) {
        $valid[$address] = $address;
      }
    }
    return array_values($valid);
  }

  /**
   * Itens da lista que não são endereços válidos.
   *
   * @return string[]
   *   Itens inválidos, como digitados.
   */
  public static function invalid(string $text): array {
    return array_values(array_filter(
      self::tokens($text),
      static fn(string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) === FALSE,
    ));
  }

  /**
   * Itens da lista, em minúsculas.
   *
   * @return string[]
   *   Itens separados pelos delimitadores.
   */
  private static function tokens(string $text): array {
    return preg_split('/[\s,;]+/', strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
  }

}
