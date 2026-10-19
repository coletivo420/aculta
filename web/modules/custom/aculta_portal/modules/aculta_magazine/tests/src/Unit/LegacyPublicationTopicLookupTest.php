<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_magazine\Unit;

use Drupal\aculta_magazine\Forum\LegacyPublicationTopicLookup;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Ensures the historical-reference adapter cannot mutate legacy topics.
 */
#[Group('aculta_magazine')]
final class LegacyPublicationTopicLookupTest extends UnitTestCase {

  /**
   * The adapter exposes lookup methods only.
   */
  public function testPublicApiIsReadOnly(): void {
    $methods = array_map(
      static fn(\ReflectionMethod $method): string => $method->getName(),
      (new \ReflectionClass(LegacyPublicationTopicLookup::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
    );

    self::assertSame(['__construct', 'topicFor', 'openingPostFor'], $methods);
  }

}
