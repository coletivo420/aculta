<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_forum\Context\DiscussionContextResolver;
use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\aculta_magazine\Controller\EditorialWorkspaceController;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests that configured capability does not grant latest-revision access.
 */
final class EditorialWorkspaceAccessTest extends UnitTestCase {

  /**
   * An author role cannot edit an entity when the latest revision is hidden.
   */
  public function testAuthoringCapabilityDoesNotBypassLatestRevisionAccess(): void {
    [$workspace, $node] = $this->workspace(EditorialCapability::AUTHORING, 'view revision');

    self::assertTrue($workspace->accessEdit($node)->isForbidden());
  }

  /**
   * A reviewer role cannot transition an entity when its revision is hidden.
   */
  public function testReviewCapabilityDoesNotBypassLatestRevisionAccess(): void {
    [$workspace, $node] = $this->workspace(EditorialCapability::REVIEW, 'view revision');

    self::assertTrue($workspace->accessReview($node)->isForbidden());
  }

  /**
   * Builds a workspace where only the selected capability grants ACULTA access.
   *
   * @return array{0: \Drupal\aculta_magazine\Controller\EditorialWorkspaceController, 1: \Drupal\node\NodeInterface}
   *   The workspace adapter and its canonical node.
   */
  private function workspace(string $capability, string $deniedOperation): array {
    $account = $this->createMock(AccountInterface::class);
    $account->method('getRoles')->willReturn(['authenticated', 'content_editor']);
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnCallback(static fn (string $key): array => $key === $capability . '_roles' ? ['content_editor'] : []);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('aculta_magazine.settings')->willReturn($config);

    $latest = $this->createMock(NodeInterface::class);
    $latest->method('access')->willReturnCallback(static fn (string $operation): AccessResult => $operation === $deniedOperation ? AccessResult::forbidden() : AccessResult::allowed());
    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn('10');
    $node->method('access')->willReturn(AccessResult::allowed());

    $storage = $this->createMock(NodeStorageInterface::class);
    $storage->method('getLatestRevisionId')->with('10')->willReturn('99');
    $storage->method('loadRevision')->with('99')->willReturn($latest);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('node')->willReturn($storage);

    $workspace = new EditorialWorkspaceController(
      $entities,
      $this->createMock(EntityFormBuilderInterface::class),
      $this->createMock(FormBuilderInterface::class),
      new DiscussionContextResolver([]),
      new EditorialCapability($configFactory),
      $account,
      new RequestStack(),
      $this->createMock(DateFormatterInterface::class),
    );
    return [$workspace, $node];
  }

}
