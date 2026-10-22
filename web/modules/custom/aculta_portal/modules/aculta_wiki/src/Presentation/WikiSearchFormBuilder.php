<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Presentation;

use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Presentation\WikiSearchFormBuilderInterface;
use Drupal\aculta_portal\Search\SearchPageController;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the shared GET search form presented throughout Wiki420.
 */
final class WikiSearchFormBuilder implements WikiSearchFormBuilderInterface {

  public function __construct(
    private readonly TranslationInterface $translation,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Returns a render array for the canonical Wiki search route.
   *
   * The current query value is repeated in the shared header on search results.
   */
  public function build(): array {
    // InputBag::get() throws on non-scalar values; read the raw bag so that
    // malformed queries (ex.: `q[]=x`) only leave the field empty.
    $term = $this->requestStack->getCurrentRequest()?->query->all()['q'] ?? '';
    $term = is_string($term) ? mb_substr(trim($term), 0, 100) : '';

    return [
      '#type' => 'form',
      '#method' => 'get',
      '#action' => Url::fromRoute('aculta_portal.wiki_search')->toString(),
      '#attributes' => ['role' => 'search'],
      '#cache' => ['contexts' => ['url.query_args:q']],
      '#attached' => ['library' => ['aculta_portal/search-preview']],
      'q' => [
        '#type' => 'search',
        '#name' => 'q',
        '#title' => (string) $this->translation->translate('Pesquisar na Wiki420'),
        '#title_display' => 'invisible',
        // This is a plain render element, not a Form API build array.
        '#value' => $term,
        '#placeholder' => (string) $this->translation->translate('Pesquisar na Wiki420'),
        '#size' => 40,
        '#maxlength' => SearchPageController::MAX_CHARS,
        '#required' => TRUE,
        '#attributes' => [
          'autocomplete' => 'off',
          'data-portal-ui' => 'search-preview-input',
          'data-preview-url' => Url::fromRoute('aculta_portal.wiki_search_preview')->toString(),
          'data-preview-target' => 'aculta-wiki-search-preview',
          'data-min-chars' => (string) SearchPageController::MIN_CHARS,
        ],
      ],
      'preview' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'aculta-wiki-search-preview',
          'data-portal-ui' => 'search-preview',
          'aria-live' => 'polite',
          'hidden' => TRUE,
        ],
        'hint' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'p',
          '#element_attributes' => ['data-portal-ui' => 'hint'],
          '#value' => (string) $this->translation->translate('Digite pelo menos @n caracteres para ver resultados.', ['@n' => SearchPageController::MIN_CHARS]),
        ],
      ],
      'submit' => [
        '#type' => 'submit',
        '#value' => (string) $this->translation->translate('Buscar'),
      ],
    ];
  }

}
