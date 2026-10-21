<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_user\Associado\AssociadoMarker;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Situação de associado de uma conta: concede, suspende, desliga ou recusa um pedido. Só o administrador
 * (permissão `administer aculta associados`). Não concede acesso a conteúdo; é registro da associação.
 */
final class AssociadoSituacaoForm extends FormBase {

  public function __construct(
    private readonly AssociadoMarker $marker,
    private readonly AccountInterface $currentUserService,
    private readonly LoggerChannelInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_user.associado_marker'),
      $container->get('current_user'),
      $container->get('logger.factory')->get('aculta_user'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
    );
  }

  public function getFormId(): string {
    return 'aculta_user_associado_situacao';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $uid = (int) $user?->id();
    $form_state->set('uid', $uid);
    $atual = $this->marker->situacao($uid);
    $adesao = $this->marker->dataAdesao($uid);
    $form['estado'] = ['#markup' => '<p>' . $this->t('Situação atual: @situacao.', ['@situacao' => $this->rotulo($atual)])
      . ($adesao !== NULL ? ' ' . $this->t('Data de adesão: @data.', ['@data' => $this->dateFormatter->format($adesao, 'custom', 'd/m/Y')]) : '')
      . '</p>'];
    $opcoes = [
      AssociadoMarker::ATIVO => $this->t('Ativo (concede a situação de associado)'),
      AssociadoMarker::SUSPENSO => $this->t('Suspenso'),
      AssociadoMarker::DESLIGADO => $this->t('Desligado'),
    ];
    if ($atual === AssociadoMarker::PENDENTE) {
      $opcoes['recusar'] = $this->t('Recusar pedido de adesão');
    }
    $form['situacao'] = [
      '#type' => 'radios',
      '#title' => $this->t('Nova situação'),
      '#options' => $opcoes,
      '#required' => TRUE,
    ];
    $form['motivo'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Motivo'),
      '#description' => $this->t('Registrado com o nome de quem alterou. Recomendado para suspensão e desligamento.'),
      '#maxlength' => 2000,
    ];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => $this->t('Salvar situação')]];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->get('uid');
    $situacao = (string) $form_state->getValue('situacao');
    $motivo = (string) $form_state->getValue('motivo');
    $admin = (int) $this->currentUserService->id();
    if ($situacao === 'recusar') {
      $ok = $this->marker->recusar($uid);
      $this->logger->notice('Pedido de adesão da conta @conta recusado por @admin.', ['@conta' => $uid, '@admin' => $admin]);
      $this->messenger()->addStatus($ok ? $this->t('Pedido de adesão recusado.') : $this->t('Não havia pedido pendente.'));
    }
    elseif ($this->marker->definir($uid, $situacao, $admin, $motivo)) {
      $this->logger->notice('Situação de associado da conta @conta alterada para @situacao por @admin.', ['@conta' => $uid, '@situacao' => $situacao, '@admin' => $admin]);
      $this->messenger()->addStatus($this->t('Situação alterada para @situacao.', ['@situacao' => $this->rotulo($situacao)]));
    }
    else {
      $this->messenger()->addStatus($this->t('A conta já estava nesta situação.'));
    }
    $form_state->setRedirect('aculta_user.associados');
  }

  /** Rótulo legível de uma situação. */
  private function rotulo(?string $situacao): string {
    return match ($situacao) {
      AssociadoMarker::PENDENTE => (string) $this->t('pedido em análise'),
      AssociadoMarker::ATIVO => (string) $this->t('ativo'),
      AssociadoMarker::SUSPENSO => (string) $this->t('suspenso'),
      AssociadoMarker::DESLIGADO => (string) $this->t('desligado'),
      default => (string) $this->t('sem pedido de adesão'),
    };
  }

}
