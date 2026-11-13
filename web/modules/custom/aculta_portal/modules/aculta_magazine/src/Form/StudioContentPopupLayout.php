<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Form;

/**
 * Layout do popup de criação de conteúdo (Compositor de Criação de Conteúdo, Revista).
 *
 * Usado pelo popup "Nova publicação" do Marley e pela story do SDC na UI Library.
 */
final class StudioContentPopupLayout {

  /**
   * Campos de administração do nó que saem do popup (menu, URL, promoção, autoria, metadados).
   */
  private const REMOVE = [
    'menu', 'path', 'promote', 'sticky', 'created', 'uid', 'url_redirects',
    'simple_sitemap', 'revision_log', 'field_meta_tags', 'langcode',
  ];

  /**
   * Aplica o layout do popup ao formulário de revista.
   */
  public static function apply(array &$form): void {
    foreach (self::REMOVE as $key) {
      unset($form[$key]);
    }
    // Sem pré-visualização. "Cancelar" (secundário) fecha o popup.
    unset($form['actions']['preview']);
    $form['actions']['cancel'] = [
      '#type' => 'component',
      '#component' => 'aculta420_v2:button',
      '#props' => [
        'text' => (string) t('Cancelar'),
        'variant' => 'secondary',
        'attributes' => new \Drupal\Core\Template\Attribute(['data-aculta-dialog-close' => '']),
      ],
      '#weight' => 50,
    ];
  }

}
