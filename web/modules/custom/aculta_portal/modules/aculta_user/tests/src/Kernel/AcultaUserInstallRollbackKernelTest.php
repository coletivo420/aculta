<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Account\NicknameRegistry;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Proves a clean install and the guarded rollback of aculta_user alone.
 *
 * The module owns two hook_schema tables and four content entity types. This
 * test installs it from scratch against its real prerequisites (User, Profile,
 * Node and Comment) and then removes it, so a release candidate can show that
 * install and uninstall leave no owned storage behind.
 *
 * aculta_portal and aculta_image are not installed here; the services that
 * aculta_user injects from them are registered with their real classes when
 * their dependencies are core-only, and as inert placeholders otherwise.
 */
#[Group('aculta_user')]
#[RunTestsInSeparateProcesses]
final class AcultaUserInstallRollbackKernelTest extends KernelTestBase {

  /**
   * Owned hook_schema tables.
   */
  private const SCHEMA_TABLES = [
    'aculta_user_agreement',
    'aculta_user_nickname',
  ];

  /**
   * Owned content entity base tables.
   */
  private const ENTITY_TABLES = [
    'aculta_badge_award',
    'aculta_friendship',
    'aculta_associado',
    'aculta_legado_autor',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'entity',
    'filter',
    'text',
    'views',
    'profile',
    'node',
    'comment',
    'datetime',
    'link',
    'image',
    'file',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->register('aculta_image.catalog', ImageCatalog::class);
    $container->register('aculta_image.render', ImageRenderer::class)
      ->addArgument(new Reference('aculta_image.catalog'))
      ->addArgument(new Reference('entity_type.manager'));
    $container->register('aculta_image.file_remover', FileRemover::class)
      ->addArgument(new Reference('file.usage'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('database'))
      ->addArgument(new Reference('file_system'))
      ->addArgument(new Reference('logger.factory'));
    $container->register('aculta_portal.email_confirmation_policy', EmailConfirmationPolicy::class)
      ->addArgument(new Reference('user.data'))
      ->addArgument(new Reference('entity_type.manager'))
      ->addArgument(new Reference('module_handler'));

    // Domain negotiation and the unused-file cleaner belong to aculta_portal
    // and are never instantiated by this test; they only need to exist so the
    // container compiles while aculta_user is being installed.
    $container->register('aculta_portal.domain_purpose', \stdClass::class);
    $container->register('aculta_portal.unused_file_cleaner', \stdClass::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_user\\', dirname(__DIR__, 3) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_portal\\', dirname(__DIR__, 5) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', dirname(__DIR__, 5) . '/modules/aculta_image/src', TRUE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('node');
    $this->installEntitySchema('comment');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installSchema('file', ['file_usage']);
    $this->installConfig([
      'system',
      'user',
      'filter',
      'node',
      'comment',
      'profile',
      'datetime',
      'link',
      'image',
      'file',
    ]);
  }

  /**
   * A clean install creates every owned table, entity type and contract.
   */
  public function testCleanInstallCreatesOwnedStorageAndContracts(): void {
    $this->assertTrue($this->container->get('module_installer')->install(['aculta_user'], FALSE));

    $container = \Drupal::getContainer();
    $this->assertTrue($container->get('module_handler')->moduleExists('aculta_user'));

    $schema = $container->get('database')->schema();
    foreach (self::SCHEMA_TABLES as $table) {
      $this->assertTrue($schema->tableExists($table), "Missing owned schema table $table.");
    }
    foreach (self::ENTITY_TABLES as $table) {
      $this->assertTrue($schema->tableExists($table), "Missing entity table $table.");
    }
    $this->assertTrue($schema->indexExists('aculta_user_nickname', 'nickname_slug'));
    $this->assertTrue($schema->fieldExists('aculta_user_nickname', 'slug'));
    $this->assertTrue($schema->fieldExists('aculta_user_agreement', 'record_hash'));

    $entity_type_manager = $container->get('entity_type.manager');
    foreach (['aculta_badge_award', 'aculta_friendship', 'aculta_associado', 'aculta_legado_autor'] as $entity_type_id) {
      $this->assertTrue($entity_type_manager->hasDefinition($entity_type_id), "Missing entity type $entity_type_id.");
      $this->assertNotNull($entity_type_manager->getStorage($entity_type_id));
    }

    // Default configuration owned by the module.
    $settings = $container->get('config.factory')->get('aculta_user.settings');
    $this->assertSame(10, $settings->get('topico_comentarios_minimos'));
    $this->assertSame(['content_editor', 'administrator'], $settings->get('papeis_isentos'));
    $this->assertNotNull(ProfileType::load('authenticated'));
    // The profile types belong to this module; the declared dependency is what
    // lets an uninstall remove them instead of leaving orphaned bundles.
    $this->assertSame(['module' => ['aculta_user']], ProfileType::load('authenticated')->getDependencies());
    $this->assertNotNull(ProfileType::load('estudante'));
    $this->assertNotNull(FieldStorageConfig::load('profile.field_nickname'));
    $this->assertNotNull(FieldStorageConfig::load('profile.field_perfil_publico'));
    $this->assertNotNull(FieldStorageConfig::load('node.field_autor_legado'));
    $this->assertNotNull(FieldStorageConfig::load('comment.field_autor_legado'));

    // Services and public aliases.
    $this->assertInstanceOf(NicknameRegistry::class, $container->get('aculta_user.nickname_registry'));
    $this->assertInstanceOf(PublicProfilePolicy::class, $container->get('aculta_user.public_profile_policy'));
    $this->assertSame(
      $container->get('aculta_user.associado_marker'),
      $container->get('aculta_user.active_associate'),
    );

    // Routes and permissions come from the module's YAML.
    $this->assertSame(
      '/u/{apelido}',
      $container->get('router.route_provider')->getRouteByName('aculta_user.perfil_publico')->getPath(),
    );
    $this->assertSame(
      '/painel-administrativo/pessoas',
      $container->get('router.route_provider')->getRouteByName('aculta_user.people')->getPath(),
    );
    $permissions = $container->get('user.permissions')->getPermissions();
    $this->assertArrayHasKey('administer aculta user agreements', $permissions);
    $this->assertArrayHasKey('administer aculta friendships', $permissions);
  }

  /**
   * Uninstall refuses to discard friendship data and then rolls back cleanly.
   */
  public function testUninstallIsGuardedAndRollsBackOwnedStorage(): void {
    $installer = $this->container->get('module_installer');
    $this->assertTrue($installer->install(['aculta_user'], FALSE));

    $container = \Drupal::getContainer();
    $entity_type_manager = $container->get('entity_type.manager');
    $friendships = $entity_type_manager->getStorage('aculta_friendship');

    $low = User::create(['name' => 'install-rollback-low', 'mail' => 'low@example.test', 'status' => 1]);
    $low->save();
    $high = User::create(['name' => 'install-rollback-high', 'mail' => 'high@example.test', 'status' => 1]);
    $high->save();
    $friendship = $friendships->create([
      'id' => $low->id() . '-' . $high->id(),
      'member_low' => $low->id(),
      'member_high' => $high->id(),
      'requester' => $low->id(),
      'recipient' => $high->id(),
      'status' => 'pending',
    ]);
    $friendship->save();

    try {
      $installer->uninstall(['aculta_user']);
      $this->fail('Uninstall must be blocked while friendship records remain.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame(
        'ACULTA User cannot be uninstalled while friendship records remain. Export or remove the records explicitly first.',
        $exception->getMessage(),
      );
    }

    // The refused rollback changes nothing.
    $this->assertTrue($container->get('module_handler')->moduleExists('aculta_user'));
    $this->assertTrue($container->get('database')->schema()->tableExists('aculta_friendship'));
    $this->assertSame(1, (int) $friendships->getQuery()->accessCheck(FALSE)->count()->execute());

    // With the records gone, uninstall removes every owned table and config.
    $friendship->delete();
    $this->assertTrue($installer->uninstall(['aculta_user']));

    $container = \Drupal::getContainer();
    $this->assertFalse($container->get('module_handler')->moduleExists('aculta_user'));
    $schema = $container->get('database')->schema();
    foreach (array_merge(self::SCHEMA_TABLES, self::ENTITY_TABLES) as $table) {
      $this->assertFalse($schema->tableExists($table), "Rollback left table $table behind.");
    }
    $entity_type_manager = $container->get('entity_type.manager');
    foreach (['aculta_badge_award', 'aculta_friendship', 'aculta_associado', 'aculta_legado_autor'] as $entity_type_id) {
      $this->assertFalse($entity_type_manager->hasDefinition($entity_type_id), "Rollback left entity type $entity_type_id behind.");
    }
    $this->assertNull($container->get('config.factory')->get('aculta_user.settings')->get('topico_limite_ativo'));
    $entity_type_manager->getStorage('profile_type')->resetCache();
    $this->assertNull(ProfileType::load('authenticated'));
    $this->assertNull(ProfileType::load('estudante'));

    // Field storages live on the shared Profile entity and hold member data.
    // Drupal recalculates their dependencies from the field type and entity
    // provider, so aculta_user never owns them; the rollback keeps them.
    $entity_type_manager->getStorage('field_storage_config')->resetCache();
    $this->assertNotNull(FieldStorageConfig::load('profile.field_nickname'));
    $this->assertSame(['module' => ['profile']], FieldStorageConfig::load('profile.field_nickname')->getDependencies());
  }

}
