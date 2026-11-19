<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\CoAuthor\CoAuthorFieldDefinition;
use Drupal\aculta_user\CoAuthor\CoAuthorManager;
use Drupal\aculta_user\CoAuthor\CoAuthorManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * OOP Hook subscriber for ACULTA node co-authorship.
 */
final class NodeCoAuthorHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.coauthor_manager')]
    private readonly CoAuthorManagerInterface $coAuthorManager,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   *
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   *   Base field definitions.
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'node') {
      return [];
    }

    return [
      CoAuthorManager::FIELD_NAME => CoAuthorFieldDefinition::build($entity_type),
    ];
  }

  /**
   * Checks if a bundle is enabled for co-authorship.
   *
   * Fórum (aculta_forum_topic) é explicitamente excluído.
   */
  private function isBundleEnabled(string $bundle): bool {
    if ($bundle === 'aculta_forum_topic') {
      return FALSE;
    }

    $config = $this->configFactory->get('aculta_user.coauthor_settings');
    $enabledBundles = $config->get('enabled_bundles');
    if (is_array($enabledBundles)) {
      return !empty($enabledBundles[$bundle]);
    }

    // Por padrão antes de salvar config, habilitado para bundles com suporte, exceto fórum.
    return in_array($bundle, ['revista', 'wiki_entry', 'activity', 'document', 'editorial_highlight', 'page'], TRUE);
  }

  /**
   * Implements hook_node_access().
   *
   * Co-author with edit own / delete own permissions gains conditional access.
   * View access to unpublished nodes requires view own unpublished content
   * and respects Content Moderation.
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $op, AccountInterface $account): AccessResultInterface {
    $bundle = $node->bundle();
    if (!$this->isBundleEnabled($bundle)) {
      return AccessResult::neutral();
    }

    if (!$node->hasField(CoAuthorManager::FIELD_NAME)) {
      return AccessResult::neutral();
    }

    $isCoAuthor = AccessResult::allowedIf($this->coAuthorManager->isCoAuthor($node, $account))
      ->cachePerUser()
      ->addCacheableDependency($node);

    if ($op === 'view' && !$node->isPublished()) {
      return AccessResult::allowedIfHasPermission($account, 'view own unpublished content')
        ->andIf($isCoAuthor);
    }

    if ($op === 'update') {
      return AccessResult::allowedIfHasPermission($account, 'edit own ' . $bundle . ' content')
        ->andIf($isCoAuthor);
    }

    if ($op === 'delete') {
      return AccessResult::allowedIfHasPermission($account, 'delete own ' . $bundle . ' content')
        ->andIf($isCoAuthor);
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_field_access().
   *
   * Controls access to editing the aculta_coauthors field.
   *
   * @param string $operation
   *   The operation to check access for.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account trying to access the field.
   * @param \Drupal\Core\Field\FieldItemListInterface<covariant \Drupal\Core\Field\FieldItemInterface>|null $items
   *   The items list being accessed, or NULL.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() !== CoAuthorManager::FIELD_NAME) {
      return AccessResult::neutral();
    }

    if ($operation !== 'edit') {
      return AccessResult::neutral();
    }

    // Se o bundle for aculta_forum_topic, proíbe edição.
    $entity = $items?->getEntity();
    if ($entity instanceof NodeInterface && !$this->isBundleEnabled($entity->bundle())) {
      return AccessResult::forbidden('Coautoria não permitida para este bundle.')
        ->addCacheableDependency($entity);
    }

    $result = AccessResult::neutral();

    // Administrador de coautores ou de nodes tem acesso.
    if ($account->hasPermission('administer aculta coauthors') || $account->hasPermission('administer nodes') || $account->hasPermission('edit aculta coauthors of all content')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($items instanceof FieldItemListInterface && $entity instanceof NodeInterface) {
      // Autor proprietário:
      if ((int) $entity->getOwnerId() === (int) $account->id()) {
        $result = $result->orIf(AccessResult::allowedIfHasPermission($account, 'edit aculta coauthors of own content'));
      }

      // Já coautor:
      if ($this->coAuthorManager->isCoAuthor($entity, $account)) {
        $result = $result->orIf(AccessResult::allowedIfHasPermission($account, 'edit aculta coauthors of coauthored content'));
      }
    }

    $result->cachePerPermissions();
    if ($entity instanceof NodeInterface) {
      $result->addCacheableDependency($entity);
    }

    return $result->isAllowed() ? $result : AccessResult::forbidden();
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   *
   * @param array<string, mixed> $form
   *   The form array.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form): void {
    if (isset($form[CoAuthorManager::FIELD_NAME]) && is_array($form[CoAuthorManager::FIELD_NAME])) {
      $form[CoAuthorManager::FIELD_NAME]['#group'] = 'author';
    }
  }

}
