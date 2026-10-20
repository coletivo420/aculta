<?php

declare(strict_types=1);

namespace Drupal\aculta_pdf_viewer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines global defaults for PDF readers in the ACULTA administration panel.
 */
final class PdfViewerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_pdf_viewer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_pdf_viewer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['scope'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Estes são os padrões globais. Cada formatter de campo pode desativar “Usar padrões do painel” e manter uma configuração própria. A autorização do arquivo no Drupal continua prevalecendo para download e impressão.'),
    ];

    $form['controls'] = [
      '#type' => 'details',
      '#title' => $this->t('Controles padrão'),
      '#open' => TRUE,
    ];
    foreach ([
      'show_download' => $this->t('Exibir link para abrir ou baixar'),
      'show_print' => $this->t('Exibir opção de impressão'),
      'show_zoom' => $this->t('Exibir controles de zoom'),
      'show_rotation' => $this->t('Exibir controle de rotação'),
      'show_outline' => $this->t('Exibir contorno do PDF'),
      'show_page_thumbnails' => $this->t('Exibir miniaturas de páginas'),
      'show_fullscreen' => $this->t('Exibir tela cheia'),
      'show_search' => $this->t('Exibir busca'),
      'show_page_navigation' => $this->t('Exibir navegação de páginas'),
      'show_full_viewer' => $this->t('Exibir link para o leitor completo PDF.js'),
    ] as $key => $label) {
      $form['controls'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#config_target' => 'aculta_pdf_viewer.settings:' . $key,
      ];
    }

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Padrões de leitura'),
      '#open' => TRUE,
    ];
    $form['defaults']['default_zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom inicial'),
      '#options' => [
        'fit-width' => $this->t('Ajustar à largura'),
        '100' => '100%',
        '125' => '125%',
      ],
      '#config_target' => 'aculta_pdf_viewer.settings:default_zoom',
    ];
    $form['defaults']['default_scroll'] = [
      '#type' => 'select',
      '#title' => $this->t('Rolagem padrão'),
      '#options' => ['vertical' => $this->t('Vertical'), 'horizontal' => $this->t('Horizontal')],
      '#config_target' => 'aculta_pdf_viewer.settings:default_scroll',
      '#description' => $this->t('O leitor canvas ainda usa navegação paginada; rolagem contínua será conectada na próxima etapa.'),
    ];
    $form['defaults']['double_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Solicitar modo de duas páginas'),
      '#config_target' => 'aculta_pdf_viewer.settings:double_page',
      '#description' => $this->t('A opção fica registrada como padrão, mas ainda não altera o canvas ACULTA.'),
    ];
    $form['defaults']['double_page_mobile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Manter duas páginas em telas móveis'),
      '#config_target' => 'aculta_pdf_viewer.settings:double_page_mobile',
      '#description' => $this->t('A opção fica registrada como padrão, mas ainda não altera o canvas ACULTA.'),
    ];
    $form['defaults']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Idioma da interface'),
      '#options' => ['pt-BR' => $this->t('Português do Brasil')],
      '#config_target' => 'aculta_pdf_viewer.settings:language',
    ];

    $form['link'] = [
      '#type' => 'details',
      '#title' => $this->t('Padrões do link e embed'),
    ];
    foreach ([
      'embed_title' => [$this->t('Título acessível do leitor'), 255],
      'link_text' => [$this->t('Texto do link para abrir o documento'), 255],
      'css_class' => [$this->t('Classe CSS adicional'), 128],
    ] as $key => [$label, $maxlength]) {
      $form['link'][$key] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#maxlength' => $maxlength,
        '#config_target' => 'aculta_pdf_viewer.settings:' . $key,
      ];
    }
    $form['link']['link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Abrir link em'),
      '#options' => ['_blank' => $this->t('Nova aba'), '_self' => $this->t('Mesma aba')],
      '#config_target' => 'aculta_pdf_viewer.settings:link_target',
    ];

    return parent::buildForm($form, $form_state);
  }

}
