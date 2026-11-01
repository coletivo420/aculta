<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prévia da busca (popup): HTML da lista de resultados para o termo, carregado pelo script do Portal depois de
 * uma pausa na digitação. Sem Form API: a rota não depende do objeto do formulário, então não há estado a
 * restaurar no cache de formulários.
 */
final class SearchPreviewController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  private const PREVIEW_LIMIT = 8;

  public function __construct(
    private readonly SearchResults $searchResults,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly RendererInterface $renderer,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.search_results'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('renderer'),
      $container->get('string_translation'),
    );
  }

  public function preview(Request $request): Response {
    $term = mb_substr(trim((string) $request->query->get('q', '')), 0, SearchPageController::MAX_CHARS);
    $html = (string) $this->renderer->renderInIsolation($this->build($term));
    $response = new Response($html);
    $response->headers->set('Cache-Control', 'no-store, private');
    return $response;
  }

  /** @return array<string, mixed> */
  private function build(string $term): array {
    if (mb_strlen($term) < SearchPageController::MIN_CHARS) {
      return [
        '#cache' => ['max-age' => 0],
        'hint' => ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#element_attributes' => new Attribute(['data-portal-ui' => 'hint']), '#value' => $this->t('Digite pelo menos @n caracteres para ver resultados.', ['@n' => SearchPageController::MIN_CHARS])],
      ];
    }
    $preview = $this->searchResults->search($term, self::PREVIEW_LIMIT);
    if ($preview['total'] === 0) {
      return [
        '#cache' => ['max-age' => 0],
        'empty' => ['#markup' => '<p>' . $this->t('Nenhum resultado para «@term».', ['@term' => $term]) . '</p>'],
      ];
    }
    $items = [];
    foreach ($preview['items'] as $result) {
      $items[] = [
        '#type' => 'link',
        '#title' => $this->t('@title (@type)', ['@title' => $result['title'], '@type' => $result['type']]),
        '#url' => $result['url'],
      ];
    }
    // Como na Wikipedia: a última linha leva à pesquisa completa pelo mesmo termo, com o total.
    $full = $this->domainPurposeManager->routeUrl('main', 'aculta_portal.search') ?? Url::fromRoute('aculta_portal.search');
    $full->setOption('query', ['q' => $term]);
    return [
      '#cache' => ['max-age' => 0, 'contexts' => ['domain', 'user.permissions']],
      'list' => ['#theme' => 'item_list', '#items' => $items, '#attributes' => ['data-portal-ui' => 'search-preview-list']],
      'full' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'search-preview-footer'],
        'link' => [
          '#type' => 'link',
          '#title' => $this->t('Pesquisa completa por «@term» (@total resultados)', ['@term' => $term, '@total' => $preview['total']]),
          '#url' => $full,
          '#attributes' => ['data-portal-ui' => 'search-preview-full'],
        ],
      ],
    ];
  }

}
