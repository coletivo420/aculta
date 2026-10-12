<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Template\Attribute;
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
      '#theme' => 'aculta_portal_search_trigger',
      '#label' => $this->t('Buscar no site'),
      '#url' => Url::fromRoute('aculta_portal.search_popup')->toString(),
      // Atributos do gatilho: use-ajax (comportamento do Drupal) e o token de design do tema.
      '#trigger_attributes' => new Attribute([
        'class' => ['use-ajax'], 'data-portal-ui' => 'search-utility',
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 520,
          'maxHeight' => 560,
          // Sem 'of': opções chegam como JSON e o jQuery UI usa window como padrão.
          'position' => ['my' => 'center top+24', 'at' => 'center top'],
          'closeText' => (string) $this->t('Fechar'),
        ]),
        'title' => (string) $this->t('Buscar no site'),
      ]),
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      '#cache' => ['contexts' => ['domain', 'url.path']],
    ];
  }

}
