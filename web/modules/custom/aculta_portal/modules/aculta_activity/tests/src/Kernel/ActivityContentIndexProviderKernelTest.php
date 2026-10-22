<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_activity\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_portal\ContentIndex\ContentIndexProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Verifies Activity provider discovery and projection in the real container.
 */
#[Group('aculta_activity')]
#[RunTestsInSeparateProcesses]
final class ActivityContentIndexProviderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'aculta_index',
    'aculta_activity',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Provider discovery does not exercise Domain negotiation or access.
    $container->set('aculta_portal.domain_purpose', new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('aculta_index', [
      'aculta_index_generation',
      'aculta_index_state',
      'aculta_index_entry',
      'aculta_index_relation',
      'aculta_index_work',
      'aculta_index_cursor',
      'aculta_index_audit',
    ]);
    $this->installConfig(['user', 'node', 'system']);
    aculta_index_install();
    NodeType::create(['type' => 'activity', 'name' => 'Activity'])->save();
  }

  /**
   * Tests the tagged provider is discovered and projects an Activity node.
   */
  public function testTaggedProviderProjectsActivityNode(): void {
    $registry = $this->container->get('aculta_index.provider_registry');
    self::assertTrue($registry->has('activity'));
    $provider = $registry->get('activity');
    self::assertInstanceOf(ContentIndexProviderInterface::class, $provider);

    $node = Node::create([
      'type' => 'activity',
      'title' => 'Encontro de teste',
      'status' => TRUE,
      'created' => 100,
      'changed' => 200,
    ]);
    $node->save();
    $work = $this->container->get('aculta_index.work_manager')
      ->getStatus('activity', 'node', (string) $node->id(), 'en');
    self::assertSame('project', $work['operation']);

    $projection = $provider->project($node);
    self::assertNotNull($projection);
    self::assertSame('node', $projection->entityTypeId);
    self::assertSame('activity', $projection->bundle);
    self::assertSame((string) $node->id(), $projection->entityId);
    self::assertSame('en', $projection->langcode);
    self::assertSame('Encontro de teste', $projection->label);
    self::assertSame([], $projection->relations);
    self::assertSame('main', $provider->getAccessPurpose($node));
  }

}
