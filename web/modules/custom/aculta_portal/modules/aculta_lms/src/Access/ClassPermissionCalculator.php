<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Access;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flexible_permissions\CalculatedPermissionsItem;
use Drupal\flexible_permissions\PermissionCalculatorBase;
use Drupal\flexible_permissions\RefinableCalculatedPermissionsInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembershipInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\group\PermissionScopeInterface;

/**
 * Calculates inherited permissions between course and class Groups.
 */
final class ClassPermissionCalculator extends PermissionCalculatorBase {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, $scope): RefinableCalculatedPermissionsInterface {
    $permissions = parent::calculatePermissions($account, $scope);
    \assert($permissions instanceof RefinableCalculatedPermissionsInterface);
    if ($scope !== PermissionScopeInterface::INDIVIDUAL_ID) {
      return $permissions;
    }

    $settings = $this->configFactory->get('aculta_lms.settings');
    $mappings = [
      'class' => array_replace([
        'view group' => '',
        'administer members' => 'add students',
        'view group_membership relationship' => 'view students',
      ], (array) ($settings->get('class_permission_mappings') ?? [])),
      'course' => array_replace([
        'view group' => '',
        'take course' => '',
      ], (array) ($settings->get('course_permission_mappings') ?? [])),
    ];
    $permissions->addCacheableDependency($settings)
      ->addCacheTags(['group_relationship_list:plugin:group_membership:entity:' . $account->id()]);

    $storage = $this->entityTypeManager->getStorage('group_relationship');
    $inherited = [];
    foreach ($storage->loadByProperties([
      'plugin_id' => 'group_membership',
      'entity_id' => $account->id(),
    ]) as $membership) {
      if (!$membership instanceof GroupMembershipInterface) {
        continue;
      }
      $group = $membership->getGroup();
      if (!in_array($group->bundle(), ['lms_course', 'lms_class'], TRUE)) {
        continue;
      }
      $permissions->addCacheableDependency($membership)
        ->addCacheableDependency($group);
      $from = $group->bundle() === 'lms_course' ? 'course' : 'class';
      $to = $from === 'course' ? 'class' : 'course';
      $grants = $this->permissionsFromRoles($membership, $mappings[$to], $permissions);
      if ($grants === []) {
        continue;
      }

      if ($from === 'course') {
        $permissions->addCacheTags(['group_relationship_list:plugin:lms_classes:group:' . $group->id()]);
        $relationships = $storage->loadByProperties([
          'gid' => $group->id(),
          'plugin_id' => 'lms_classes',
        ]);
        foreach ($relationships as $relationship) {
          $related = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
          if (!$related instanceof GroupInterface || $related->bundle() !== 'lms_class') {
            continue;
          }
          $permissions->addCacheableDependency($relationship)
            ->addCacheableDependency($related)
            ->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $related->id()]);
          $inherited[$related->id()] = ($inherited[$related->id()] ?? []) + $grants;
        }
      }
      else {
        $permissions->addCacheTags(['group_relationship_list:plugin:lms_classes:entity:' . $group->id()]);
        $relationships = $storage->loadByProperties([
          'entity_id' => $group->id(),
          'plugin_id' => 'lms_classes',
        ]);
        foreach ($relationships as $relationship) {
          $related = $relationship instanceof GroupRelationshipInterface ? $relationship->getGroup() : NULL;
          if (!$related instanceof GroupInterface || $related->bundle() !== 'lms_course') {
            continue;
          }
          $permissions->addCacheableDependency($relationship)
            ->addCacheableDependency($related)
            ->addCacheTags(['group_relationship_list:plugin:lms_classes:group:' . $related->id()]);
          $inherited[$related->id()] = ($inherited[$related->id()] ?? []) + $grants;
        }
      }
    }

    foreach ($inherited as $group_id => $group_permissions) {
      $permissions->addItem(new CalculatedPermissionsItem($scope, $group_id, array_values($group_permissions)));
    }
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts($scope): array {
    return $scope === PermissionScopeInterface::INDIVIDUAL_ID ? ['user'] : [];
  }

  /**
   * Maps role permissions on a membership to inherited target permissions.
   *
   * @param \Drupal\group\Entity\GroupMembershipInterface $membership
   *   Group membership relationship.
   * @param array<string, string> $mapping
   *   Target permission to required source permission mappings.
   * @param \Drupal\flexible_permissions\RefinableCalculatedPermissionsInterface $permissions
   *   Calculated permissions collection receiving cache dependencies.
   *
   * @return array<string, string>
   *   Granted permission names.
   */
  private function permissionsFromRoles(GroupMembershipInterface $membership, array $mapping, RefinableCalculatedPermissionsInterface $permissions): array {
    $grants = [];
    foreach ($mapping as $target_permission => $required_permission) {
      if ($required_permission === '') {
        $grants[$target_permission] = $target_permission;
      }
    }
    foreach ($membership->getRoles(TRUE) as $role) {
      $permissions->addCacheableDependency($role);
      foreach ($mapping as $target_permission => $required_permission) {
        if ($required_permission !== '' && $role->hasPermission($required_permission)) {
          $grants[$target_permission] = $target_permission;
        }
      }
    }
    return $grants;
  }

}
