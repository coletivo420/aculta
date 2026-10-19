<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Cart;

/**
 * Separadores do carrinho único: cada tipo de pedido pertence a um separador (decisão de 2026-10-17).
 *
 * O carrinho é um só; o agrupamento e o botão de checkout são por separador. Os pedidos e o checkout ficam no domínio
 * principal (rotas do Commerce).
 */
final class CartSite {

  public const LOJA = 'loja';

  public const CURSOS = 'cursos';

  public const APOIO = 'apoio';

  /** Assinatura: espaço próprio no carrinho, com checkout próprio (fora do carrinho unificado). */
  public const ASSINATURA = 'assinatura';

  /** Ordem dos separadores no painel. */
  public const ORDEM = [self::LOJA, self::CURSOS, self::APOIO, self::ASSINATURA];

  /** Tipo de pedido → separador. Tipo não listado cai na loja. */
  private const POR_TIPO = [
    'default' => self::LOJA,
    'curso' => self::CURSOS,
    'doacao' => self::APOIO,
    'assinatura' => self::ASSINATURA,
  ];

  /** Rótulo visível do separador (texto fixo do painel). */
  private const ROTULOS = [
    self::LOJA => 'Loja',
    self::CURSOS => 'Cursos',
    self::APOIO => 'Apoio',
    self::ASSINATURA => 'Assinatura',
  ];

  public static function paraTipo(string $tipoDePedido): string {
    return self::POR_TIPO[$tipoDePedido] ?? self::LOJA;
  }

  public static function rotulo(string $site): string {
    return self::ROTULOS[$site];
  }

}
