<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Form;

use Drupal\aculta_image\ImageCapabilities;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures image processing and reports runtime capabilities.
 */
final class ImageSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config,
    private readonly ImageCapabilities $capabilities,
  ) {
    parent::__construct($config_factory, $typed_config);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('aculta_image.capabilities'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_image_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_image.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('aculta_image.settings');
    $backends = $this->capabilities->backends();
    $backend_options = [];
    foreach ($backends as $id => $formats) {
      $backend_options[$id] = $id === 'imagick' ? $this->t('Imagick (PHP)') : $this->t('GD (PHP)');
    }
    $format_labels = [
      'image/jpeg' => 'JPEG',
      'image/png' => 'PNG',
      'image/gif' => 'GIF',
      'image/webp' => 'WebP',
    ];

    $form['capabilities'] = [
      '#type' => 'details',
      '#title' => $this->t('Bibliotecas e formatos detectados neste servidor'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    if ($backends === []) {
      $form['capabilities']['notice'] = [
        '#markup' => '<p>' . $this->t('Nenhuma biblioteca PHP de processamento de imagens foi detectada.') . '</p>',
      ];
    }
    foreach ($backends as $id => $formats) {
      $supported = array_map(static fn (string $mime): string => $format_labels[$mime], array_keys(array_filter($formats)));
      $form['capabilities'][$id] = [
        '#type' => 'item',
        '#title' => $backend_options[$id],
        '#markup' => $supported === [] ? $this->t('Sem formatos de imagem disponíveis.') : implode(', ', $supported),
      ];
    }
    $optimizer_status = [];
    foreach ($this->capabilities->optimizers() as $name => $available) {
      $optimizer_status[] = $name . ': ' . ($available ? $this->t('disponível') : $this->t('indisponível'));
    }
    $form['capabilities']['optimizers'] = [
      '#type' => 'item',
      '#title' => $this->t('Otimizadores opcionais'),
      '#markup' => implode(', ', $optimizer_status),
    ];

    $form['backend'] = [
      '#type' => 'select',
      '#title' => $this->t('Biblioteca de processamento'),
      '#options' => $backend_options,
      '#default_value' => $config->get('backend') ?? 'imagick',
      '#required' => TRUE,
      '#description' => $this->t('Somente bibliotecas detectadas podem ser escolhidas.'),
    ];
    $form['compression_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ativar compressão e redimensionamento de imagens inline'),
      '#default_value' => (bool) ($config->get('compression_enabled') ?? TRUE),
    ];
    $form['max_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Largura máxima em pixels'),
      '#min' => 320,
      '#max' => 10000,
      '#default_value' => (int) ($config->get('max_width') ?? 1200),
      '#states' => ['visible' => [':input[name="compression_enabled"]' => ['checked' => TRUE]]],
    ];
    $form['supported_mime_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Tipos processados em imagens inline'),
      '#options' => $format_labels,
      '#default_value' => $config->get('supported_mime_types') ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
      '#description' => $this->t('Isso controla o processamento deste módulo; as regras de validação e upload dos campos permanecem separadas. GIF é processado somente com Imagick para preservar animações.'),
    ];
    $form['webp_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Converter e substituir imagens compatíveis por WebP'),
      '#default_value' => (bool) ($config->get('webp_enabled') ?? TRUE),
      '#states' => ['visible' => [':input[name="compression_enabled"]' => ['checked' => TRUE]]],
    ];
    $form['webp_quality'] = [
      '#type' => 'number',
      '#title' => $this->t('Qualidade WebP'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => (int) ($config->get('webp_quality') ?? 82),
      '#description' => $this->t('Compressão com perda; o valor inicial é 82.'),
      '#states' => ['visible' => [':input[name="webp_enabled"]' => ['checked' => TRUE]]],
    ];
    $form['output_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Formato de saída para os demais tipos'),
      '#options' => [
        'original' => $this->t('Manter o formato enviado'),
        'image/jpeg' => $this->t('JPEG'),
        'image/png' => $this->t('PNG'),
      ],
      '#default_value' => $config->get('output_format') ?? 'original',
      '#description' => $this->t('Aplica-se quando WebP estiver desativado. GIF animado nunca é convertido para outro formato; JPEG não preserva transparência.'),
    ];
    $form['jpeg_quality'] = [
      '#type' => 'number',
      '#title' => $this->t('Qualidade JPEG'),
      '#min' => 1,
      '#max' => 100,
      '#default_value' => (int) ($config->get('jpeg_quality') ?? 82),
    ];
    $form['png_compression'] = [
      '#type' => 'number',
      '#title' => $this->t('Nível de compressão PNG'),
      '#min' => 0,
      '#max' => 9,
      '#default_value' => (int) ($config->get('png_compression') ?? 6),
      '#description' => $this->t('Nível sem perda; valores maiores podem exigir mais processamento.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $backend = (string) $form_state->getValue('backend');
    if (!isset($this->capabilities->backends()[$backend])) {
      $form_state->setErrorByName('backend', $this->t('A biblioteca selecionada não está disponível neste servidor.'));
      return;
    }
    if ((bool) $form_state->getValue('webp_enabled') && !$this->capabilities->backends()[$backend]['image/webp']) {
      $form_state->setErrorByName('webp_enabled', $this->t('A biblioteca selecionada não consegue gerar WebP.'));
    }
    foreach (array_filter($form_state->getValue('supported_mime_types') ?? []) as $mime) {
      if (empty($this->capabilities->backends()[$backend][$mime])) {
        $form_state->setErrorByName('supported_mime_types', $this->t('O backend selecionado não suporta o tipo @type.', ['@type' => $mime]));
        break;
      }
    }
    $output_format = (string) $form_state->getValue('output_format');
    if ($output_format !== 'original' && empty($this->capabilities->backends()[$backend][$output_format])) {
      $form_state->setErrorByName('output_format', $this->t('O backend selecionado não consegue gerar o formato escolhido.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('aculta_image.settings')
      ->set('backend', (string) $form_state->getValue('backend'))
      ->set('compression_enabled', (bool) $form_state->getValue('compression_enabled'))
      ->set('max_width', (int) $form_state->getValue('max_width'))
      ->set('supported_mime_types', array_values(array_filter($form_state->getValue('supported_mime_types') ?? [])))
      ->set('webp_enabled', (bool) $form_state->getValue('webp_enabled'))
      ->set('webp_quality', (int) $form_state->getValue('webp_quality'))
      ->set('output_format', (string) $form_state->getValue('output_format'))
      ->set('jpeg_quality', (int) $form_state->getValue('jpeg_quality'))
      ->set('png_compression', (int) $form_state->getValue('png_compression'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
