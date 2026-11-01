<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Form;

use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Concessão/revogação manual com ator, data e motivo na entidade histórica.
 */
final class AchievementGrantForm extends FormBase {

  public function __construct(private readonly BadgeService $badges) {}

  /**
   * Create the grant form from Drupal services.
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_user.badge_service'));
  }

  /**
   * Return the stable form identifier.
   */
  public function getFormId(): string {
    return 'aculta_game_achievement_grant';
  }

  /**
   * Build the manual achievement grant and revocation form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $options = [];
    foreach ($this->badges->definitions() as $id => $definition) {
      // Permitir revogar uma concessão manual mesmo após pausar sua definição.
      $options[$id] = (string) $definition['label'];
    }
    $form['person'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Pessoa'),
      '#target_type' => 'user',
      '#required' => TRUE,
      '#selection_settings' => ['include_anonymous' => FALSE],
    ];
    $form['achievement'] = [
      '#type' => 'select',
      '#title' => $this->t('Conquista'),
      '#options' => $options,
      '#required' => TRUE,
    ];
    $form['operation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Operação'),
      '#options' => [
        'grant' => $this->t('Conceder'),
        'revoke' => $this->t('Revogar concessão manual'),
      ],
      '#default_value' => 'grant',
      '#description' => $this->t('Concessões automáticas permanecem como fatos históricos. Para conceder, a definição precisa estar ativa e permitir concessão administrativa.'),
    ];
    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Motivo administrativo'),
      '#required' => TRUE,
      '#maxlength' => 2000,
      '#description' => $this->t('Registro interno. Não inclua documentos, contato ou outros dados pessoais desnecessários.'),
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Aplicar operação')];
    return $form;
  }

  /**
   * Validate eligibility, operation, and the required audit reason.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getValue('operation') === 'grant') {
      $definition = $this->badges->definitions()[$form_state->getValue('achievement')] ?? [];
      if (empty($definition['enabled']) || !in_array('manual', $definition['triggers'] ?? [], TRUE)) {
        $form_state->setErrorByName('achievement', $this->t('A conquista deve estar ativa e permitir concessão administrativa.'));
      }
      if (!$this->badges->isEligible((int) $form_state->getValue('person'))) {
        $form_state->setErrorByName('person', $this->t('Escolha uma conta ativa, sem papel legado.'));
      }
    }
    if (trim((string) $form_state->getValue('reason')) === '') {
      $form_state->setErrorByName('reason', $this->t('Informe o motivo.'));
    }
  }

  /**
   * Apply the manual grant or revocation and record its reason.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = (int) $form_state->getValue('person');
    $id = (string) $form_state->getValue('achievement');
    $actor = (int) $this->currentUser()->id();
    $reason = (string) $form_state->getValue('reason');
    $changed = $form_state->getValue('operation') === 'grant'
      ? $this->badges->award($uid, $id, $actor, $reason)
      : $this->badges->revoke($uid, $id, $actor, $reason);
    $message = $changed
      ? $this->t('Operação registrada no histórico da conquista.')
      : $this->t('Nenhuma alteração: verifique a concessão ativa e a origem manual.');
    $this->messenger()->addStatus($message);
    $form_state->setRedirect('aculta_game.achievement_grant');
  }

}
