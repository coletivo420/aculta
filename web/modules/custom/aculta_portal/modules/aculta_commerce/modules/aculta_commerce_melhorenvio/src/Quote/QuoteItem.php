<?php

declare(strict_types=1);

namespace Drupal\aculta_commerce_melhorenvio\Quote;

/**
 * Item do carrinho para cotação: medida de uma unidade, quantidade e valor declarado da linha.
 *
 * A API do Melhor Envio recebe cada item com a própria medida e quantidade e monta as caixas (campo `products`).
 */
final class QuoteItem {

  /**
   * Cria o item.
   *
   * @param string $reference
   *   Identificador do item no envio (ex.: SKU da variação), sem dados pessoais.
   */
  public function __construct(
    public readonly string $reference,
    public readonly QuotePackage $package,
    public readonly int $quantity,
    public readonly float $insuranceValue,
  ) {}

}
