<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmação manual de e-mail pela administração (decisão de 2026-10-16).
 *
 * Usa a mesma política do fluxo normal: marca a confirmação do Portal e concede o papel email_confirmed. Não
 * envia nenhum e-mail. Registra no log quem confirmou e qual conta, sem o endereço.
 */
final class AdminEmailConfirmForm extends ConfirmFormBase {

  public function __construct(
    private readonly EmailConfirmationPolicy $policy,
    private readonly AccountInterface $currentUser,
    private readonly LoggerChannelInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.email_confirmation_policy'),
      $container->get('current_user'),
      $container->get('logger.factory')->get('aculta_portal'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_portal_admin_email_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return (string) $this->t('Confirmar o e-mail desta conta?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->t('Use somente quando a pessoa comprovou o endereço por outro meio. A conta passa a ter o papel "E-mail confirmado", como no fluxo normal. Nenhum e-mail é enviado.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('aculta_portal.email_confirmacao_pendentes');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $form_state->set('uid', (int) $user?->id());
    $form['conta'] = ['#markup' => '<p>' . $this->t('Conta: @nome (uid @uid)', ['@nome' => $user?->getAccountName(), '@uid' => $user?->id()]) . '</p>'];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->get('uid');
    if ($uid <= 0 || $this->policy->isConfirmed($this->entityUser($uid))) {
      $this->messenger()->addStatus($this->t('Este e-mail já estava confirmado.'));
      $form_state->setRedirect('aculta_portal.email_confirmacao_pendentes');
      return;
    }
    $this->policy->markConfirmed($uid);
    $this->logger->notice('Confirmação manual de e-mail: conta @conta confirmada pela administração (uid @admin).', ['@conta' => $uid, '@admin' => $this->currentUser->id()]);
    $this->messenger()->addStatus($this->t('E-mail confirmado para a conta @uid.', ['@uid' => $uid]));
    $form_state->setRedirect('aculta_portal.email_confirmacao_pendentes');
  }

  /**
   * Conta carregada pelo uid (a política lê o estado por uid).
   */
  private function entityUser(int $uid): AccountInterface {
    return $this->entityTypeManagerService->getStorage('user')->load($uid);
  }

}
