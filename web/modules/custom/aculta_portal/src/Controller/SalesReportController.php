<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\aculta_portal\Sales\SalesReportQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Relatório próprio de vendas da loja (decisão do responsável, 2026-10-17).
 *
 * Lista as variações vendidas em pedidos concluídos, com quantidade e total por moeda. Não exibe dados de pagamento:
 * pagamentos são processados pelo Mercado Pago e continuam só com o administrador. A permissão é view commerce_order,
 * concedida ao gerente de loja. O relatório é recalculado a cada acesso (sem cache).
 */
final class SalesReportController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CurrencyFormatterInterface $currencyFormatter,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('string_translation'),
    );
  }

  /**
   * Página do relatório.
   */
  public function report(): array {
    $linhas = [];
    $totais = [];
    $orderStorage = $this->entityTypeManager->getStorage('commerce_order');
    // Consulta sem checagem de acesso por entidade: a rota já exige view commerce_order e o relatório só agrega.
    $ids = $orderStorage->getQuery()->accessCheck(FALSE)->condition('state', 'completed')->execute();

    foreach ($orderStorage->loadMultiple($ids) as $pedido) {
      foreach ($pedido->getItems() as $item) {
        // Doações ficam fora do relatório de vendas de produto (decisão do responsável).
        if ($item->bundle() === SalesReportQuery::TIPO_DOACAO) {
          continue;
        }
        $variacao = $item->getPurchasedEntity();
        $preco = $item->getTotalPrice();
        if ($variacao === NULL || $preco === NULL) {
          continue;
        }
        // Agrupa por variação e moeda: Price::add() só soma valores da mesma moeda.
        $moeda = $preco->getCurrencyCode();
        $chave = $variacao->id() . '|' . $moeda;
        $produto = $variacao->getProduct();
        $linhas[$chave] ??= [
          'produto' => $produto ? (string) $produto->label() : '',
          'variacao' => (string) $variacao->label(),
          'quantidade' => 0,
          'total' => new Price('0', $moeda),
        ];
        $linhas[$chave]['quantidade'] += (int) $item->getQuantity();
        $linhas[$chave]['total'] = $linhas[$chave]['total']->add($preco);
        $totais[$moeda] = ($totais[$moeda] ?? new Price('0', $moeda))->add($preco);
      }
    }

    usort($linhas, static fn (array $a, array $b): int => $b['quantidade'] <=> $a['quantidade']);
    $rows = array_map(fn (array $l) => [
      $l['produto'],
      $l['variacao'],
      $l['quantidade'],
      $this->formatar($l['total']),
    ], $linhas);

    $rodape = [];
    foreach ($totais as $total) {
      $rodape[] = ['', '', $this->t('Total'), $this->formatar($total)];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Produto'),
        $this->t('Variação'),
        $this->t('Quantidade'),
        $this->t('Total'),
      ],
      '#rows' => array_merge($rows, $rodape),
      '#empty' => $this->t('Nenhuma venda concluída até o momento.'),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Formata o total com o formatador de moeda do Commerce (locale e casas da moeda).
   */
  private function formatar(Price $total): string {
    return $this->currencyFormatter->format($total->getNumber(), $total->getCurrencyCode());
  }

}
