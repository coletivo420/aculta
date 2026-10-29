<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Kernel;

use Drupal\aculta_studio\Controller\SavedContentController;
use Drupal\aculta_studio\Recommendation\RecommendationService;
use Drupal\aculta_studio\Saved\SavedContentService;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\flag\Entity\Flag;
use Drupal\flag\Entity\Flagging;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates saved content, account isolation, transparent recommendations and cache.
 */
#[Group('aculta_studio')]
#[RunTestsInSeparateProcesses]
final class SavedAndRecommendationsKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'taxonomy',
    'flag',
    'aculta_studio',
  ];

  protected SavedContentService $savedContentService;
  protected RecommendationService $recommendationService;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $root = dirname(__DIR__, 9);
    $loader = require $root . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_studio\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_studio/src');

    // Supply dummy domain_purpose service required by MarleyListingBuilder.
    $container->register('aculta_portal.domain_purpose', \stdClass::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('flagging');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['system', 'filter', 'node', 'flag']);

    $nodeType = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $nodeType->save();

    // Create taxonomy vocabulary and term reference field.
    $vocab = Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);
    $vocab->save();

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ]);
    $fieldStorage->save();

    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
      'label' => 'Tags',
    ]);
    $field->save();

    // Create test flags.
    $flagSaved = Flag::create([
      'id' => 'salvar_topico',
      'label' => 'Salvar tópico',
      'entity_type' => 'node',
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'bundles' => ['article'],
    ]);
    $flagSaved->save();

    $flagFollow = Flag::create([
      'id' => 'seguir_topico',
      'label' => 'Seguir tópico',
      'entity_type' => 'node',
      'flag_type' => 'entity:node',
      'link_type' => 'reload',
      'bundles' => ['article'],
    ]);
    $flagFollow->save();

    // Create member role with basic content access permissions.
    $role = \Drupal\user\Entity\Role::create([
      'id' => 'member',
      'label' => 'Member',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('access aculta portal');
    $role->save();

    // Occupy UID 1 so test accounts are normal unprivileged users.
    $admin = User::create(['name' => 'admin', 'status' => 1]);
    $admin->save();

    $this->savedContentService = $this->container->get('aculta_studio.saved_content');
    $this->recommendationService = $this->container->get('aculta_studio.recommendations');
  }

  /**
   * Proves bookmarks and followed items are isolated per account with no data leaks.
   */
  public function testSavedItemsIsolationBetweenAccounts(): void {
    $userA = User::create(['name' => 'user_a', 'status' => 1, 'roles' => ['member']]);
    $userA->save();

    $userB = User::create(['name' => 'user_b', 'status' => 1, 'roles' => ['member']]);
    $userB->save();

    $node1 = Node::create(['type' => 'article', 'title' => 'Nó 1 - Salvo A', 'status' => 1]);
    $node1->save();
    $node2 = Node::create(['type' => 'article', 'title' => 'Nó 2 - Seguido A', 'status' => 1]);
    $node2->save();
    $node3 = Node::create(['type' => 'article', 'title' => 'Nó 3 - Salvo B', 'status' => 1]);
    $node3->save();

    // User A flags Node 1 (salvar_topico) and Node 2 (seguir_topico).
    $flaggingA1 = Flagging::create([
      'flag_id' => 'salvar_topico',
      'entity_type' => 'node',
      'entity_id' => $node1->id(),
      'uid' => $userA->id(),
    ]);
    $flaggingA1->save();

    $flaggingA2 = Flagging::create([
      'flag_id' => 'seguir_topico',
      'entity_type' => 'node',
      'entity_id' => $node2->id(),
      'uid' => $userA->id(),
    ]);
    $flaggingA2->save();

    // User B flags Node 3 (salvar_topico).
    $flaggingB1 = Flagging::create([
      'flag_id' => 'salvar_topico',
      'entity_type' => 'node',
      'entity_id' => $node3->id(),
      'uid' => $userB->id(),
    ]);
    $flaggingB1->save();

    // Test User A queries.
    $itemsA = $this->savedContentService->getSavedItems($userA, 'all');
    $this->assertCount(2, $itemsA);
    $idsA = array_map(fn($item) => (int) $item['entity_id'], $itemsA);
    $this->assertContains((int) $node1->id(), $idsA);
    $this->assertContains((int) $node2->id(), $idsA);
    $this->assertNotContains((int) $node3->id(), $idsA);

    $savedOnlyA = $this->savedContentService->getSavedItems($userA, 'saved');
    $this->assertCount(1, $savedOnlyA);
    $this->assertSame((int) $node1->id(), (int) $savedOnlyA[0]['entity_id']);

    $followingOnlyA = $this->savedContentService->getSavedItems($userA, 'following');
    $this->assertCount(1, $followingOnlyA);
    $this->assertSame((int) $node2->id(), (int) $followingOnlyA[0]['entity_id']);

    $countsA = $this->savedContentService->getSavedCounts($userA);
    $this->assertSame(1, $countsA['saved']);
    $this->assertSame(1, $countsA['following']);
    $this->assertSame(2, $countsA['total']);

    // Test User B queries (strictly isolated).
    $itemsB = $this->savedContentService->getSavedItems($userB, 'all');
    $this->assertCount(1, $itemsB);
    $this->assertSame((int) $node3->id(), (int) $itemsB[0]['entity_id']);
    $this->assertNotContains((int) $node1->id(), array_map(fn($i) => (int) $i['entity_id'], $itemsB));
    $this->assertNotContains((int) $node2->id(), array_map(fn($i) => (int) $i['entity_id'], $itemsB));

    $countsB = $this->savedContentService->getSavedCounts($userB);
    $this->assertSame(1, $countsB['saved']);
    $this->assertSame(0, $countsB['following']);
  }

  /**
   * Proves unpublished or restricted entities are never exposed in saved lists.
   */
  public function testSavedItemsAccessControlEnforcement(): void {
    $user = User::create(['name' => 'viewer', 'status' => 1, 'roles' => ['member']]);
    $user->save();

    $nodePrivate = Node::create(['type' => 'article', 'title' => 'Nó Despublicado', 'status' => 0]);
    $nodePrivate->save();

    $flagging = Flagging::create([
      'flag_id' => 'salvar_topico',
      'entity_type' => 'node',
      'entity_id' => $nodePrivate->id(),
      'uid' => $user->id(),
    ]);
    $flagging->save();

    $items = $this->savedContentService->getSavedItems($user, 'saved');
    $this->assertEmpty($items, 'Unpublished node must be filtered out by view access check.');
  }

  /**
   * Proves recommendations provide transparent, contextual reasons based on user interests.
   */
  public function testTransparentRecommendations(): void {
    $user = User::create(['name' => 'culture_fan', 'status' => 1, 'roles' => ['member']]);
    $user->save();

    $term = Term::create(['vid' => 'tags', 'name' => 'Cinema']);
    $term->save();

    $nodeSaved = Node::create([
      'type' => 'article',
      'title' => 'Festival de Cinema',
      'status' => 1,
      'field_tags' => [$term->id()],
    ]);
    $nodeSaved->save();

    $nodeRelated = Node::create([
      'type' => 'article',
      'title' => 'Mostra Audiovisual',
      'status' => 1,
      'field_tags' => [$term->id()],
    ]);
    $nodeRelated->save();

    // Flag nodeSaved as saved by user.
    $flagging = Flagging::create([
      'flag_id' => 'salvar_topico',
      'entity_type' => 'node',
      'entity_id' => $nodeSaved->id(),
      'uid' => $user->id(),
    ]);
    $flagging->save();

    $recs = $this->recommendationService->getRecommendations($user, 5);
    $this->assertNotEmpty($recs);

    // Verify the already saved item is NOT recommended to avoid duplication.
    $recIds = array_map(fn($r) => (int) $r['entity_id'], $recs);
    $this->assertNotContains((int) $nodeSaved->id(), $recIds);

    // Verify nodeRelated is recommended with an explicit reason.
    $this->assertContains((int) $nodeRelated->id(), $recIds);
    $found = FALSE;
    foreach ($recs as $rec) {
      if ((int) $rec['entity_id'] === (int) $nodeRelated->id()) {
        $this->assertStringContainsString('Cinema', $rec['reason']);
        $found = TRUE;
      }
    }
    $this->assertTrue($found, 'Explicit transparent reason referencing "Cinema" must be present.');
  }

  /**
   * Proves controller page renders with user cache context and proper structure.
   */
  public function testSavedControllerRenderingAndCacheContexts(): void {
    $user = User::create(['name' => 'studio_user', 'status' => 1, 'roles' => ['member']]);
    $user->save();

    // Mock request with session and tab=salvos.
    $request = Request::create('/studio/salvos', 'GET', ['tab' => 'salvos']);
    $session = new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
    $request->setSession($session);
    $this->container->get('request_stack')->push($request);

    $this->setCurrentUser($user);

    /** @var \Drupal\aculta_studio\Controller\SavedContentController $controller */
    $controller = $this->container->get('aculta_studio.saved_controller');
    $build = $controller->page();

    $this->assertSame('container', $build['#type']);
    $this->assertSame('studio-saved-content', $build['#attributes']['data-portal-ui']);
    $this->assertContains('user', $build['#cache']['contexts']);
    $this->assertContains('url.query_args:tab', $build['#cache']['contexts']);
    $this->assertContains('flagging_list', $build['#cache']['tags']);
  }

}
