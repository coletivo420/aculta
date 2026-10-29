<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines global defaults for document viewers in ACULTA administration.
 */
final class DocumentViewerSettingsForm extends ConfigFormBase {

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
  public function buildForm(array $form, FormStateInterface $form_state, string $section = 'aculta_document_viewer_general'): array {
    $form['scope'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('Estes são os padrões globais de PDF, documentos DOCX e planilhas. A autorização do arquivo no Drupal continua prevalecendo para leitura e download.'),
    ];

    if ($section === 'aculta_document_viewer_general' || $section === 'all') {
      $this->buildGeneralSection($form);
    }
    if ($section === 'aculta_document_viewer_pdf' || $section === 'all') {
      $this->buildPdfSection($form);
    }
    if ($section === 'aculta_document_viewer_doc' || $section === 'all') {
      $this->buildDocSection($form);
    }
    if ($section === 'aculta_document_viewer_sheet' || $section === 'all') {
      $this->buildSheetSection($form);
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the shared module settings section (aculta_document_viewer_general).
   *
   * @param array<string, mixed> $form
   *   The form structure.
   */
  private function buildGeneralSection(array &$form): void {
    $form['aculta_document_viewer_general'] = [
      '#type' => 'container',
      '#tree' => FALSE,
    ];
    $form['aculta_document_viewer_general']['controls'] = [
      '#type' => 'details',
      '#title' => $this->t('Controles compartilhados dos leitores'),
      '#open' => TRUE,
      '#description' => $this->t('Opções conjuntas do módulo aplicáveis aos leitores de documentos.'),
    ];
    foreach ([
      'show_download' => $this->t('Exibir link para abrir ou baixar'),
      'show_zoom' => $this->t('Exibir controles de zoom'),
      'show_fullscreen' => $this->t('Exibir tela cheia'),
    ] as $key => $label) {
      $form['aculta_document_viewer_general']['controls'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
      ];
    }
    $form['aculta_document_viewer_general']['controls']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Idioma da interface'),
      '#options' => ['pt-BR' => $this->t('Português do Brasil')],
      '#config_target' => 'aculta_document_viewer.settings:language',
    ];

    $form['aculta_document_viewer_general']['link'] = [
      '#type' => 'details',
      '#title' => $this->t('Padrões do link e embed'),
      '#open' => TRUE,
    ];
    foreach ([
      'embed_title' => [$this->t('Título acessível do leitor'), 255],
      'link_text' => [$this->t('Texto do link para abrir o documento'), 255],
      'css_class' => [$this->t('Classe CSS adicional'), 128],
    ] as $key => [$label, $maxlength]) {
      $form['aculta_document_viewer_general']['link'][$key] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#maxlength' => $maxlength,
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
      ];
    }
    $form['aculta_document_viewer_general']['link']['link_target'] = [
      '#type' => 'select',
      '#title' => $this->t('Abrir link em'),
      '#options' => ['_blank' => $this->t('Nova aba'), '_self' => $this->t('Mesma aba')],
      '#config_target' => 'aculta_document_viewer.settings:link_target',
    ];
  }

  /**
   * Builds the PDF reader settings section (aculta_document_viewer_pdf).
   *
   * @param array<string, mixed> $form
   *   The form structure.
   */
  private function buildPdfSection(array &$form): void {
    $form['aculta_document_viewer_pdf'] = [
      '#type' => 'container',
      '#tree' => FALSE,
    ];
    $form['aculta_document_viewer_pdf']['controls'] = [
      '#type' => 'details',
      '#title' => $this->t('Controles específicos do leitor PDF'),
      '#open' => TRUE,
    ];
    foreach ([
      'show_print' => $this->t('Exibir opção de impressão'),
      'show_rotation' => $this->t('Exibir controle de rotação'),
      'show_outline' => $this->t('Exibir contorno do PDF'),
      'show_page_thumbnails' => $this->t('Exibir miniaturas de páginas'),
      'show_search' => $this->t('Exibir busca'),
      'show_page_navigation' => $this->t('Exibir navegação de páginas'),
      'show_full_viewer' => $this->t('Exibir link para o leitor completo PDF.js'),
    ] as $key => $label) {
      $form['aculta_document_viewer_pdf']['controls'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
      ];
    }

    $form['aculta_document_viewer_pdf']['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Padrões de leitura PDF'),
      '#open' => TRUE,
    ];
    $form['aculta_document_viewer_pdf']['defaults']['default_zoom'] = [
      '#type' => 'select',
      '#title' => $this->t('Zoom inicial'),
      '#options' => [
        'fit-width' => $this->t('Ajustar à largura'),
        '100' => $this->t('100%'),
        '125' => $this->t('125%'),
      ],
      '#config_target' => 'aculta_document_viewer.settings:default_zoom',
    ];
    $form['aculta_document_viewer_pdf']['defaults']['default_scroll'] = [
      '#type' => 'select',
      '#title' => $this->t('Rolagem padrão'),
      '#options' => ['vertical' => $this->t('Vertical'), 'horizontal' => $this->t('Horizontal')],
      '#config_target' => 'aculta_document_viewer.settings:default_scroll',
      '#description' => $this->t('Aplicada pelo viewer completo aberto nos links de impressão e PDF.js. O canvas incorporado mantém navegação paginada.'),
    ];
    $form['aculta_document_viewer_pdf']['defaults']['double_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Solicitar modo de duas páginas'),
      '#config_target' => 'aculta_document_viewer.settings:double_page',
      '#description' => $this->t('Aplicada pelo viewer completo em telas largas. Em telas móveis, página dupla é desligada por padrão.'),
    ];
    $form['aculta_document_viewer_pdf']['defaults']['double_page_mobile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Manter duas páginas em telas móveis'),
      '#config_target' => 'aculta_document_viewer.settings:double_page_mobile',
      '#description' => $this->t('Quando marcada, mantém página dupla no viewer completo em telas móveis. O canvas incorporado mantém página única.'),
    ];
  }

  /**
   * Builds the DOCX documents reader section (aculta_document_viewer_doc).
   *
   * @param array<string, mixed> $form
   *   The form structure.
   */
  private function buildDocSection(array &$form): void {
    $form['aculta_document_viewer_doc'] = [
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
      $form['aculta_document_viewer_doc'][$key] = [
        '#type' => 'checkbox',
        '#title' => $label,
        '#config_target' => 'aculta_document_viewer.settings:' . $key,
      ];
    }
  }

  /**
   * Builds the spreadsheet reader section (aculta_document_viewer_sheet).
   *
   * @param array<string, mixed> $form
   *   The form structure.
   */
  private function buildSheetSection(array &$form): void {
    $form['aculta_document_viewer_sheet'] = [
      '#type' => 'details',
      '#title' => $this->t('Apresentação de planilhas'),
      '#open' => TRUE,
      '#description' => $this->t('As planilhas continuam somente para leitura. Os limites máximos de abas, linhas, colunas e texto de célula não podem ser aumentados pelo painel.'),
    ];
    $form['aculta_document_viewer_sheet']['spreadsheet_default_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Modo inicial'),
      '#options' => [
        'sheet' => $this->t('Planilha com coordenadas de células'),
        'table' => $this->t('Tabela com filtros e paginação'),
      ],
      '#config_target' => 'aculta_document_viewer.settings:spreadsheet_default_mode',
    ];
    $form['aculta_document_viewer_sheet']['spreadsheet_page_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Linhas por página no modo tabela'),
      '#options' => [25 => 25, 50 => 50, 100 => 100],
      '#config_target' => 'aculta_document_viewer.settings:spreadsheet_page_size',
    ];
  }

}
