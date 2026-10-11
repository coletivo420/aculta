<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

/**
 * Ícone de busca da barra multidomínio. Abre o popup da busca por AJAX (modal do Core), no host atual.
 */
final class SearchUtility {

  use StringTranslationTrait;

  public function __construct(
    private readonly DomainPurposeManager $domainPurposeManager,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /** @return array<string, mixed> */
  public function build(): array {
    return [
      '#type' => 'link',
      '#title' => Markup::create('<i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">' . $this->t('Buscar no site') . '</span>'),
      '#url' => Url::fromRoute('aculta_portal.search_popup'),
      '#attributes' => [
        'class' => ['aculta-search-utility', 'use-ajax', 'btn', 'btn-link', 'p-0'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 520,
          'maxHeight' => 560,
          'position' => ['my' => 'center top+24', 'at' => 'center top', 'of' => 'window'],
          'closeText' => (string) $this->t('Fechar'),
        ]),
        'title' => (string) $this->t('Buscar no site'),
      ],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      '#cache' => ['contexts' => ['domain', 'url.path']],
    ];
  }

}
