<?php

declare(strict_types=1);

namespace Drupal\aculta_index\Plugin\Block;

use Drupal\aculta_index\Query\ContentIndexRelationQuery;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delivers related content cards via ContentIndexRelationQuery (item I-02).
 *
 * Consults the active Content Index catalog without performing direct queries
 * to origin tables. Respects entity and purpose access and fails closed to an
 * empty render array when no relations are available or authorized.
 */
#[Block(
  id: 'aculta_index_related_content',
  admin_label: new TranslatableMarkup('Conteúdo relacionado (Content Index)'),
  category: new TranslatableMarkup('ACULTA Index'),
  context_definitions: [
    'entity' => new ContextDefinition(
      'entity',
      label: new TranslatableMarkup('Entidade de origem'),
      required: FALSE,
    ),
  ],
)]
final class RelatedContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a RelatedContentBlock.
   *
   * @param array<string, mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\aculta_index\Query\ContentIndexRelationQuery $relationQuery
   *   Content Index relation query service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Current user account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current route match.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ContentIndexRelationQuery $relationQuery,
    private readonly AccountInterface $currentUser,
    private readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_index.relation_query'),
      $container->get('current_user'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'relation_types' => '',
      'limit' => 5,
      'block_title' => 'Conteúdo relacionado',
      'show_summary' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['block_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título do bloco'),
      '#default_value' => $this->configuration['block_title'],
      '#description' => $this->t('Título exibido no topo da lista.'),
    ];

    $form['relation_types'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tipos de relação'),
      '#default_value' => $this->configuration['relation_types'],
      '#description' => $this->t(
        'Nomes de relação separados por vírgula. Deixe em branco para detecção automática.',
      ),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limite de itens'),
      '#default_value' => $this->configuration['limit'],
      '#min' => 1,
      '#max' => 25,
      '#description' => $this->t('Número máximo de itens relacionados exibidos.'),
      '#required' => TRUE,
    ];

    $form['show_summary'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exibir resumo'),
      '#default_value' => !empty($this->configuration['show_summary']),
      '#description' => $this->t(
        'Exibe resumo ou descrição curta do item quando disponível.',
      ),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['block_title'] = trim((string) $form_state->getValue('block_title'));
    $this->configuration['relation_types'] = trim((string) $form_state->getValue('relation_types'));
    $this->configuration['limit'] = max(1, min(25, (int) $form_state->getValue('limit')));
    $this->configuration['show_summary'] = !empty($form_state->getValue('show_summary'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $sourceEntity = $this->getSourceEntity();
    if ($sourceEntity === NULL) {
      return [];
    }

    $sourceEntityType = $sourceEntity->getEntityTypeId();
    $sourceEntityId = (string) $sourceEntity->id();
    $sourceLangcode = $sourceEntity->language()->getId() ?: 'und';

    $relationTypes = $this->resolveRelationTypes($sourceEntity);
    if ($relationTypes === []) {
      return [];
    }

    $limit = (int) ($this->configuration['limit'] ?? 5);
    $limit = max(1, min(25, $limit));

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($sourceEntity);
    $cacheability->addCacheContexts([
      'languages:language_interface',
      'user.permissions',
    ]);

    /** @var array<string, \Drupal\Core\Entity\ContentEntityInterface> $collected */
    $collected = [];

    foreach ($relationTypes as $relationType) {
      $remaining = $limit - count($collected);
      if ($remaining <= 0) {
        break;
      }

      try {
        $result = $this->relationQuery->queryOutgoing(
          $sourceEntityType,
          $sourceEntityId,
          $sourceLangcode,
          $relationType,
          $this->currentUser,
          NULL,
          $remaining,
        );
        $cacheability = $cacheability->merge($result->cacheability);
        foreach ($result->entities as $targetEntity) {
          if ($targetEntity instanceof ContentEntityInterface) {
            $key = $targetEntity->getEntityTypeId() . ':' . $targetEntity->id();
            if (!isset($collected[$key])) {
              $collected[$key] = $targetEntity;
            }
          }
        }
      }
      catch (\Throwable) {
        // Fails closed on query errors.
      }
    }

    if ($collected === []) {
      $empty = [];
      $cacheability->applyTo($empty);
      return $empty;
    }

    $items = [];
    foreach ($collected as $target) {
      $cacheability->addCacheableDependency($target);
      $items[] = $this->buildItemRenderArray($target);
    }

    $title = (string) ($this->configuration['block_title'] ?? '');
    $heading = $title !== '' ? $title : (string) $this->t('Conteúdo relacionado');

    $build = [
      '#theme' => 'item_list',
      '#title' => $heading,
      '#items' => $items,
      '#attributes' => [
        'data-portal-ui' => 'related-content-block',
      ],
    ];

    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Resolves the source content entity from context or current route.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The resolved entity or NULL when unavailable.
   */
  private function getSourceEntity(): ?ContentEntityInterface {
    try {
      $contextEntity = $this->getContextValue('entity');
      if ($contextEntity instanceof ContentEntityInterface) {
        return $contextEntity;
      }
    }
    catch (\Throwable) {
      // Fall through to route match inspection.
    }

    $candidates = [
      'aculta_event',
      'aculta_event_location',
      'aculta_event_organization',
      'node',
      'group',
    ];
    foreach ($candidates as $paramName) {
      $candidate = $this->routeMatch->getParameter($paramName);
      if ($candidate instanceof ContentEntityInterface) {
        return $candidate;
      }
    }

    foreach ($this->routeMatch->getParameters() as $parameter) {
      if ($parameter instanceof ContentEntityInterface) {
        return $parameter;
      }
    }

    return NULL;
  }

  /**
   * Resolves relation types to query for the source entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity.
   *
   * @return list<string>
   *   List of relation type machine names.
   */
  private function resolveRelationTypes(ContentEntityInterface $entity): array {
    $configured = trim((string) ($this->configuration['relation_types'] ?? ''));
    if ($configured !== '') {
      $types = array_filter(array_map('trim', explode(',', $configured)));
      if ($types !== []) {
        return array_values($types);
      }
    }

    $entityTypeId = $entity->getEntityTypeId();
    return match ($entityTypeId) {
      'aculta_event' => ['event_location'],
      'aculta_event_organization' => ['organization_event'],
      'node' => match ($entity->bundle()) {
        'wiki' => ['related_entry', 'wiki_category'],
        default => ['related_entry'],
      },
      'group' => ['course_document', 'course_video'],
      default => ['related_entry'],
    };
  }

  /**
   * Builds the render array for a single related target entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $target
   *   The target entity.
   *
   * @return array<string, mixed>
   *   Render array for the item.
   */
  private function buildItemRenderArray(ContentEntityInterface $target): array {
    $item = [
      '#type' => 'container',
      '#attributes' => [
        'data-portal-ui' => 'related-content-item',
        'data-entity-type' => $target->getEntityTypeId(),
        'data-entity-id' => (string) $target->id(),
      ],
    ];

    $label = (string) ($target->label() ?? $target->id());
    $url = NULL;
    try {
      $url = $target->toUrl('canonical');
    }
    catch (\Throwable) {
      $url = NULL;
    }

    if ($url !== NULL) {
      $item['title'] = [
        '#type' => 'link',
        '#title' => $label,
        '#url' => $url,
        '#attributes' => [
          'data-portal-ui' => 'related-content-link',
        ],
      ];
    }
    else {
      $item['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $label,
        '#attributes' => [
          'data-portal-ui' => 'related-content-title',
        ],
      ];
    }

    $typeLabel = (string) $target->getEntityType()->getLabel();
    $item['badge'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $typeLabel,
      '#attributes' => [
        'data-portal-ui' => 'related-content-badge',
      ],
    ];

    if (!empty($this->configuration['show_summary'])) {
      $summary = $this->extractSummary($target);
      if ($summary !== '') {
        $item['summary'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $summary,
          '#attributes' => [
            'data-portal-ui' => 'related-content-summary',
          ],
        ];
      }
    }

    return $item;
  }

  /**
   * Extracts a short plain-text summary from common content fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to inspect.
   *
   * @return string
   *   Summary text up to 160 characters.
   */
  private function extractSummary(ContentEntityInterface $entity): string {
    $fields = [
      'field_resumo',
      'field_summary',
      'field_descricao',
      'field_subtitle',
    ];
    foreach ($fields as $fieldName) {
      if ($entity->hasField($fieldName) && !$entity->get($fieldName)->isEmpty()) {
        $val = (string) $entity->get($fieldName)->value;
        if ($val !== '') {
          return mb_substr(strip_tags($val), 0, 160);
        }
      }
    }

    if ($entity->hasField('body') && !$entity->get('body')->isEmpty()) {
      $item = $entity->get('body')->first();
      if ($item !== NULL) {
        if (!empty($item->summary)) {
          return mb_substr(strip_tags((string) $item->summary), 0, 160);
        }
        if (!empty($item->value)) {
          return mb_substr(strip_tags((string) $item->value), 0, 160);
        }
      }
    }

    return '';
  }

}
