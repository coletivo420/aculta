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
 * Código no Aculta Portal, Design no ACULTA420: este construtor só marca os elementos com tokens semânticos
 * (data-portal-ui). Cores, espaçamento e classes ficam no tema (PortalUiHooks e css/components/sales-panel.css).
 * Os links de ação só aparecem quando a permissão do escopo do pedido confere. A permissão é checada de novo
 * no formulário de ação, então um link escondido nunca é a única barreira.
 */
class SalesOrdersTableBuilder {

  use StringTranslationTrait;

  public const POR_PAGINA = 25;

  /**
   * Atributo de contrato entre módulo e tema (docs/portal/TOKENS-E-ATRIBUTOS.md).
   */
  public const ATRIBUTO = 'data-portal-ui';

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
        '#attributes' => [self::ATRIBUTO => 'sales-table'],
        '#header' => [$this->t('Pedido'), $this->t('Data'), $this->t('Total'), $this->t('Pagamentos')],
        '#rows' => $linhas,
        '#empty' => $this->t('Nenhum pedido concluído com este tipo de item no intervalo.'),
      ],
      'paginacao' => $paginacao === [] ? [] : [
        '#type' => 'container',
        '#attributes' => [self::ATRIBUTO => 'sales-pagination'],
        'links' => $paginacao,
      ],
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
        '#type' => 'container',
        '#attributes' => [self::ATRIBUTO => 'sales-payment'],
        'estado' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [self::ATRIBUTO => $this->tokenDoEstado($pagamento->getState()->getId())],
          '#value' => $pagamento->getState()->getLabel(),
        ],
        'remoto' => [
          '#markup' => ' ' . $this->t('Mercado Pago: @remoto', [
            '@remoto' => $pagamento->getRemoteState() ?: $this->t('sem status'),
          ]),
        ],
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
    $links[] = $this->link($this->t('Atualizar'), Url::fromRoute('aculta_portal.relatorio_vendas_pagamento_atualizar', ['commerce_payment' => $pagamento->id()]), 'sales-action');
    if ($pagamento->getState()->getId() === 'authorization') {
      $links[] = $this->link($this->t('Cancelar'), Url::fromRoute('aculta_portal.relatorio_vendas_pagamento_cancelar', ['commerce_payment' => $pagamento->id()]), 'sales-action-danger');
    }
    if (in_array($pagamento->getState()->getId(), MercadoPagoPaymentPolicy::ESTORNAVEIS, TRUE)) {
      $links[] = $this->link($this->t('Estornar'), Url::fromRoute('entity.commerce_payment.operation_form', [
        'commerce_order' => $pedido->id(),
        'commerce_payment' => $pagamento->id(),
        'operation' => 'refund-payment',
      ]), 'sales-action-danger');
    }
    return $links;
  }

  /**
   * Link com token de ação. Botão ou cor são decididos pelo tema.
   *
   * @return array<string, mixed>
   */
  private function link(mixed $texto, Url $url, string $token): array {
    $link = Link::fromTextAndUrl($texto, $url)->toRenderable();
    $link['#attributes'] = [self::ATRIBUTO => $token];
    return $link;
  }

  /**
   * Token de estado do pagamento. O tema decide a cor de cada token.
   */
  private function tokenDoEstado(string $estado): string {
    return match ($estado) {
      'completed' => 'sales-state-ok',
      'authorization' => 'sales-state-pending',
      'partially_refunded', 'refunded' => 'sales-state-refunded',
      'authorization_voided', 'voided' => 'sales-state-voided',
      default => 'sales-state-neutral',
    };
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
