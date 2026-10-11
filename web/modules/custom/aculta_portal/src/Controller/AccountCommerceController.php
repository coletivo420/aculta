<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Meus pedidos e meus pagamentos, dentro do painel da conta. O usuário vem da sessão, nunca da URL.
 */
final class AccountCommerceController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $account,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /** Pedidos da pessoa logada (entidades do Commerce, sem Views). Rascunhos de carrinho ficam fora. */
  public function orders(): array {
    $storage = $this->entityTypeManager()->getStorage('commerce_order');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('uid', $this->account->id())
      ->condition('state', 'draft', '<>')
      ->sort('placed', 'DESC')
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $total = $order->getTotalPrice();
      $rows[] = [
        '#' . $order->getOrderNumber(),
        $order->getPlacedTime() ? $this->dateFormatter->format($order->getPlacedTime(), 'short') : '',
        $total ? $total->getNumber() . ' ' . $total->getCurrencyCode() : '',
        $order->getState()->getLabel(),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['portal-account__commerce', 'portal-account__commerce--orders']],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Pedido'), $this->t('Data'), $this->t('Total'), $this->t('Situação')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não tem pedidos.'),
      ],
    ];
  }

  /** Métodos de pagamento da pessoa logada, somente leitura. */
  public function payments(): array {
    $storage = $this->entityTypeManager()->getStorage('commerce_payment_method');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('uid', $this->account->id())->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $method) {
      $rows[] = [
        $method->label(),
        $method->bundle(),
        $method->isDefault() ? $this->t('Sim') : $this->t('Não'),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['portal-account__commerce', 'portal-account__commerce--payments']],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Método'), $this->t('Tipo'), $this->t('Padrão')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não tem métodos de pagamento salvos.'),
      ],
    ];
  }

}
