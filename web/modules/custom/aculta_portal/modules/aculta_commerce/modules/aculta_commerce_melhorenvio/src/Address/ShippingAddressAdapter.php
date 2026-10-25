<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Address;

use Drupal\aculta_portal_cep\Lookup\CepLookupInterface;

/**
 * Adaptador de endereço de envio para o Melhor Envio.
 *
 * Recebe os campos do Drupal Address do perfil de envio do pedido e devolve o CEP de destino validado, além de uma
 * sugestão da consulta compartilhada (aculta_portal_cep) quando faltam campos. Regras:
 * - não grava nem altera o endereço recebido; o perfil continua sendo do Drupal Commerce;
 * - só consulta a base de CEP quando faltam campos (endereço completo não gera consulta);
 * - nunca sobrescreve campo já preenchido; número e complemento não entram na sugestão;
 * - não inventa endereço: CEP não encontrado resulta em STATUS_NOT_FOUND;
 * - falha da consulta não derruba o fluxo: resulta em STATUS_UNAVAILABLE;
 * - disponibilidade de frete não é decidida aqui: quem decide é a cotação real do Melhor Envio.
 */
final class ShippingAddressAdapter {

  public const STATUS_COMPLETE = 'complete';
  public const STATUS_SUGGESTED = 'suggested';
  public const STATUS_NOT_FOUND = 'not_found';
  public const STATUS_INVALID = 'invalid_postal_code';
  public const STATUS_UNAVAILABLE = 'unavailable';

  /** Campos que o CEP pode preencher. Número e complemento ficam de fora de propósito. */
  public const CEP_FIELDS = ['address_line1', 'dependent_locality', 'locality', 'administrative_area'];

  public function __construct(private readonly CepLookupInterface $lookup) {}

  /**
   * Resolve o CEP de destino do endereço.
   *
   * @param array<string, string> $address
   *   Campos do Drupal Address do perfil de envio. Não é alterado.
   *
   * @return array{status: string, postal_code: ?string, missing: list<string>, suggestion: array<string, string>}
   */
  public function resolve(array $address): array {
    $postalCode = $this->lookup->normalize((string) ($address['postal_code'] ?? ''));
    if ($postalCode === NULL) {
      return $this->result(self::STATUS_INVALID, NULL, [], []);
    }

    $missing = array_values(array_filter(
      self::CEP_FIELDS,
      static fn (string $field): bool => trim((string) ($address[$field] ?? '')) === '',
    ));
    if ($missing === []) {
      return $this->result(self::STATUS_COMPLETE, $postalCode, [], []);
    }

    try {
      $found = $this->lookup->lookup($postalCode);
    }
    catch (\Throwable) {
      return $this->result(self::STATUS_UNAVAILABLE, $postalCode, $missing, []);
    }
    if ($found === NULL) {
      return $this->result(self::STATUS_NOT_FOUND, $postalCode, $missing, []);
    }

    // Sugestão limitada aos campos vazios: o que o usuário digitou prevalece.
    $suggestion = array_intersect_key($found, array_flip($missing));
    return $this->result(self::STATUS_SUGGESTED, $postalCode, $missing, $suggestion);
  }

  /**
   * @param list<string> $missing
   * @param array<string, string> $suggestion
   *
   * @return array{status: string, postal_code: ?string, missing: list<string>, suggestion: array<string, string>}
   */
  private function result(string $status, ?string $postalCode, array $missing, array $suggestion): array {
    return [
      'status' => $status,
      'postal_code' => $postalCode,
      'missing' => $missing,
      'suggestion' => $suggestion,
    ];
  }

}
