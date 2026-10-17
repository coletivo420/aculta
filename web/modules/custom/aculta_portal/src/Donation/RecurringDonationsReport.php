<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Donation;

use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\commerce_order\Entity\OrderInterface;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Painel de doações recorrentes (assinaturas de doação) para o painel administrativo.
 *
 * Mostra o resumo por situação e a lista dos pedidos mais recentes. A situação é a guardada no pedido (status informado
 * pelo Mercado Pago pelos webhooks). O painel é somente leitura; pausar ou cancelar é feito pelo doador em "Meu apoio".
 */
final class RecurringDonationsReport {

  /** Quantidade máxima de pedidos listados (os mais recentes). Acima disso, o resumo continua completo. */
  public const LIMITE_LISTA = 100;

  public function __construct(
    private readonly EntityTypeManagerInterface $entidades,
    private readonly MercadoPagoSubscriptionRecorder $assinaturas,
    private readonly CurrencyFormatterInterface $moedas,
  ) {}

  /**
   * Tabela-resumo e lista de doações recorrentes, como render array.
   */
  public function construir(): array {
    $ids = $this->entidades->getStorage('commerce_order')->getQuery()
      ->condition('type', MercadoPagoSubscriptionRecorder::TIPO_PEDIDO)
      // Painel administrativo: a rota exige a permissão do relatório; a lista não depende do acesso da conta.
      ->accessCheck(FALSE)
      ->sort('order_id', 'DESC')
      ->execute();

    $resumo = [];
    $linhas = [];
    foreach (array_slice(array_values($ids), 0, self::LIMITE_LISTA) as $id) {
      $pedido = $this->entidades->getStorage('commerce_order')->load($id);
      if (!$pedido instanceof OrderInterface) {
        continue;
      }
      $status = $this->assinaturas->statusDoPedido($pedido) ?: MercadoPagoSubscriptionRecorder::STATUS_PENDENTE;
      $resumo[$status] = ($resumo[$status] ?? 0) + 1;
      [$cobrancas, $ultima] = $this->cobrancas($pedido);
      $total = $pedido->getTotalPrice();
      $linhas[] = [
        [
          'data' => [
            '#type' => 'link',
            '#title' => '#' . $pedido->id(),
            '#url' => Url::fromRoute('entity.commerce_order.canonical', ['commerce_order' => $pedido->id()]),
          ],
        ],
        gmdate('d/m/Y', $pedido->getPlacedTime() ?: $pedido->getCreatedTime()),
        $total ? $this->moedas->format($total->getNumber(), $total->getCurrencyCode()) : new TranslatableMarkup('A confirmar'),
        MercadoPagoSubscriptionRecorder::rotulo($status),
        (string) $cobrancas,
        $ultima ? gmdate('d/m/Y', $ultima) : new TranslatableMarkup('Nenhuma ainda'),
      ];
    }

    $linhasResumo = [];
    foreach (MercadoPagoSubscriptionRecorder::STATUS_ORDEM as $status) {
      $linhasResumo[] = [MercadoPagoSubscriptionRecorder::rotulo($status), $resumo[$status] ?? 0];
    }

    return [
      'resumo' => [
        '#type' => 'table',
        '#header' => [new TranslatableMarkup('Situação'), new TranslatableMarkup('Assinaturas')],
        '#rows' => $linhasResumo,
        '#cache' => ['max-age' => 0],
      ],
      'lista' => [
        '#type' => 'table',
        '#header' => [
          new TranslatableMarkup('Pedido'),
          new TranslatableMarkup('Início'),
          new TranslatableMarkup('Valor mensal'),
          new TranslatableMarkup('Situação'),
          new TranslatableMarkup('Cobranças aprovadas'),
          new TranslatableMarkup('Última cobrança'),
        ],
        '#rows' => $linhas,
        '#empty' => new TranslatableMarkup('Nenhuma doação recorrente ainda.'),
        '#cache' => ['max-age' => 0],
      ],
    ];
  }

  /**
   * Quantidade de cobranças aprovadas do pedido e a data (timestamp) da última.
   *
   * @return array{0: int, 1: int}
   */
  private function cobrancas(OrderInterface $pedido): array {
    $contagem = 0;
    $ultima = 0;
    foreach ($this->entidades->getStorage('commerce_payment')->loadByProperties(['order_id' => $pedido->id()]) as $pagamento) {
      if ($pagamento->getState()->getId() !== 'completed') {
        continue;
      }
      $contagem++;
      $ultima = max($ultima, (int) $pagamento->getAuthorizedTime());
    }
    return [$contagem, $ultima];
  }

}
