<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Sales;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Tabela de pedidos com o pagamento de cada um e as ações permitidas (painel de vendas e de doações).
 *
 * Os links de ação só aparecem quando a permissão do escopo do pedido confere. A permissão é checada de novo
 * no formulário de ação, então um link escondido nunca é a única barreira.
 */
class SalesOrdersTableBuilder {

  use StringTranslationTrait;

  public const POR_PAGINA = 25;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SalesReportQuery $consultas,
    private readonly CurrencyFormatterInterface $currencyFormatter,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly AccountInterface $usuario,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Tabela paginada de pedidos com itens do escopo informado.
   *
   * @return array<string, mixed>
   */
  public function construir(SalesReportFilters $filtros, string $escopo, int $pagina, string $rotaAtual, array $queryAtual): array {
    $pagina = max(0, $pagina);
    $resultado = $this->consultas->pedidos($filtros, $escopo, $pagina, self::POR_PAGINA);
    $pedidos = $this->entityTypeManager->getStorage('commerce_order')->loadMultiple($resultado['ids']);
    $acesso = new SalesPaymentAccess($this->usuario);

    $linhas = [];
    foreach ($resultado['ids'] as $id) {
      $pedido = $pedidos[$id] ?? NULL;
      if (!$pedido instanceof OrderInterface) {
        continue;
      }
      $podeAgir = $acesso->podeAgir(SalesPaymentAccess::escoposDoPedido($this->tiposDosItens($pedido)));
      $linhas[] = [
        $pedido->getOrderNumber() ?: (string) $pedido->id(),
        $this->dateFormatter->format((int) $pedido->getPlacedTime(), 'short'),
        $this->currencyFormatter->format($pedido->getTotalPrice()?->getNumber() ?? '0', $pedido->getTotalPrice()?->getCurrencyCode() ?? 'BRL'),
        ['data' => $this->pagamentos($pedido, $podeAgir)],
      ];
    }

    $paginacao = [];
    if ($pagina > 0) {
      $paginacao[] = Link::fromTextAndUrl($this->t('Anterior'), Url::fromRoute($rotaAtual, [], ['query' => ['pagina' => $pagina - 1] + $queryAtual]))->toRenderable();
    }
    if (($pagina + 1) * self::POR_PAGINA < $resultado['total']) {
      $paginacao[] = Link::fromTextAndUrl($this->t('Próxima'), Url::fromRoute($rotaAtual, [], ['query' => ['pagina' => $pagina + 1] + $queryAtual]))->toRenderable();
    }

    return [
      'tabela' => [
        '#type' => 'table',
        '#header' => [$this->t('Pedido'), $this->t('Data'), $this->t('Total'), $this->t('Pagamentos')],
        '#rows' => $linhas,
        '#empty' => $this->t('Nenhum pedido concluído com este tipo de item no intervalo.'),
      ],
      'paginacao' => $paginacao,
      '#cache' => ['max-age' => 0, 'contexts' => ['url.query_args', 'user.permissions']],
    ];
  }

  /**
   * Pagamentos do pedido, com estado, status remoto e ações permitidas.
   *
   * @return array<int, array<string, mixed>>
   */
  private function pagamentos(OrderInterface $pedido, bool $podeAgir): array {
    $saida = [];
    $pagamentos = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties(['order_id' => $pedido->id()]);
    if ($pagamentos === []) {
      return [['#markup' => $this->t('Sem pagamento registrado.')]];
    }
    foreach ($pagamentos as $pagamento) {
      if (!$pagamento instanceof PaymentInterface) {
        continue;
      }
      $saida[] = [
        '#markup' => $this->t('@estado — Mercado Pago: @remoto', [
          '@estado' => $pagamento->getState()->getLabel(),
          '@remoto' => $pagamento->getRemoteState() ?: $this->t('sem status'),
        ]) . ' ',
        'acoes' => $podeAgir ? $this->acoes($pedido, $pagamento) : [],
      ];
    }
    return $saida;
  }

  /**
   * Links de ação do pagamento. Cancelar só aparece para pagamento pendente em authorization.
   *
   * @return array<int, array<string, mixed>>
   */
  private function acoes(OrderInterface $pedido, PaymentInterface $pagamento): array {
    $links = [];
    $links[] = Link::fromTextAndUrl($this->t('Atualizar'), Url::fromRoute('aculta_portal.relatorio_vendas_pagamento_atualizar', ['commerce_payment' => $pagamento->id()]))->toRenderable();
    if ($pagamento->getState()->getId() === 'authorization') {
      $links[] = Link::fromTextAndUrl($this->t('Cancelar'), Url::fromRoute('aculta_portal.relatorio_vendas_pagamento_cancelar', ['commerce_payment' => $pagamento->id()]))->toRenderable();
    }
    if (in_array($pagamento->getState()->getId(), MercadoPagoPaymentPolicy::ESTORNAVEIS, TRUE)) {
      $links[] = Link::fromTextAndUrl($this->t('Estornar'), Url::fromRoute('entity.commerce_payment.operation_form', [
        'commerce_order' => $pedido->id(),
        'commerce_payment' => $pagamento->id(),
        'operation' => 'refund-payment',
      ]))->toRenderable();
    }
    return $links;
  }

  /**
   * Tipos dos itens do pedido (para o escopo).
   *
   * @return string[]
   */
  private function tiposDosItens(OrderInterface $pedido): array {
    return array_map(static fn ($item): string => (string) $item->bundle(), $pedido->getItems());
  }

}
