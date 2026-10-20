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

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ForumTopicMover $mover,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
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
      if ((int) ($object->get('field_aculta_categories')->getValue()[0]['target_id'] ?? 0) === (int) $destination->id()) {
        $result = $result->andIf(AccessResult::forbidden()->addCacheableDependency($object));
      }
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
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
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $selected_count = $this->getSelectedCount();
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
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['destination_category'] = (int) $form_state->getValue('destination_category');
  }

  /**
   * {@inheritdoc}
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
      $this->mover->move((int) $object->id(), (int) $this->configuration['destination_category'], $this->currentUser);
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
        $options[(int) $term->id()] = str_repeat('— ', (int) $term->depth) . $term->label();
      }
    }
    return $options;
  }

  /**
   * Counts the persisted VBO selection without trusting display labels.
   */
  private function getSelectedCount(): int {
    $list = $this->context['list'] ?? [];
    if (!is_array($list)) {
      return 0;
    }

    if (!empty($this->context['exclude_mode'])) {
      return max(0, (int) ($this->context['total_results'] ?? 0) - count($list));
    }
    return count($list);
  }

}
