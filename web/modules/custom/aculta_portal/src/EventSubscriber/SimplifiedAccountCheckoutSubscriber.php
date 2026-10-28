<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\EventSubscriber;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\Email\EmailConfirmationRequester;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\Core\Url;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cadastro simplificado: ao concluir o checkout, pede a confirmação do e-mail da conta criada pelo Commerce.
 *
 * Reaproveita a confirmação de e-mail que já existe no portal (EmailConfirmationRequester, com o envio pelo
 * aculta_mailer). Roda depois da criação da conta pelo Commerce, só para conta nova, ainda não confirmada e sem pedido
 * anterior. A pessoa vai para a etapa de criação de senha depois de confirmar.
 */
final class SimplifiedAccountCheckoutSubscriber implements EventSubscriberInterface {

  /**
   * Chave de userData (módulo aculta_portal): conta criada pelo cadastro simplificado (pendente de senha).
   */
  public const FLAG = 'simplified_account';

  /**
   * Idade máxima, em segundos, de uma conta para ser considerada criada neste checkout.
   */
  private const NEW_ACCOUNT_WINDOW = 120;

  /**
   * Cria o assinante.
   */
  public function __construct(
    private readonly EmailConfirmationRequester $requester,
    private readonly EmailConfirmationPolicy $policy,
    private readonly UserDataInterface $userData,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Prioridade menor que a do Commerce: a conta já existe quando este assinante roda.
    return [CheckoutEvents::COMPLETION => ['onCompletion', -100]];
  }

  /**
   * Pede a confirmação do e-mail da conta do pedido, quando ela ainda não foi confirmada.
   *
   * Vale para conta criada neste checkout e para conta do cadastro simplificado que ainda não confirmou: uma nova
   * compra com o mesmo e-mail reenvia a confirmação. O intervalo entre reenvios é o do requester existente.
   * Contas comuns já existentes não recebem o pedido aqui.
   */
  public function onCompletion(OrderEvent $event): void {
    $customer = $event->getOrder()->getCustomer();
    if (!$customer instanceof UserInterface || $customer->isAnonymous() || $customer->isBlocked()) {
      return;
    }
    if ($this->policy->isConfirmed($customer)) {
      return;
    }
    $uid = (int) $customer->id();
    $nova = time() - (int) $customer->getCreatedTime() <= self::NEW_ACCOUNT_WINDOW;
    $simplificada = (bool) $this->userData->get('aculta_portal', $uid, self::FLAG);
    if (!$nova && !$simplificada) {
      return;
    }
    // Marca a conta como simplificada antes do pedido: a etapa de senha depende dessa marca.
    $this->userData->set('aculta_portal', $uid, self::FLAG, TRUE);
    $this->requester->request($customer, Url::fromRoute('aculta_portal.simplified_account.password'));
  }

}
