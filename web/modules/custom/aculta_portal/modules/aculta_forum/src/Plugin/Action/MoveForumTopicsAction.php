<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Plugin\Action;

use Drupal\aculta_forum\Service\ForumTopicMover;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Moves a bounded selection of public ACULTA Forum topics to one category.
 */
#[Action(
  id: 'aculta_forum_move_topics',
  label: new TranslatableMarkup('Mover tópicos para outra categoria'),
  type: 'node',
)]
final class MoveForumTopicsAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface, PluginFormInterface {

  use DependencySerializationTrait;

  /**
   * Maximum selection size accepted for a single bulk movement.
   */
  public const MAX_SELECTION = 50;

  /**
   * Constructs a MoveForumTopicsAction object.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\aculta_forum\Service\ForumTopicMover $mover
   *   The forum topic mover.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user account.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ForumTopicMover $mover,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aculta_forum.topic_mover'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account ??= $this->currentUser;
    $destination_id = filter_var($this->configuration['destination_category'] ?? NULL, FILTER_VALIDATE_INT);
    $destination = $destination_id !== FALSE && $destination_id > 0
      ? $this->entityTypeManager->getStorage('taxonomy_term')->load($destination_id)
      : NULL;

    if (!$object instanceof NodeInterface || !$destination instanceof TermInterface
      || $destination->bundle() !== 'aculta_forum_categories') {
      $result = AccessResult::forbidden();
    }
    else {
      $result = $this->mover->access($object, $account);
      $destination_access = $destination->access('view', $account, TRUE);
      $result = $result->andIf($destination_access);
      $cat_items = $object->hasField('field_aculta_categories') ? $object->get('field_aculta_categories')->getValue() : [];
      $cat_first = is_array($cat_items) && isset($cat_items[0]) && is_array($cat_items[0]) ? $cat_items[0] : [];
      $cat_id = isset($cat_first['target_id']) && is_numeric($cat_first['target_id']) ? (int) $cat_first['target_id'] : 0;
      if ($cat_id === (int) $destination->id()) {
        $result = $result->andIf(AccessResult::forbidden()->addCacheableDependency($object));
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   * @return array<string, mixed>
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $selected_count = $this->getSelectedCount();
    $form['selection_summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Tópicos selecionados'),
      '#markup' => $this->formatPlural($selected_count, '1 tópico será avaliado.', '@count tópicos serão avaliados.'),
    ];

    $form['destination_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Categoria de destino'),
      '#options' => $this->getDestinationOptions(),
      '#default_value' => $this->configuration['destination_category'] ?? NULL,
      '#required' => TRUE,
      '#description' => $this->t('Somente tópicos públicos elegíveis serão movidos. Tópicos do acervo histórico são sempre recusados.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // ConfigureAction restores the selection to FormState for validation but
    // does not call setContext() on the newly created action instance.
    $selection = $form_state->get('views_bulk_operations');
    $selected_count = $this->getSelectedCount(is_array($selection) ? $selection : NULL);
    if ($selected_count < 1 || $selected_count > self::MAX_SELECTION) {
      $form_state->setErrorByName('destination_category', $this->t('Selecione entre 1 e @limit tópicos por operação.', [
        '@limit' => self::MAX_SELECTION,
      ]));
    }

    $destination_id = filter_var($form_state->getValue('destination_category'), FILTER_VALIDATE_INT);
    $destination = $destination_id !== FALSE && $destination_id > 0
      ? $this->entityTypeManager->getStorage('taxonomy_term')->load($destination_id)
      : NULL;
    if (!$destination instanceof TermInterface
      || $destination->bundle() !== 'aculta_forum_categories'
      || !$destination->access('view', $this->currentUser)) {
      $form_state->setErrorByName('destination_category', $this->t('Escolha uma categoria pública válida.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $destVal = $form_state->getValue('destination_category');
    $this->configuration['destination_category'] = is_numeric($destVal) ? (int) $destVal : 0;
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function execute(?object $object = NULL): array {
    if ($this->getSelectedCount() > self::MAX_SELECTION
      || !$object instanceof NodeInterface
      || !$this->access($object, $this->currentUser)) {
      return [
        'message' => $this->t('O tópico deixou de ser elegível ou autorizado; nenhuma alteração foi aplicada.'),
        'type' => 'warning',
      ];
    }

    try {
      $destCat = $this->configuration['destination_category'] ?? 0;
      $destCatId = is_numeric($destCat) ? (int) $destCat : 0;
      $this->mover->move((int) $object->id(), $destCatId, $this->currentUser);
      return ['message' => $this->t('Tópico movido para a categoria selecionada.')];
    }
    catch (\InvalidArgumentException) {
      return [
        'message' => $this->t('O tópico deixou de ser elegível ou autorizado; nenhuma alteração foi aplicada.'),
        'type' => 'warning',
      ];
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int, mixed> $objects
   * @return array<int, mixed>
   */
  public function executeMultiple(array $objects): array {
    if ($this->getSelectedCount() > self::MAX_SELECTION) {
      return array_fill(0, count($objects), [
        'message' => $this->t('A seleção excede o limite de @limit tópicos; nada foi movido.', [
          '@limit' => self::MAX_SELECTION,
        ]),
        'type' => 'error',
      ]);
    }

    $results = [];
    foreach ($objects as $object) {
      $results[] = $this->execute(is_object($object) ? $object : NULL);
    }
    return $results;
  }

  /**
   * Gets category destinations visible to the current account.
   *
   * @return array<int, string>
   *   Taxonomy term IDs keyed to their indented labels.
   */
  private function getDestinationOptions(): array {
    $options = [];
    $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('aculta_forum_categories', 0, NULL, TRUE);
    foreach ($tree as $term) {
      if ($term instanceof TermInterface
        && $term->bundle() === 'aculta_forum_categories'
        && $term->access('view', $this->currentUser)) {
        $treeProperties = get_object_vars($term);
        $depth = $treeProperties['depth'] ?? 0;
        $depth = is_int($depth) ? $depth : 0;
        $options[(int) $term->id()] = str_repeat('— ', $depth) . $term->label();
      }
    }
    return $options;
  }

  /**
   * Counts the persisted VBO selection without trusting display labels.
   *
   * @param array<array-key, mixed>|null $context
   */
  private function getSelectedCount(?array $context = NULL): int {
    $context ??= $this->context;
    $list = $context['list'] ?? [];
    if (!is_array($list)) {
      return 0;
    }

    if (!empty($context['exclude_mode'])) {
      $total = $context['total_results'] ?? 0;
      $totalInt = is_numeric($total) ? (int) $total : 0;
      return max(0, $totalInt - count($list));
    }
    return count($list);
  }

}
