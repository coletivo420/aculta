<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Kernel;

use Drupal\aculta_forum\Access\ForumPostModerationAccess;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\domain\Entity\Domain;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Proves the Forum moderation queue is limited to the main purpose.
 */
#[Group('aculta_forum')]
#[RunTestsInSeparateProcesses]
final class ForumPostModerationAccessKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'domain',
  ];

  /**
   * Purpose manager used by the access check.
   */
  private DomainPurposeManager $purpose;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'user', 'node', 'domain']);
    foreach ([
      'aculta_org' => 'main.example.test',
      'forum_aculta_org' => 'forum.example.test',
    ] as $id => $hostname) {
      Domain::create([
        'id' => $id,
        'hostname' => $hostname,
        'name' => $id,
        'scheme' => 'https',
        'status' => TRUE,
        'is_default' => $id === 'aculta_org',
      ])->save();
    }
    $this->purpose = new DomainPurposeManager(
      $this->container->get('entity_type.manager'),
      $this->container->get('domain.negotiator'),
      $this->container->get('config.factory'),
      new RequestStack(),
    );
  }

  /**
   * The queue is allowed only while the active purpose is main.
   */
  public function testQueueRequiresMainPurpose(): void {
    $access = new ForumPostModerationAccess(
      $this->container->get('entity_type.manager'),
      $this->purpose,
    );

    $main = $this->purpose->runInPurpose('main', fn () => $access->queueAccess());
    self::assertTrue($main->isAllowed(), 'No purpose main a fila é permitida.');

    $forum = $this->purpose->runInPurpose('forum', fn () => $access->queueAccess());
    self::assertFalse($forum->isAllowed(), 'No purpose forum a fila não é liberada.');

  }

}
