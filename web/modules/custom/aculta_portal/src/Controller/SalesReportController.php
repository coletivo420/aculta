<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
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
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
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
        $variacao = $item->getPurchasedEntity();
        $preco = $item->getTotalPrice();
        if ($variacao === NULL || $preco === NULL) {
          continue;
        }
        $chave = (string) $variacao->id();
        $produto = $variacao->getProduct();
        $linhas[$chave] ??= [
          'produto' => $produto ? (string) $produto->label() : '',
          'variacao' => (string) $variacao->label(),
          'quantidade' => 0,
          'total' => 0.0,
          'moeda' => $preco->getCurrencyCode(),
        ];
        $linhas[$chave]['quantidade'] += (int) $item->getQuantity();
        $linhas[$chave]['total'] += (float) $preco->getNumber();
        $moeda = $preco->getCurrencyCode();
        $totais[$moeda] = ($totais[$moeda] ?? 0.0) + (float) $preco->getNumber();
      }
    }

    usort($linhas, static fn (array $a, array $b): int => $b['quantidade'] <=> $a['quantidade']);
    $rows = array_map(fn (array $l) => [
      $l['produto'],
      $l['variacao'],
      $l['quantidade'],
      $this->formatar($l['total'], $l['moeda']),
    ], $linhas);

    $rodape = [];
    foreach ($totais as $moeda => $valor) {
      $rodape[] = ['', '', $this->t('Total'), $this->formatar($valor, $moeda)];
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
   * Formata valor com duas casas e a moeda do pedido.
   */
  private function formatar(float $valor, string $moeda): string {
    return number_format($valor, 2, ',', '.') . ' ' . $moeda;
  }

}
