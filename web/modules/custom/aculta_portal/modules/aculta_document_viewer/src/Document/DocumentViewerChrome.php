<?php

declare(strict_types=1);

namespace Drupal\aculta_document_viewer\Document;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Cabeçalho e barra de controles compartilhados pelos leitores DOCX e planilhas.
 *
 * Emite só marcação neutra com os atributos documentados em TOKENS-E-ATRIBUTOS. Cores, espaçamento
 * e foco ficam no tema ACULTA420. Títulos e rótulos são escapados aqui.
 */
final class DocumentViewerChrome {

  /**
   * Monta cabeçalho (tipo e título), barra de controles e área de status.
   *
   * @return array<string, mixed>
   *   Elementos de render para ser incluídos no contêiner do leitor.
   */
  public static function elements(string $title, TranslatableMarkup $type): array {
    return [
      'header' => [
        '#type' => 'container',
        '#attributes' => ['data-aculta-document-part' => 'header'],
        'type' => [
          '#prefix' => '<p data-aculta-document-part="type">',
          '#markup' => Html::escape((string) $type),
          '#suffix' => '</p>',
        ],
        'title' => [
          '#prefix' => '<h3 data-aculta-document-part="title">',
          '#markup' => Html::escape($title),
          '#suffix' => '</h3>',
        ],
      ],
      'toolbar' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'data-aculta-document-part' => 'toolbar',
          'data-aculta-document-control' => 'toolbar',
        ],
      ],
      'status' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => [
          'data-aculta-document-part' => 'status',
          'data-aculta-document-control' => 'status',
          'role' => 'status',
          'aria-live' => 'polite',
        ],
        '#value' => new TranslatableMarkup('Carregando documento…'),
      ],
    ];
  }

}
