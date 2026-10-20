<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides native Drupal configuration for the ACULTA PDF reader.
 */
trait PdfViewerFormatterSettingsTrait {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'show_download' => TRUE,
      'show_print' => TRUE,
      'show_zoom' => TRUE,
      'show_rotation' => TRUE,
      'show_outline' => TRUE,
      'show_page_thumbnails' => TRUE,
      'show_fullscreen' => TRUE,
      'show_search' => TRUE,
      'show_page_navigation' => TRUE,
      'show_full_viewer' => TRUE,
      'default_zoom' => 'fit-width',
      'default_scroll' => 'vertical',
      'double_page' => FALSE,
      'double_page_mobile' => FALSE,
      'language' => 'pt-BR',
      'embed_title' => '',
      'link_text' => '',
      'link_target' => '_blank',
      'css_class' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    foreach ([
      'show_download' => $this->t('Exibir link para abrir ou baixar (sujeito à permissão do arquivo)'),
      'show_print' => $this->t('Exibir opção de impressão no leitor completo'),
      'show_zoom' => $this->t('Exibir controles de zoom'),
      'show_rotation' => $this->t('Exibir controle de rotação'),
      'show_outline' => $this->t('Exibir contorno do PDF quando disponível'),
      'show_page_thumbnails' => $this->t('Exibir miniaturas de páginas sob demanda'),
      'show_fullscreen' => $this->t('Exibir controle de tela cheia'),
      'show_search' => $this->t('Exibir busca no texto'),
      'show_page_navigation' => $this->t('Exibir navegação entre páginas'),
      'show_full_viewer' => $this->t('Exibir link para o leitor completo PDF.js'),
      'double_page' => $this->t('Solicitar modo de página dupla no leitor completo'),
      'double_page_mobile' => $this->t('Manter página dupla em telas móveis'),
    ] as $key => $title) {
      $form[$key] = [
        '#type' => 'checkbox',
        '#title' => $title,
        '#default_value' => (bool) $this->getSetting($key),
      ];
    }
    $form['default_zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom inicial'),
      '#options' => [
        'fit-width' => $this->t('Ajustar à largura'),
        '100' => '100%',
        '125' => '125%',
      ],
      '#default_value' => $this->getSetting('default_zoom'),
    ];
    $form['default_scroll'] = [
      '#type' => 'select',
      '#title' => $this->t('Rolagem inicial do leitor completo'),
      '#options' => ['vertical' => $this->t('Vertical'), 'horizontal' => $this->t('Horizontal')],
      '#default_value' => $this->getSetting('default_scroll'),
    ];
    $form['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Idioma da interface'),
      '#options' => ['pt-BR' => $this->t('Português do Brasil')],
      '#default_value' => 'pt-BR',
      '#description' => $this->t('Este é o único idioma instalado no leitor neste momento.'),
    ];
    foreach ([
      'embed_title' => $this->t('Título acessível do leitor'),
      'link_text' => $this->t('Texto do link para abrir o documento'),
      'css_class' => $this->t('Classe CSS adicional'),
    ] as $key => $title) {
      $form[$key] = [
        '#type' => 'textfield',
        '#title' => $title,
        '#default_value' => $this->getSetting($key),
        '#maxlength' => $key === 'css_class' ? 128 : 255,
      ];
    }
    $form['link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Destino do link'),
      '#options' => ['_blank' => $this->t('Nova aba'), '_self' => $this->t('Mesma aba')],
      '#default_value' => $this->getSetting('link_target'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $enabled = [];
    foreach (['show_download', 'show_print', 'show_zoom', 'show_fullscreen', 'show_search', 'show_page_navigation', 'show_full_viewer'] as $key) {
      if ($this->getSetting($key)) {
        $enabled[] = $key;
      }
    }
    return [$this->t('Controles ativos: @count; zoom inicial: @zoom; rolagem: @scroll.', [
      '@count' => count($enabled),
      '@zoom' => $this->getSetting('default_zoom'),
      '@scroll' => $this->getSetting('default_scroll'),
    ])];
  }

  /**
   * Returns the sanitized display configuration passed to the viewer builder.
   */
  private function getViewerSettings(): array {
    $settings = $this->getSettings();
    $settings['embed_title'] = trim((string) ($settings['embed_title'] ?? ''));
    $settings['link_text'] = trim((string) ($settings['link_text'] ?? ''));
    $settings['css_class'] = preg_replace('/[^a-zA-Z0-9_ -]/', '', (string) ($settings['css_class'] ?? ''));
    return $settings;
  }

}
