<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Configures the shared policy for user file uploads across the Portal. */
final class UserUploadSettingsForm extends ConfigFormBase {

  /** Integrations with their own tab; each may inherit the global policy. */
  private const CONSUMERS = ['forum', 'lms', 'wiki'];

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
      '#plain_text' => $this->t('Esta política central é usada pelos formulários de envio de arquivos para usuários no Portal, incluindo o Fórum, as respostas com arquivo do LMS e a imagem de destaque dos verbetes da Wiki. Cada área continua responsável por associar o arquivo ao conteúdo e controlar sua publicação e acesso.'),
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
    foreach (self::CONSUMERS as $consumer) {
      $settings = $config->get('consumers.' . $consumer) ?? [];
      $label = $this->consumerLabel($consumer);
      $form[$consumer] = [
        '#type' => 'details',
        '#title' => $label,
        '#group' => 'policy_tabs',
      ];
      $form[$consumer]['inherit_global'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Usar a política global'),
        '#default_value' => $settings['inherit_global'] ?? TRUE,
      ];
      $form[$consumer]['allowed_extensions'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Extensões permitidas em @consumer', ['@consumer' => $label]),
        '#default_value' => $settings['allowed_extensions'] ?? UserUploadPolicy::DEFAULT_EXTENSIONS,
        '#maxlength' => 255,
        '#states' => ['visible' => [':input[name="' . $consumer . '[inherit_global]"]' => ['checked' => FALSE]]],
      ];
      $form[$consumer]['max_size_mb'] = [
        '#type' => 'number',
        '#title' => $this->t('Tamanho máximo por arquivo em @consumer (MB)', ['@consumer' => $label]),
        '#default_value' => $settings['max_size_mb'] ?? 10,
        '#min' => 1,
        '#max' => intdiv(PHP_INT_MAX, 1048576),
        '#step' => 1,
        '#states' => ['visible' => [':input[name="' . $consumer . '[inherit_global]"]' => ['checked' => FALSE]]],
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $scopes = ['global' => 'Política global'];
    foreach (self::CONSUMERS as $consumer) {
      $scopes[$consumer] = (string) $this->consumerLabel($consumer);
    }
    foreach ($scopes as $scope => $label) {
      if ($scope !== 'global' && $form_state->getValue([$scope, 'inherit_global'])) {
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
    $config = $this->config('aculta_portal.user_upload')
      ->set('allowed_extensions', implode(' ', $global_extensions))
      ->set('max_size_mb', (int) $form_state->getValue(['global', 'max_size_mb']));
    foreach (self::CONSUMERS as $consumer) {
      $extensions = preg_split('/\s+/', strtolower(trim((string) $form_state->getValue([$consumer, 'allowed_extensions'])))) ?: [];
      $extensions = array_values(array_unique(array_filter($extensions)));
      $config->set('consumers.' . $consumer, [
        'inherit_global' => (bool) $form_state->getValue([$consumer, 'inherit_global']),
        'allowed_extensions' => implode(' ', $extensions),
        'max_size_mb' => (int) $form_state->getValue([$consumer, 'max_size_mb']),
      ]);
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /** Returns the administrative label of a consumer tab. */
  private function consumerLabel(string $consumer): string|\Stringable {
    return match ($consumer) {
      'forum' => $this->t('Fórum'),
      'lms' => $this->t('LMS'),
      'wiki' => $this->t('Wiki'),
    };
  }

}
