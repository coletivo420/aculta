<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\comment\Entity\CommentType;
use Drupal\Core\Config\DefaultConfigMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\group\Entity\GroupType;
use Drupal\group\Entity\Storage\GroupRelationshipTypeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Yaml\Yaml;

/**
 * Proves the LMS discussion bundle is installed by the ConfigInstaller.
 *
 * The test starts from the real prerequisites exported by contrib LMS, the
 * Group gnode plugin, and the shared Comment field provided by aculta_forum,
 * then installs aculta_forum_lms default configuration and inspects the
 * persisted entities.
 */
#[TestGroup('aculta_forum_lms')]
#[RunTestsInSeparateProcesses]
final class LmsTopicBundleInstallKernelTest extends KernelTestBase {

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
    $this->installConfig(['system', 'user', 'node', 'comment', 'group', 'lms', 'lms_classes']);

    // Contrib LMS provides both Group types as optional configuration. Their
    // presence is the real prerequisite for the adapter relationships.
    self::assertInstanceOf(GroupType::class, GroupType::load('lms_course'));
    self::assertInstanceOf(GroupType::class, GroupType::load('lms_class'));

    // The shared discussion field and its Comment type belong to aculta_forum.
    // Load the exported configuration instead of duplicating its definition.
    $forum_install_path = dirname(__DIR__, 4) . '/aculta_forum/config/install';
    $comment_type_path = $forum_install_path . '/comment.type.aculta_discussion_post.yml';
    $discussion_storage_path = $forum_install_path . '/field.storage.node.field_aculta_discussion.yml';
    self::assertFileExists($comment_type_path);
    self::assertFileExists($discussion_storage_path);
    CommentType::create(Yaml::parseFile($comment_type_path))->save();
    $this->container->get('comment.manager')->addBodyField('aculta_discussion_post');
    FieldStorageConfig::create(Yaml::parseFile($discussion_storage_path))->save();

    // The body field is added by the installation profile, not by Node itself.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'body',
      'type' => 'text_with_summary',
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();

    self::assertNull(NodeType::load('aculta_lms_topic'), 'The bundle does not exist before installing the adapter.');

    // Only config entities are installed: the module itself is not enabled in
    // this kernel, so its simple settings schema is not registered and the
    // adapter's settings are covered by the role/permission test instead.
    $this->container->get('config.installer')
      ->installDefaultConfig('module', 'aculta_forum_lms', DefaultConfigMode::InstallEntities);
  }

  /**
   * Asserts the bundle, displays, shared Comment field, and Group links.
   */
  public function testConfigInstallerCreatesLmsTopicBundle(): void {
    $node_type = NodeType::load('aculta_lms_topic');
    self::assertInstanceOf(NodeType::class, $node_type);
    self::assertTrue($node_type->shouldCreateNewRevision());
    self::assertSame(1, $node_type->getPreviewMode());

    $discussion_field = FieldConfig::load('node.aculta_lms_topic.field_aculta_discussion');
    self::assertInstanceOf(FieldConfig::class, $discussion_field);
    self::assertTrue($discussion_field->isRequired());
    self::assertSame('comment', $discussion_field->getType());
    self::assertSame('aculta_discussion_post', $discussion_field->getSetting('comment_type'));
    $default_value = $discussion_field->getDefaultValueLiteral();
    self::assertSame(2, $default_value[0]['status'], 'New discussions start open.');
    self::assertSame(0, $default_value[0]['comment_count']);

    $body_field = FieldConfig::load('node.aculta_lms_topic.body');
    self::assertInstanceOf(FieldConfig::class, $body_field);
    self::assertTrue($body_field->isRequired());

    $activity_field = FieldConfig::load('node.aculta_lms_topic.field_lms_activity');
    self::assertInstanceOf(FieldConfig::class, $activity_field);
    $activity_storage = FieldStorageConfig::load('node.field_lms_activity');
    self::assertInstanceOf(FieldStorageConfig::class, $activity_storage);
    self::assertSame('lms_activity', $activity_storage->getSetting('target_type'));
  }

  /**
   * Asserts the form, full, and teaser displays are installed.
   */
  public function testConfigInstallerCreatesDisplays(): void {
    $form_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_form_display')
      ->load('node.aculta_lms_topic.default');
    self::assertNotNull($form_display);
    self::assertNotNull($form_display->getComponent('body'));
    self::assertNotNull($form_display->getComponent('field_lms_activity'));

    $full_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display')
      ->load('node.aculta_lms_topic.full');
    self::assertNotNull($full_display);
    $discussion_component = $full_display->getComponent('field_aculta_discussion');
    self::assertNotNull($discussion_component);
    self::assertSame('comment_default', $discussion_component['type']);
    self::assertNotNull($full_display->getComponent('body'));

    $teaser_display = $this->container->get('entity_type.manager')
      ->getStorage('entity_view_display')
      ->load('node.aculta_lms_topic.teaser');
    self::assertNotNull($teaser_display);
    self::assertNotNull($teaser_display->getComponent('body'));
  }

  /**
   * Asserts both Group Node relationships are installed with their limits.
   */
  public function testConfigInstallerCreatesGroupRelationships(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    self::assertInstanceOf(GroupRelationshipTypeStorageInterface::class, $storage);

    foreach ([
      'lms_course-3abb8caf7f7d526ecedee' => 'lms_course',
      'lms_class-3abb8caf7f7d526ecedeec' => 'lms_class',
    ] as $relationship_id => $group_type_id) {
      $relationship = $storage->load($relationship_id);
      self::assertNotNull($relationship, "Relationship $relationship_id was installed.");
      $config = $this->config("group.relationship_type.$relationship_id");
      self::assertSame($group_type_id, $config->get('group_type'));
      self::assertSame('group_node:aculta_lms_topic', $config->get('content_plugin'));
      self::assertSame(1, $config->get('plugin_config.entity_cardinality'));
      self::assertSame(0, $config->get('plugin_config.group_cardinality'));
    }
  }

  /**
   * Asserts missing optional config is skipped by the installer.
   */
  public function testConfigInstallerSkipsOptionalH5pWithoutModule(): void {
    // aculta_lms_h5p is not enabled in this kernel, so its optional fields must
    // stay out of the installed bundle.
    self::assertNull(FieldConfig::load('node.aculta_lms_topic.field_h5p_content'));
    self::assertNull(FieldStorageConfig::load('node.field_h5p_content'));
  }

}
