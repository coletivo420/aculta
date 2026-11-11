<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search;

use Drupal\Core\Template\Attribute;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

/**
 * Ícone de busca da barra multidomínio. Abre o popup da busca no host atual.
 *
 * O trigger mantém o contrato Drupal AJAX (modal do Core) como fallback da V1;
 * no ACULTA420 V2 o tema substitui esses atributos por HTMX antes da interação
 * (ADR-050, F1). O Portal não depende do tema para renderizar o link.
 */
final class SearchUtility {

  use StringTranslationTrait;

  public function __construct(
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Monta o ícone de busca da barra multidomínio.
   *
   * @return array<string, mixed>
   *   Estrutura renderizável do ícone de busca.
   */
  public function build(): array {
    return [
      '#theme' => 'aculta_portal_search_trigger',
      '#label' => $this->t('Buscar no site'),
      '#url' => Url::fromRoute('aculta_portal.search_popup')->toString(),
      // Fallback V1: use-ajax (modal do Core) e o token de design do tema.
      '#trigger_attributes' => new Attribute([
        'class' => ['use-ajax'], 'data-portal-ui' => 'search-utility',
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'closeText' => (string) $this->t('Fechar'),
        ]),
        'title' => (string) $this->t('Buscar no site'),
      ]),
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      '#cache' => ['contexts' => ['domain', 'url.path']],
    ];
  }

}
