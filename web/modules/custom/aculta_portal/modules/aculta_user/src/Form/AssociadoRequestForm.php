<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Associado\AssociadoMarker;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pedido de adesão à associação, feito pela própria pessoa em Minha conta.
 *
 * O pedido só cria uma situação "pedido em análise". Quem concede, suspende ou desliga é o administrador. Exige e-mail
 * confirmado, para que o pedido venha de uma conta verificada.
 */
final class AssociadoRequestForm extends FormBase {

  public function __construct(
    private readonly AssociadoMarker $marker,
    private readonly AccountInterface $currentUserService,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly EmailConfirmationPolicy $emailConfirmation,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.associado_marker'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('aculta_portal.email_confirmation_policy'),
    );
  }

  public function getFormId(): string {
    return 'aculta_user_associado_pedido';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $uid = (int) $this->currentUserService->id();
    $situacao = $this->marker->situacao($uid);
    $form['descricao'] = ['#markup' => '<p>' . $this->texto($situacao, $uid) . '</p>'];
    if ($situacao === AssociadoMarker::CONVIDADO) {
      // Convite do administrador: só o aceite concede a situação de associado.
      $form['actions'] = [
        '#type' => 'actions',
        'aceitar' => ['#type' => 'submit', '#value' => $this->t('Aceitar convite'), '#name' => 'aceitar'],
        'recusar' => ['#type' => 'submit', '#value' => $this->t('Recusar convite'), '#name' => 'recusar'],
      ];
    }
    elseif ($this->podePedir($situacao) && $this->emailConfirmation->isConfirmed($this->currentUserService)) {
      $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Pedir adesão')]];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $this->currentUserService->id();
    // Convite: aceitar ou recusar, sem depender de e-mail confirmado além do que o convite já exige.
    $acao = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($acao === 'aceitar') {
      $this->messenger()->addStatus($this->marker->aceitarConvite($uid)
        ? $this->t('Convite aceito. Você agora é associado.')
        : $this->t('Não há convite para aceitar.'));
      $form_state->setRedirect('aculta_user.associado_pedido');
      return;
    }
    if ($acao === 'recusar') {
      $this->messenger()->addStatus($this->marker->recusarConvite($uid)
        ? $this->t('Convite recusado.')
        : $this->t('Não há convite para recusar.'));
      $form_state->setRedirect('aculta_user.associado_pedido');
      return;
    }
    // Revalida no envio: o botão não basta, a regra é do servidor.
    if (!$this->emailConfirmation->isConfirmed($this->currentUserService)) {
      $this->messenger()->addError($this->t('Confirme seu e-mail antes de pedir adesão.'));
    }
    elseif ($this->marker->pedir($uid)) {
      $this->messenger()->addStatus($this->t('Pedido de adesão enviado. A associação vai analisar.'));
      // Reconstrói o formulário com a situação nova: a resposta AJAX da conta devolve o formulário já atualizado.
      $form_state->setRebuild();
      return;
    }
    else {
      $this->messenger()->addStatus($this->t('Não foi possível enviar o pedido agora.'));
    }
    $form_state->setRedirect('aculta_user.associado_pedido');
  }

  /** Só quem não tem situação ou foi desligado pode pedir. */
  private function podePedir(?string $situacao): bool {
    return $situacao === NULL || $situacao === AssociadoMarker::DESLIGADO;
  }

  /** Texto conforme a situação. */
  private function texto(?string $situacao, int $uid): string {
    if (!$this->emailConfirmation->isConfirmed($this->currentUserService)) {
      return (string) $this->t('Confirme seu e-mail antes de pedir adesão à associação.');
    }
    return match ($situacao) {
      AssociadoMarker::PENDENTE => (string) $this->t('Seu pedido de adesão está em análise.'),
      AssociadoMarker::ATIVO => (string) $this->t('Você é associado desde @data.', ['@data' => $this->dateFormatter->format($this->marker->dataAdesao($uid) ?? 0, 'custom', 'd/m/Y')]),
      AssociadoMarker::SUSPENSO => (string) $this->t('Sua situação de associado está suspensa. Procure a associação para mais informações.'),
      AssociadoMarker::DESLIGADO => (string) $this->t('Sua situação de associado foi encerrada. Você pode pedir adesão de novo.'),
      AssociadoMarker::CONVIDADO => (string) $this->t('A associação convidou você a ser associado. Aceite ou recuse o convite abaixo.'),
      default => (string) $this->t('Você ainda não é associado. Pedir adesão envia um pedido para a associação analisar; só a administração concede a situação.'),
    };
  }

}
