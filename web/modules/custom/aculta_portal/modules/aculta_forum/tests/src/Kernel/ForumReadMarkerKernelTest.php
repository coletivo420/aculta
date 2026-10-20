<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Service\ForumReadMarker;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\domain\Entity\Domain;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies the Forum History batch against Drupal entity and SQL storage.
 */
#[CoversClass(ForumReadMarker::class)]
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumReadMarkerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc} */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'domain',
    'history',
  ];

  /**
   * Only accessible published topics in the Forum domain are marked.
   */
  public function testOnlyAccessibleForumTopicsAreMarkedForCurrentAccount(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('history', ['history']);
    $this->installConfig(['system', 'user', 'node', 'domain', 'history']);

    $forumDomain = Domain::create([
      'id' => 'forum_aculta_org',
      'hostname' => 'forum.example.test',
      'name' => 'Forum test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => FALSE,
    ]);
    $forumDomain->save();
    Domain::create([
      'id' => 'aculta_org',
      'hostname' => 'main.example.test',
      'name' => 'Main test domain',
      'scheme' => 'https',
      'status' => TRUE,
      'is_default' => TRUE,
    ])->save();

    NodeType::create(['type' => 'aculta_forum_topic', 'name' => 'Forum topic'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_domain_source',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'domain'],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'aculta_forum_topic',
      'field_name' => 'field_domain_source',
      'label' => 'Domain',
      'settings' => ['handler' => 'default:domain', 'handler_settings' => []],
    ])->save();

    $role = Role::load('authenticated');
    if (!$role instanceof Role) {
      $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated']);
    }
    $role->grantPermission('access content')->save();
    $account = User::create([
      'name' => 'forum-reader',
      'mail' => 'forum-reader@example.test',
      'status' => 1,
      'roles' => ['authenticated'],
    ]);
    $account->save();
    $currentUser = $this->container->get('current_user');
    $currentUser->setAccount($account);

    $publicTopic = $this->createTopic((string) $forumDomain->id());
    $this->createTopic('aculta_org');
    $this->createTopic((string) $forumDomain->id(), FALSE);

    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($forumDomain);
    $purposeManager = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $marker = new ForumReadMarker(
      $this->container->get('entity_type.manager'),
      $purposeManager,
      $currentUser,
      $this->container->get('string_translation'),
      $this->container->get('messenger'),
    );

    self::assertSame(1, $marker->countEligibleTopics());
    $context = [];
    while (($context['finished'] ?? 0) < 1) {
      $marker->markBatch((int) $account->id(), 1, $context);
    }
    $rows = $this->container->get('database')->select('history', 'h')
      ->fields('h', ['uid', 'nid'])
      ->execute()
      ->fetchAllKeyed();
    self::assertSame(
      [(int) $account->id() => (int) $publicTopic->id()],
      array_map('intval', $rows),
    );
  }

  /**
   * Creates a test topic. */
  private function createTopic(string|int $domainId, bool $published = TRUE): Node {
    $topic = Node::create([
      'type' => 'aculta_forum_topic',
      'title' => 'Topic ' . $domainId . ' ' . (int) $published,
      'status' => $published ? 1 : 0,
      'field_domain_source' => ['target_id' => (string) $domainId],
    ]);
    $topic->save();
    return $topic;
  }

}
