<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Search;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\aculta_portal\Search\Form\SearchPageForm;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Busca pública do site (/busca), pelo índice Search API no banco (F3).
 *
 * O índice só contém os tipos de conteúdo públicos e o resultado passa pela
 * checagem de acesso de cada nó antes de ser mostrado.
 */
final class SearchPageController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public const INDEX_ID = 'aculta_conteudo';

  public const MIN_CHARS = 3;

  public const MAX_CHARS = 100;

  public const LIMIT = 20;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly DomainSiteManager $siteManager,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('aculta_portal.domain_site'),
      $container->get('form_builder'),
    );
  }

  /**
   * Monta a página pública de busca.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Requisição com o termo de busca no parâmetro query `q`.
   *
   * @return array
   *   Array de renderização da busca e dos resultados.
   */
  public function page(Request $request): array {
    $term = mb_substr(trim((string) $request->query->get('q', '')), 0, self::MAX_CHARS);
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'search'],
      '#cache' => [
        'contexts' => ['url.query_args:q', 'user.permissions'],
        'tags' => ['search_api_list:' . self::INDEX_ID],
      ],
      'form' => $this->formBuilder->getForm(SearchPageForm::class),
    ];
    if ($term === '') {
      $build['hint'] = [
        '#type' => 'component',
        '#component' => 'aculta420_v2:hint',
        '#props' => ['tone' => 'muted'],
        '#slots' => ['content' => ['#plain_text' => $this->t('Digite um termo para buscar no site.')]],
      ];
      return $build;
    }
    if (mb_strlen($term) < self::MIN_CHARS) {
      $this->messenger->addWarning($this->t('Digite pelo menos @n caracteres para buscar.', ['@n' => self::MIN_CHARS]));
      return $build;
    }

    $index = $this->entityTypeManager->getStorage('search_api_index')->load(self::INDEX_ID);
    if ($index === NULL) {
      $this->messenger->addError($this->t('A busca está indisponível no momento.'));
      return $build;
    }

    $query = $index->query(['limit' => self::LIMIT, 'offset' => 0]);
    $query->addCondition('status', TRUE);
    $query->keys($term);
    $results = $query->execute();

    $links = [];
    $cache = CacheableMetadata::createFromRenderArray($build);
    foreach ($results->getResultItems() as $item) {
      $node = $item->getOriginalObject()?->getValue();
      // Acesso de nó é checado item a item: o índice não substitui o controle de acesso.
      if (!$node instanceof NodeInterface || !$node->access('view')) {
        continue;
      }
      $cache->addCacheableDependency($node);
      $links[] = ['#type' => 'link', '#title' => $node->label(), '#url' => $this->resultUrl($node)];
    }
    $cache->applyTo($build);

    if ($links === []) {
      $this->messenger->addWarning($this->t('Nenhum resultado para «@term».', ['@term' => $term]));
      return $build;
    }
    $build['results'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'search-results'],
      'count' => ['#markup' => '<p>' . $this->t('@count resultado(s).', ['@count' => count($links)]) . '</p>'],
      'list' => ['#theme' => 'item_list', '#items' => $links],
    ];
    return $build;
  }

  /**
   * Sugestões do campo de busca (Core autocomplete), com o mesmo índice e o mesmo controle de acesso.
   */
  public function suggestions(Request $request): JsonResponse {
    $term = mb_substr(trim((string) $request->query->get('q', '')), 0, self::MAX_CHARS);
    if (mb_strlen($term) < self::MIN_CHARS) {
      return new JsonResponse([]);
    }
    $index = $this->entityTypeManager->getStorage('search_api_index')->load(self::INDEX_ID);
    if ($index === NULL) {
      return new JsonResponse([]);
    }
    $query = $index->query(['limit' => self::LIMIT, 'offset' => 0]);
    $query->addCondition('status', TRUE);
    $query->keys($term);
    $suggestions = [];
    foreach ($query->execute()->getResultItems() as $item) {
      $node = $item->getOriginalObject()?->getValue();
      if (!$node instanceof NodeInterface || !$node->access('view')) {
        continue;
      }
      $suggestions[] = ['value' => $node->label(), 'label' => $node->label()];
    }
    return new JsonResponse($suggestions);
  }

  /**
   * Retorna o link do resultado no host do conteúdo.
   *
   * A busca é centralizada no MAIN, mas cada item abre no purpose ao qual
   * pertence (field_domain_source). Sem purpose conhecido, usa o caminho do
   * próprio host.
   */
  private function resultUrl(NodeInterface $node): Url {
    $relative = $node->toUrl();
    $domainId = $node->hasField('field_domain_source') ? $node->get('field_domain_source')->target_id : NULL;
    $purpose = is_string($domainId) ? $this->siteManager->getSiteKeyValueForDomainId($domainId) : NULL;
    $absolute = $purpose !== NULL ? $this->siteManager->canonicalPathUrl($purpose, $relative->toString()) : NULL;
    return $absolute ?? $relative;
  }

}
