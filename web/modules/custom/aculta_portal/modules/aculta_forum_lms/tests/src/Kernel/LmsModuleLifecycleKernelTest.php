<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\comment\Entity\CommentType;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\GroupType;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Proves the adapter can be activated, deactivated and reactivated.
 *
 * The module installer runs the real install and uninstall paths, including
 * the default configuration and its dependency validation. The adapter's
 * ACULTA dependencies are represented by the Kernel prerequisites and by the
 * fixture module, so the module is installed without expanding its full
 * dependency chain; its own exported configuration is still installed by the
 * ConfigInstaller.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsModuleLifecycleKernelTest extends KernelTestBase {

  /**
   * Configuration shipped as optional because only the H5P module needs it.
   *
   * The exported form display depends on the H5P field. A site running the
   * full ACULTA suite has aculta_lms_h5p enabled, so the field exists before
   * the adapter is activated; the Kernel cannot enable that module because of
   * its own dependency chain. The optional configuration is therefore written
   * from the export around the install call, which reproduces the dependency
   * the real site provides without turning it into adapter-owned config.
   *
   * Only the field config is written: the dependency check compares config
   * names, while a raw field storage would be read by the Views data builder
   * and fail on the aculta_h5p_content entity type this Kernel lacks.
   */
  private const OPTIONAL_CONFIGS = [
    'field.field.node.aculta_lms_topic.field_h5p_content',
  ];

  /**
   * Configuration names present before any adapter configuration is written.
   *
   * @var string[]
   */
  private array $baselineConfigNames = [];

  /**
   * Relationship type IDs the adapter adds during the current activation.
   *
   * @var string[]
   */
  private array $adapterRelationshipTypes = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'comment',
    'views',
    'node',
    'options',
    'flexible_permissions',
    'group',
    'gnode',
    'lms',
    'lms_classes',
    'aculta_forum_lms_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_config_wrapper');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    // The uninstall removes the module's user data rows.
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'user', 'node', 'comment', 'group', 'lms', 'lms_classes']);

    self::assertInstanceOf(GroupType::class, GroupType::load('lms_course'));
    self::assertInstanceOf(GroupType::class, GroupType::load('lms_class'));

    // The shared discussion field and its Comment type belong to aculta_forum.
    CommentType::create([
      'id' => 'aculta_discussion_post',
      'label' => 'Discussion post',
      'target_entity_type_id' => 'node',
    ])->save();
    $this->container->get('comment.manager')->addBodyField('aculta_discussion_post');
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_aculta_discussion',
      'type' => 'comment',
      'settings' => ['comment_type' => 'aculta_discussion_post'],
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();

    // The rejection marker is a prerequisite of the adapter moderation view.
    FieldStorageConfig::create([
      'entity_type' => 'comment',
      'field_name' => 'field_aculta_rejected',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'comment',
      'bundle' => 'aculta_discussion_post',
      'field_name' => 'field_aculta_rejected',
      'label' => 'Rejected',
    ])->save();

    // Every name present here belongs to the prerequisites, never to the
    // adapter: after a correct uninstall this list must be unchanged.
    $this->baselineConfigNames = $this->configFactory()->listAll();
  }

  /**
   * Activation installs exactly the exported configuration.
   */
  public function testActivationInstallsTheAdapterConfiguration(): void {
    $baseline = $this->baselineConfigNames;
    $this->installAdapter();

    self::assertTrue($this->moduleHandler()->moduleExists('aculta_forum_lms'));
    self::assertSame(
      $this->expectedConfigNames($baseline),
      $this->configFactory()->listAll(),
      'Activation adds only the exported configuration.',
    );

    foreach ($this->installConfigNames() as $configName) {
      $yaml = $this->readInstallConfig($configName);
      $stored = $this->config($configName)->getRawData();
      self::assertNotSame([], $stored, "$configName was created.");
      self::assertSame($yaml['uuid'] ?? NULL, $stored['uuid'] ?? NULL, "$configName keeps the exported uuid.");
    }

    // The ConfigInstaller records the hash of the installed file, which the
    // export does not carry; every other key must match the export exactly.
    $exportedSettings = $this->readInstallConfig('aculta_forum_lms.settings');
    $storedSettings = $this->config('aculta_forum_lms.settings')->getRawData();
    self::assertArrayHasKey('_core', $storedSettings);
    unset($storedSettings['_core']);
    self::assertSame($exportedSettings, $storedSettings);
    self::assertInstanceOf(NodeType::class, NodeType::load('aculta_lms_topic'));
    self::assertInstanceOf(FieldStorageConfig::class, FieldStorageConfig::load('node.field_lms_activity'));
    self::assertInstanceOf(FieldConfig::class, FieldConfig::load('node.aculta_lms_topic.field_lms_activity'));
    self::assertNotNull($this->container->get('entity_type.manager')
      ->getStorage('view')
      ->load('aculta_forum_lms_admin_comments'));
    self::assertTrue($this->container->has('aculta_forum_lms.moderation_manager'));
  }

  /**
   * Deactivation removes every configuration object the adapter owned.
   */
  public function testDeactivationLeavesNoConfigurationResidue(): void {
    $baseline = $this->baselineConfigNames;
    $classSettings = $this->config('lms_classes.settings')->getRawData();

    $this->installAdapter();
    $this->uninstallAdapter();

    self::assertFalse($this->moduleHandler()->moduleExists('aculta_forum_lms'));
    self::assertSame($baseline, $this->configFactory()->listAll(), 'Uninstall returns the configuration to the baseline.');
    self::assertFalse($this->container->has('aculta_forum_lms.moderation_manager'));

    foreach ($this->installConfigNames() as $configName) {
      self::assertSame([], $this->config($configName)->getRawData(), "$configName was removed.");
    }
    self::assertNull(NodeType::load('aculta_lms_topic'));
    self::assertNull(FieldStorageConfig::load('node.field_lms_activity'));
    self::assertNull(FieldConfig::load('node.aculta_lms_topic.field_lms_activity'));
    self::assertNull($this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type')
      ->load('lms_course-3abb8caf7f7d526ecedee'));
    self::assertNull($this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type')
      ->load('lms_class-3abb8caf7f7d526ecedeec'));

    foreach ($this->configFactory()->listAll() as $configName) {
      self::assertStringNotContainsString('aculta_lms_topic', $configName);
      self::assertStringNotContainsString('aculta_forum_lms', $configName);
    }

    // Configuration owned by other modules is not rewritten.
    self::assertSame($classSettings, $this->config('lms_classes.settings')->getRawData());
    self::assertArrayNotHasKey(
      'aculta_forum_lms',
      $this->moduleHandler()->getModuleList(),
    );
    self::assertArrayNotHasKey(
      'aculta_forum_lms',
      $this->config('core.extension')->get('module'),
    );
  }

  /**
   * Reactivation rebuilds the same configuration without leftovers.
   */
  public function testReactivationReproducesTheSameConfiguration(): void {
    $baseline = $this->baselineConfigNames;

    $this->installAdapter();
    $first = $this->adapterConfigSnapshot();
    $this->uninstallAdapter();
    $this->installAdapter();

    self::assertTrue($this->moduleHandler()->moduleExists('aculta_forum_lms'));
    self::assertSame(
      $this->expectedConfigNames($baseline),
      $this->configFactory()->listAll(),
      'Reactivation does not duplicate or leak configuration.',
    );
    self::assertSame($first, $this->adapterConfigSnapshot(), 'Reactivation reproduces the first activation exactly.');
    self::assertInstanceOf(NodeType::class, NodeType::load('aculta_lms_topic'));
    self::assertTrue($this->container->has('aculta_forum_lms.moderation_manager'));
  }

  /**
   * Installs the adapter through the module installer.
   *
   * The optional H5P configuration is provided only for the duration of the
   * install, the way an enabled aculta_lms_h5p provides it on a real site.
   */
  private function installAdapter(): void {
    $before = $this->relationshipTypeIds();
    $this->provideOptionalH5pConfig();
    try {
      self::assertTrue(
        $this->container->get('module_installer')->install(['aculta_forum_lms'], FALSE),
      );
    }
    finally {
      $this->deleteOptionalH5pConfig();
    }
    self::assertTrue($this->moduleHandler()->moduleExists('aculta_forum_lms'));
    $this->adapterRelationshipTypes = array_values(array_diff($this->relationshipTypeIds(), $before));
    self::assertCount(2, $this->adapterRelationshipTypes, 'Activation adds the two Group relationship types.');
  }

  /**
   * Uninstalls the adapter through the module installer.
   *
   * Group 3.3.5 overrides ConfigEntityBundleBase::postDelete() in
   * GroupRelationshipType without calling the parent, so deleting a
   * relationship type leaves its bundle in entity_type.bundle.info. When the
   * uninstall later deletes a field config, the field map rebuild resolves
   * that bundle and fails with "Call to a member function getPluginId() on
   * null". The adapter's two relationship types are therefore removed first
   * and the bundle cache is cleared, which is the state a cache rebuild
   * produces; every other configuration object is still removed by the
   * module installer, so the residue assertions keep their meaning.
   */
  private function uninstallAdapter(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type');
    $storage->delete($storage->loadMultiple($this->adapterRelationshipTypes));
    $this->adapterRelationshipTypes = [];
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    $this->container->get('module_installer')->uninstall(['aculta_forum_lms']);
    self::assertFalse($this->moduleHandler()->moduleExists('aculta_forum_lms'));
  }

  /**
   * Returns the Group relationship type IDs currently stored.
   *
   * @return string[]
   *   The relationship type IDs.
   */
  private function relationshipTypeIds(): array {
    $ids = array_keys($this->container->get('entity_type.manager')
      ->getStorage('group_relationship_type')
      ->loadMultiple());
    sort($ids);
    return $ids;
  }

  /**
   * Writes the optional H5P configuration the exported display depends on.
   *
   * The write goes straight to config storage: the field's schema lives in
   * aculta_lms_h5p, which cannot be enabled here, so saving it through the
   * config factory would fail the strict schema check that the install path
   * itself never applies to an already existing optional config.
   */
  private function provideOptionalH5pConfig(): void {
    $storage = $this->container->get('config.storage');
    foreach (self::OPTIONAL_CONFIGS as $configName) {
      $storage->write($configName, $this->readConfigFile('optional', $configName));
    }
  }

  /**
   * Removes the simulated H5P configuration after the install call.
   */
  private function deleteOptionalH5pConfig(): void {
    $storage = $this->container->get('config.storage');
    foreach (self::OPTIONAL_CONFIGS as $configName) {
      $storage->delete($configName);
      $this->configFactory()->reset($configName);
    }
  }

  /**
   * Returns the install configuration names shipped by the adapter.
   *
   * @return string[]
   *   The configuration object names.
   */
  private function installConfigNames(): array {
    $names = [];
    foreach (glob($this->installConfigDirectory() . '/*.yml') as $file) {
      $names[] = basename($file, '.yml');
    }
    sort($names);
    return $names;
  }

  /**
   * Returns the baseline names plus the adapter configuration, sorted.
   *
   * @param string[] $baseline
   *   Configuration names present before the adapter was installed.
   *
   * @return string[]
   *   The expected configuration names after activation.
   */
  private function expectedConfigNames(array $baseline): array {
    $names = array_merge($baseline, $this->installConfigNames());
    $names = array_values(array_unique($names));
    sort($names);
    return $names;
  }

  /**
   * Snapshots the adapter configuration currently in storage.
   *
   * @return array<string, array>
   *   Raw configuration data keyed by configuration name.
   */
  private function adapterConfigSnapshot(): array {
    $snapshot = [];
    foreach ($this->installConfigNames() as $configName) {
      $snapshot[$configName] = $this->config($configName)->getRawData();
    }
    return $snapshot;
  }

  /**
   * Reads one exported configuration file.
   *
   * @param string $subdirectory
   *   The configuration subdirectory, such as install or optional.
   * @param string $configName
   *   The configuration object name.
   *
   * @return array
   *   The parsed configuration.
   */
  private function readConfigFile(string $subdirectory, string $configName): array {
    $path = dirname(__DIR__, 3) . '/config/' . $subdirectory . '/' . $configName . '.yml';
    self::assertFileExists($path);
    $data = Yaml::parseFile($path);
    self::assertIsArray($data);
    return $data;
  }

  /**
   * Reads one exported install configuration file.
   *
   * @param string $configName
   *   The configuration object name.
   *
   * @return array
   *   The parsed configuration.
   */
  private function readInstallConfig(string $configName): array {
    return $this->readConfigFile('install', $configName);
  }

  /**
   * Returns the adapter install configuration directory.
   */
  private function installConfigDirectory(): string {
    return dirname(__DIR__, 3) . '/config/install';
  }

  /**
   * Returns the configuration factory of the current container.
   */
  private function configFactory(): ConfigFactoryInterface {
    return $this->container->get('config.factory');
  }

  /**
   * Returns the module handler of the current container.
   */
  private function moduleHandler(): ModuleHandlerInterface {
    return $this->container->get('module_handler');
  }

}
