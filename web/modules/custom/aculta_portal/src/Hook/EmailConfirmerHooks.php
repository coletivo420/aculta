<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Access\EmailConfirmationResponseAccessHandler;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Integração do Portal com as confirmações de e-mail (email_confirmer).
 */
final class EmailConfirmerHooks {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly EmailConfirmationPolicy $policy,
  ) {}

  /** Validade infinita: troca a classe da entidade de confirmação pela do Portal (ver EmailConfirmationEntity). */
  #[Hook('entity_type_alter')]
  public function alterConfirmationEntityClass(array &$entity_types): void {
    if (isset($entity_types['aculta_mail_confirmer'])) {
      $entity_types['aculta_mail_confirmer']->setClass(\Drupal\aculta_portal\Email\EmailConfirmationEntity::class);
    }
  }

  /**
   * Troca o handler de acesso das confirmações de e-mail por um que libera a resposta ao visitante.
   */
  #[Hook('entity_type_alter')]
  public function alterConfirmationAccess(array &$entity_types): void {
    if (isset($entity_types['aculta_mail_confirmer'])) {
      $entity_types['aculta_mail_confirmer']->setHandlerClass('access', EmailConfirmationResponseAccessHandler::class);
    }
  }

  /**
   * Após confirmar a troca de e-mail, entra na conta do pedido se o visitante estiver anônimo.
   *
   * Só vale para confirmações de troca de e-mail de conta, para conta ativa, e só quando o visitante ainda não
   * está logado (não troca a sessão de outro usuário). O hash do link já foi verificado por confirm().
   */
  #[Hook('aculta_mail_confirmer')]
  public function loginAfterConfirmation(string $op, AcultaMailConfirmationInterface $confirmation): void {
    if ($op === 'confirm' && $confirmation->getRealm() === 'aculta_registration') {
      // Confirmação do cadastro: o e-mail da conta está comprovado.
      $this->policy->markConfirmed((int) $confirmation->getProperty('user'));
      return;
    }
    if ($op !== 'confirm' || $confirmation->getRealm() !== 'aculta_mail_confirmer_user') {
      return;
    }
    $uid = (int) $confirmation->getProperty('user');
    // O novo endereço foi comprovado pelo próprio link: o e-mail da conta passa a ser confirmado.
    $this->policy->markConfirmed($uid);
    if (!$this->currentUser->isAnonymous()) {
      return;
    }
    $account = $uid > 0 ? $this->entityTypeManager->getStorage('user')->load($uid) : NULL;
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return;
    }
    user_login_finalize($account);
  }

}
