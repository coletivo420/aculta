<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines global defaults for document viewers in ACULTA administration.
 */
final class PdfViewerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_document_viewer_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['aculta_document_viewer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['scope'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Estes são os padrões globais de PDF, DOCX e planilhas. A autorização do arquivo no Drupal continua prevalecendo para leitura e download.'),
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
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
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
        '100' => $this->t('100%'),
        '125' => $this->t('125%'),
      ],
      '#config_target' => 'aculta_document_viewer.settings:default_zoom',
    ];
    $form['defaults']['default_scroll'] = [
      '#type' => 'select',
      '#title' => $this->t('Rolagem padrão'),
      '#options' => ['vertical' => $this->t('Vertical'), 'horizontal' => $this->t('Horizontal')],
      '#config_target' => 'aculta_document_viewer.settings:default_scroll',
      '#description' => $this->t('Aplicada pelo viewer completo aberto nos links de impressão e PDF.js. O canvas incorporado mantém navegação paginada.'),
    ];
    $form['defaults']['double_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Solicitar modo de duas páginas'),
      '#config_target' => 'aculta_document_viewer.settings:double_page',
      '#description' => $this->t('Aplicada pelo viewer completo em telas largas. Em telas móveis, página dupla é desligada por padrão.'),
    ];
    $form['defaults']['double_page_mobile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Manter duas páginas em telas móveis'),
      '#config_target' => 'aculta_document_viewer.settings:double_page_mobile',
      '#description' => $this->t('Quando marcada, mantém página dupla no viewer completo em telas móveis. O canvas incorporado mantém página única.'),
    ];
    $form['defaults']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Idioma da interface'),
      '#options' => ['pt-BR' => $this->t('Português do Brasil')],
      '#config_target' => 'aculta_document_viewer.settings:language',
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
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
      ];
    }
    $form['link']['link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Abrir link em'),
      '#options' => ['_blank' => $this->t('Nova aba'), '_self' => $this->t('Mesma aba')],
      '#config_target' => 'aculta_document_viewer.settings:link_target',
    ];

    $form['docx'] = [
      '#type' => 'details',
      '#title' => $this->t('Apresentação de documentos DOCX'),
      '#open' => TRUE,
      '#description' => $this->t('As opções só afetam a prévia no navegador. O arquivo original e o link autorizado para download permanecem disponíveis.'),
    ];
    foreach ([
      'docx_render_headers' => $this->t('Exibir cabeçalhos'),
      'docx_render_footers' => $this->t('Exibir rodapés'),
      'docx_render_footnotes' => $this->t('Exibir notas de rodapé'),
      'docx_render_endnotes' => $this->t('Exibir notas de fim'),
      'docx_respect_page_breaks' => $this->t('Respeitar quebras de página gravadas no documento'),
    ] as $key => $label) {
      $form['docx'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
      ];
    }

    $form['spreadsheet'] = [
      '#type' => 'details',
      '#title' => $this->t('Apresentação de planilhas'),
      '#open' => TRUE,
      '#description' => $this->t('As planilhas continuam somente para leitura. Os limites máximos de abas, linhas, colunas e texto de célula não podem ser aumentados pelo painel.'),
    ];
    $form['spreadsheet']['spreadsheet_default_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Modo inicial'),
      '#options' => [
        'sheet' => $this->t('Planilha com coordenadas de células'),
        'table' => $this->t('Tabela com filtros e paginação'),
      ],
      '#config_target' => 'aculta_document_viewer.settings:spreadsheet_default_mode',
    ];
    $form['spreadsheet']['spreadsheet_page_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Linhas por página no modo tabela'),
      '#options' => [25 => 25, 50 => 50, 100 => 100],
      '#config_target' => 'aculta_document_viewer.settings:spreadsheet_page_size',
    ];

    return parent::buildForm($form, $form_state);
  }

}
