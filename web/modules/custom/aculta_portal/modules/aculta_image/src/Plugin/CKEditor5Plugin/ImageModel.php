<?php

declare(strict_types=1);

namespace Drupal\aculta_image\Plugin\CKEditor5Plugin;

use Drupal\aculta_image\ImageCatalog;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * Modelo de imagem no CKEditor 5 (aculta_image, código próprio).
 *
 * A configuração escolhe quais modelos aparecem no seletor de cada editor (ex.: o formato do usuário comum oferece só
 * Pequena e Padrão). O JavaScript lê a lista em editor.config "acultaImageModel.models".
 */
class ImageModel extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  public function defaultConfiguration(): array {
    return ['modelos' => array_keys(ImageCatalog::MODELOS)];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $opcoes = [
      'pequena' => $this->t('Pequena'),
      'padrao' => $this->t('Padrão'),
      'larga' => $this->t('Larga'),
      'destaque' => $this->t('Destaque'),
    ];
    $form['modelos'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Modelos disponíveis no seletor'),
      '#options' => $opcoes,
      '#default_value' => $this->configuration['modelos'],
      '#description' => $this->t('Modelos oferecidos a quem edita o texto. Os tamanhos ficam no catálogo do aculta_image.'),
    ];
    return $form;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $escolhidos = array_filter((array) $form_state->getValue('modelos'));
    if ($escolhidos === []) {
      $form_state->setErrorByName('modelos', $this->t('Escolha ao menos um modelo.'));
    }
  }

  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $escolhidos = array_values(array_filter((array) $form_state->getValue('modelos')));
    $this->configuration['modelos'] = array_values(array_intersect(array_keys(ImageCatalog::MODELOS), $escolhidos));
  }

  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config['acultaImageModel'] = ['models' => $this->configuration['modelos']];
    return $static_plugin_config;
  }

}
