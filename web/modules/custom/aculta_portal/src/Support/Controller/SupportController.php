<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Support\Controller;

use Drupal\aculta_portal\MercadoPago\MercadoPagoSubscriptionRecorder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Presents support records owned by Commerce to the current account. */
final class SupportController extends ControllerBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly \Drupal\aculta_portal\Domain\DomainPurposeManager $domainPurposeManager,
    private readonly MercadoPagoSubscriptionRecorder $assinaturas,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_portal.mercado_pago.subscription_recorder'),
    );
  }

  public function mySupport(): array {
    // Orders are owner-scoped by the authenticated account; uid 0 would match
    // every guest checkout, so anonymous never reaches the storage lookup
    // (the route also requires _user_is_logged_in).
    $uid = (int) $this->account->id();
    $orders = $uid > 0
      ? $this->entities->getStorage('commerce_order')->loadByProperties(['uid' => $uid])
      : [];
    $rows = [];
    $payment_storage = $this->entities->getStorage('commerce_payment');
    foreach ($orders as $order) {
      $has_donation_item = FALSE;
      foreach ($order->getItems() as $item) {
        if ($item->bundle() === 'donation') {
          $has_donation_item = TRUE;
          break;
        }
      }
      if (!$has_donation_item) {
        continue;
      }

      $total = $order->getTotalPrice();
      $acoes = [];
      if ($order->bundle() === MercadoPagoSubscriptionRecorder::TIPO_PEDIDO) {
        // Doação mensal: a situação é a da assinatura no Mercado Pago, e o doador pode pausar, reativar ou cancelar.
        $status = MercadoPagoSubscriptionRecorder::rotulo($this->assinaturas->statusDoPedido($order) ?: MercadoPagoSubscriptionRecorder::STATUS_PENDENTE);
        $cobranca = $this->assinaturas->situacaoDaUltimaCobranca($order);
        if ($cobranca !== '') {
          $status = $this->t('@situacao. Última cobrança: @cobranca', [
            '@situacao' => $status,
            '@cobranca' => MercadoPagoSubscriptionRecorder::rotuloCobranca($cobranca),
          ]);
        }
        foreach (array_keys(MercadoPagoSubscriptionRecorder::ACOES) as $acao) {
          if ($this->assinaturas->podeAlterar($order, $acao)) {
            $acoes[] = [
              '#type' => 'link',
              '#title' => $this->acaoRotulo($acao),
              '#url' => Url::fromRoute('aculta_portal.support_subscription_action', [
                'commerce_order' => $order->id(),
                'acao' => $acao,
              ]),
            ];
          }
        }
      }
      else {
        $payments = $payment_storage->loadByProperties(['order_id' => $order->id()]);
        $payment_states = array_map(static fn ($payment): string => $payment->getState()->getId(), $payments);
        $status = match (TRUE) {
          in_array('completed', $payment_states, TRUE) => $this->t('Aprovado'),
          in_array('authorization', $payment_states, TRUE) => $this->t('Em processamento'),
          in_array('partially_refunded', $payment_states, TRUE) => $this->t('Reembolso parcial'),
          in_array('refunded', $payment_states, TRUE) => $this->t('Reembolsado'),
          in_array('voided', $payment_states, TRUE) => $this->t('Cancelado'),
          $order->getState()->getId() === 'canceled' => $this->t('Não concluído'),
          default => $this->t('Aguardando pagamento'),
        };
      }
      $rows[] = [
        gmdate('d/m/Y', $order->getPlacedTime() ?: $order->getCreatedTime()),
        $total ? $total->getCurrencyCode() . ' ' . $total->getNumber() : $this->t('A confirmar'),
        $status,
        ['data' => $acoes],
      ];
    }

    return [
      'intro' => ['#plain_text' => $this->t('Quando houver apoios vinculados à sua conta, eles aparecerão aqui.')],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Data'), $this->t('Valor'), $this->t('Situação'), $this->t('Ações')],
        '#rows' => $rows,
        '#empty' => $this->t('Ainda não há apoios vinculados a esta conta.'),
      ],
      'support_link' => [
        '#type' => 'link',
        '#title' => $this->t('Conheça as formas de apoio'),
        // A página de apoio fica no host de apoio (SUPPORT), não no host da conta nem no principal.
        '#url' => $this->domainPurposeManager->pathUrl('support', '/') ?? Url::fromRoute('aculta_portal.support_form'),
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'max-age' => 0],
    ];
  }

  /**
   * Rótulo do link de ação da assinatura.
   */
  private function acaoRotulo(string $acao): string {
    return (string) match ($acao) {
      'pausar' => $this->t('Pausar'),
      'reativar' => $this->t('Reativar'),
      default => $this->t('Cancelar assinatura'),
    };
  }

}
