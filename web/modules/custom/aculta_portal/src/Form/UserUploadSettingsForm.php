<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Configures the shared policy for user file uploads across the Portal. */
final class UserUploadSettingsForm extends ConfigFormBase {

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_portal_user_upload_settings';
  }

  /** {@inheritdoc} */
  protected function getEditableConfigNames(): array {
    return ['aculta_portal.user_upload'];
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_portal.user_upload');
    $form['introduction'] = [
      '#plain_text' => $this->t('Esta política central é usada pelos formulários de envio de arquivos para usuários no Portal, incluindo o Fórum. Cada área continua responsável por associar o arquivo ao conteúdo e controlar sua publicação e acesso.'),
    ];
    $form['policy_tabs'] = ['#type' => 'vertical_tabs'];
    $form['global'] = [
      '#type' => 'details',
      '#title' => $this->t('Política global'),
      '#group' => 'policy_tabs',
      '#open' => TRUE,
    ];
    $form['global']['allowed_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extensões permitidas'),
      '#description' => $this->t('Informe extensões separadas por espaço. Imagens também precisam passar pela política do aculta_image.'),
      '#default_value' => $config->get('allowed_extensions') ?? UserUploadPolicy::DEFAULT_EXTENSIONS,
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['global']['max_size_mb'] = [
      '#type' => 'number',
      '#title' => $this->t('Tamanho máximo por arquivo (MB)'),
      '#description' => $this->t('O padrão é 10 MB. Este valor pode ser maior; os limites do servidor PHP continuam valendo.'),
      '#default_value' => $config->get('max_size_mb') ?? 10,
      '#min' => 1,
      '#max' => intdiv(PHP_INT_MAX, 1048576),
      '#step' => 1,
      '#required' => TRUE,
    ];
    $forum = $config->get('consumers.forum') ?? [];
    $form['forum'] = [
      '#type' => 'details',
      '#title' => $this->t('Fórum'),
      '#group' => 'policy_tabs',
    ];
    $form['forum']['inherit_global'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Usar a política global'),
      '#default_value' => $forum['inherit_global'] ?? TRUE,
    ];
    $form['forum']['allowed_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extensões permitidas no Fórum'),
      '#default_value' => $forum['allowed_extensions'] ?? UserUploadPolicy::DEFAULT_EXTENSIONS,
      '#maxlength' => 255,
      '#states' => ['visible' => [':input[name="forum[inherit_global]"]' => ['checked' => FALSE]]],
    ];
    $form['forum']['max_size_mb'] = [
      '#type' => 'number',
      '#title' => $this->t('Tamanho máximo por arquivo no Fórum (MB)'),
      '#default_value' => $forum['max_size_mb'] ?? 10,
      '#min' => 1,
      '#max' => intdiv(PHP_INT_MAX, 1048576),
      '#step' => 1,
      '#states' => ['visible' => [':input[name="forum[inherit_global]"]' => ['checked' => FALSE]]],
    ];
    return parent::buildForm($form, $form_state);
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    foreach (['global' => 'Política global', 'forum' => 'Fórum'] as $scope => $label) {
      if ($scope === 'forum' && $form_state->getValue(['forum', 'inherit_global'])) {
        continue;
      }
      $extensions = preg_split('/\s+/', strtolower(trim((string) $form_state->getValue([$scope, 'allowed_extensions'])))) ?: [];
      $extensions = array_values(array_unique(array_filter($extensions)));
      if ($extensions === [] || array_diff($extensions, UserUploadPolicy::SUPPORTED_EXTENSIONS) !== []) {
        $form_state->setErrorByName($scope . '][allowed_extensions', $this->t('Em @scope, use apenas: @extensions.', ['@scope' => $label, '@extensions' => implode(', ', UserUploadPolicy::SUPPORTED_EXTENSIONS)]));
      }
      $max_size = $form_state->getValue([$scope, 'max_size_mb']);
      if (!is_numeric($max_size) || (int) $max_size < 1 || (int) $max_size > intdiv(PHP_INT_MAX, 1048576)) {
        $form_state->setErrorByName($scope . '][max_size_mb', $this->t('Informe um limite de tamanho válido para @scope.', ['@scope' => $label]));
      }
    }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $global_extensions = preg_split('/\s+/', strtolower(trim((string) $form_state->getValue(['global', 'allowed_extensions'])))) ?: [];
    $global_extensions = array_values(array_unique(array_filter($global_extensions)));
    $forum_extensions = preg_split('/\s+/', strtolower(trim((string) $form_state->getValue(['forum', 'allowed_extensions'])))) ?: [];
    $forum_extensions = array_values(array_unique(array_filter($forum_extensions)));
    $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', implode(' ', $global_extensions))
      ->set('max_size_mb', (int) $form_state->getValue(['global', 'max_size_mb']))
      ->set('consumers.forum', [
        'inherit_global' => (bool) $form_state->getValue(['forum', 'inherit_global']),
        'allowed_extensions' => implode(' ', $forum_extensions),
        'max_size_mb' => (int) $form_state->getValue(['forum', 'max_size_mb']),
      ])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
