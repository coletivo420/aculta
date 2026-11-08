<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_commerce\Kernel;

use Symfony\Component\Yaml\Yaml;

/**
 * Provides the ACULTA-owned LMS Group types that aculta_lms configuration needs.
 *
 * The contrib LMS modules used to create these Group types. ADR-042 removed
 * them, so the repository export in config/sync is the source of truth, the
 * same way the Forum Kernel tests load them.
 *
 * This is a test-side workaround: aculta_lms/config/install still depends on
 * these Group types, which is a production defect tracked separately.
 *
 * Removal plan: delete this trait and its calls once aculta_lms installs
 * without the Group types (config/install self-contained or moved to
 * config/optional). Until then the trait is the only thing keeping the
 * shipping and Melhor Envio suites from failing on aculta_lms configuration.
 */
trait LmsGroupTypesPrerequisiteTrait {

  /**
   * Imports lms_course and lms_class from config/sync and enables their relations.
   */
  protected function installLmsGroupTypesFromSync(): void {
    $config_factory = $this->container->get('config.factory');
    $config_names = ['group.type.lms_course', 'group.type.lms_class'];
    foreach ($config_names as $config_name) {
      $path = dirname(__DIR__, 9) . '/config/sync/' . $config_name . '.yml';
      self::assertFileExists($path);
      $data = Yaml::parseFile($path);
      // creator_wizard belonged to the removed contrib LMS schema.
      unset($data['creator_wizard']);
      $config_factory->getEditable($config_name)
        ->setData($data)
        ->save();
    }
    $relation_manager = $this->container->get('group_relation_type.manager');
    $relation_manager->clearCachedPluginMaps();
    $storage = $this->container->get('entity_type.manager')->getStorage('group_type');
    $storage->resetCache();
    foreach (['lms_course', 'lms_class'] as $id) {
      $relation_manager->installEnforced($storage->load($id));
    }
    $relation_manager->clearCachedPluginMaps();
  }

}
