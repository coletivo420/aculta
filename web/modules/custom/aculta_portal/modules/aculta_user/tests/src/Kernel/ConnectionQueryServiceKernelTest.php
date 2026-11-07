<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Kernel;

use Drupal\aculta_image\FileRemover;
use Drupal\aculta_image\ImageCatalog;
use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Entity\AcultaFriendship;
use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\aculta_user\Social\ConnectionQueryService;
use Drupal\aculta_user\Social\FriendshipManager;
use Drupal\aculta_user\Social\FriendshipStatus;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\profile\Entity\Profile;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Kernel tests for ConnectionQueryService.
 */
#[Group('aculta_user')]
final class ConnectionQueryServiceKernelTest extends KernelTestBase {

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
    'aculta_user',
  ];

  /**
   *
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $loader = require dirname(DRUPAL_ROOT) . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_portal\\', dirname(__DIR__, 5) . '/src', TRUE);
    $loader->addPsr4('Drupal\\aculta_image\\', dirname(__DIR__, 5) . '/modules/aculta_image/src', TRUE);

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

    $container->register('aculta_portal.domain_purpose', \stdClass::class);
    $container->register('aculta_file_uploader.unused_file_cleaner', \stdClass::class);
  }

  /**
   *
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('profile');
    $this->installEntitySchema('aculta_friendship');
    $this->installSchema('user', ['users_data']);
    $this->installSchema('aculta_user', ['aculta_user_nickname', 'aculta_user_agreement']);
    $this->installConfig(['system', 'user', 'filter', 'profile']);

    ProfileType::create(['id' => 'authenticated', 'label' => 'Perfil autenticado'])->save();
    foreach (['field_first_name', 'field_nickname', 'field_perfil_publico'] as $fieldName) {
      $type = $fieldName === 'field_perfil_publico' ? 'boolean' : 'string';
      FieldStorageConfig::create([
        'entity_type' => 'profile',
        'field_name' => $fieldName,
        'type' => $type,
      ])->save();
      FieldConfig::create([
        'entity_type' => 'profile',
        'bundle' => 'authenticated',
        'field_name' => $fieldName,
        'label' => $fieldName,
      ])->save();
    }
  }

  /**
   *
   */
  public function testAcceptedConnectionsAndCandidateSearch(): void {
    $u1 = User::create(['name' => 'user1', 'status' => 1]);
    $u1->save();
    $u2 = User::create(['name' => 'user2', 'status' => 1]);
    $u2->save();
    $u3 = User::create(['name' => 'user3', 'status' => 1]);
    $u3->save();

    // Profiles.
    Profile::create([
      'type' => 'authenticated',
      'uid' => $u1->id(),
      'field_nickname' => 'Alfa',
      'field_first_name' => 'Alfa User',
      'field_perfil_publico' => 1,
      'status' => 1,
    ])->save();

    Profile::create([
      'type' => 'authenticated',
      'uid' => $u2->id(),
      'field_nickname' => 'Beta',
      'field_first_name' => 'Beta User',
      'field_perfil_publico' => 1,
      'status' => 1,
    ])->save();

    Profile::create([
      'type' => 'authenticated',
      'uid' => $u3->id(),
      'field_nickname' => 'Gama',
      'field_first_name' => 'Gama User',
      'field_perfil_publico' => 1,
      'status' => 1,
    ])->save();

    // Friendship between 1 and 2: accepted.
    AcultaFriendship::create([
      'id' => '1:2',
      'member_low' => (int) $u1->id(),
      'member_high' => (int) $u2->id(),
      'requester' => (int) $u1->id(),
      'recipient' => (int) $u2->id(),
      'status' => FriendshipStatus::Accepted->value,
    ])->save();

    // Friendship between 1 and 3: pending sent by 1.
    AcultaFriendship::create([
      'id' => '1:3',
      'member_low' => (int) $u1->id(),
      'member_high' => (int) $u3->id(),
      'requester' => (int) $u1->id(),
      'recipient' => (int) $u3->id(),
      'status' => FriendshipStatus::Pending->value,
    ])->save();

    $currentUser = $this->container->get('current_user');
    $currentUser->setAccount($u1);

    $friendshipManager = new FriendshipManager(
      $this->container->get('entity_type.manager'),
      $currentUser,
      $this->container->get('lock.persistent'),
      $this->container->get('flood'),
      $this->container->get('event_dispatcher')
    );

    $mockIdentity = $this->createMock(PublicMemberIdentityInterface::class);
    $mockIdentity->method('publicDisplayName')->willReturnCallback(function (UserInterface $user): ?string {
      return match ((int) $user->id()) {
        1 => 'Alfa',
        2 => 'Beta',
        3 => 'Gama',
        default => NULL,
      };
    });

    $service = new ConnectionQueryService(
      $this->container->get('entity_type.manager'),
      $currentUser,
      $this->container->get('database'),
      $friendshipManager,
      NULL,
      $mockIdentity
    );

    // 1. Get accepted connections for user 1
    $connections = $service->getAcceptedConnections((int) $u1->id(), limit: 5, offset: 0);
    $this->assertSame(1, $connections['total']);
    $this->assertCount(1, $connections['items']);
    $this->assertSame((int) $u2->id(), $connections['items'][0]['uid']);
    $this->assertSame('Beta', $connections['items'][0]['name']);
    $this->assertFalse($connections['has_more']);

    // 2. Search candidates for "User"
    $search = $service->searchCandidates('User', limit: 10, offset: 0);
    $this->assertSame(2, $search['total']);
    $this->assertCount(2, $search['items']);

    // Sorted by name: Beta (2), Gama (3)
    $this->assertSame((int) $u2->id(), $search['items'][0]['uid']);
    $this->assertSame('connected', $search['items'][0]['state']);

    $this->assertSame((int) $u3->id(), $search['items'][1]['uid']);
    $this->assertSame('pending_sent', $search['items'][1]['state']);
  }

}
