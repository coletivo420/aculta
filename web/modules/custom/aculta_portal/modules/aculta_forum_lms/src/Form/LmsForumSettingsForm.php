<?php

declare(strict_types=1);

namespace Drupal\aculta_forum_lms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures options owned by the LMS discussion adapter.
 */
final class LmsForumSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_lms_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_forum_lms.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_forum_lms.settings');
    $form['edit_window_minutes'] = [
      '#type' => 'number',
      '#title' => $this->t('Prazo para edição de comentário após publicação (minutos)'),
      '#description' => $this->t('Aplica-se somente às discussões de curso e turma. O padrão é 10 minutos, contado desde a primeira publicação e sem reinício após edição. Zero bloqueia edição pelo autor; Educadores e administradores mantêm as permissões contextuais existentes.'),
      '#default_value' => max(0, (int) ($config->get('edit_window_minutes') ?? 10)),
      '#min' => 0,
      '#step' => 1,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_forum_lms.settings')
      ->set('edit_window_minutes', max(0, (int) $form_state->getValue('edit_window_minutes')))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
