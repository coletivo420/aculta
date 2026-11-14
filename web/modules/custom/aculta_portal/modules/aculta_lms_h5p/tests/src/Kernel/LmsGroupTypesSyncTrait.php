<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_h5p\Kernel;

use Symfony\Component\Yaml\Yaml;

/**
 * Installs the ACULTA-owned LMS Group types the Forum Kernel tests depend on.
 */
trait LmsGroupTypesSyncTrait {

  /**
   * Installs the ACULTA-owned LMS Group types from the exported configuration.
   *
   * The Group types used to be installed by the contrib LMS modules. ADR-042
   * removed them, so the repository export is the source of truth for these
   * prerequisites.
   */
  protected function installLmsGroupTypesFromSync(): void {
    $config_factory = $this->container->get('config.factory');
    foreach (['group.type.lms_course', 'group.type.lms_class'] as $config_name) {
      $path = dirname(__DIR__, 9) . '/config/sync/' . $config_name . '.yml';
      self::assertFileExists($path);
      $data = Yaml::parseFile($path);
      // creator_wizard belonged to the removed contrib LMS schema; false is the
      // Group default, so dropping it keeps the same behavior.
      unset($data['creator_wizard']);
      $config_factory->getEditable($config_name)
        ->setData($data)
        ->save();
    }
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $relation_manager = $this->container->get('group_relation_type.manager');
    $relation_manager->clearCachedPluginMaps();
    // Group creates its enforced relationship types (group_membership) only
    // when a group type is created outside Config Sync, so do it here.
    $storage = $this->container->get('entity_type.manager')->getStorage('group_type');
    $storage->resetCache();
    foreach (['lms_course', 'lms_class'] as $id) {
      $relation_manager->installEnforced($storage->load($id));
    }
    $relation_manager->clearCachedPluginMaps();
  }

}
