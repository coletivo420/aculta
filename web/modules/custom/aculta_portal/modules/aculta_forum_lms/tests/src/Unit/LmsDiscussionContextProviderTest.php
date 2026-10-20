<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_forum_lms\Context\LmsDiscussionContextProvider;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the LMS provider's strict host-bundle boundary.
 */
#[Group('aculta_forum_lms')]
final class LmsDiscussionContextProviderTest extends UnitTestCase {

  /**
   * Checks that public topics cannot be claimed by the LMS adapter.
   */
  public function testOnlyTheLmsTopicBundleIsSupported(): void {
    $provider = new LmsDiscussionContextProvider(
      $this->createMock(EntityTypeManagerInterface::class),
    );

    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_lms_topic');
    self::assertTrue($provider->supports($topic));

    $publicTopic = $this->createMock(NodeInterface::class);
    $publicTopic->method('bundle')->willReturn('aculta_forum_topic');
    self::assertFalse($provider->supports($publicTopic));

    self::assertFalse($provider->supports($this->createMock(EntityInterface::class)));
  }

}
