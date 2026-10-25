<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit;

use Drupal\aculta_user\Participation\PublishedParticipationEvidence;
use Drupal\aculta_user\Participation\PublishedParticipationLookup;
use Drupal\aculta_user\Participation\PublishedParticipationLookupInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests context isolation and conservative failure of participation lookup.
 */
#[Group('aculta_user')]
final class PublishedParticipationLookupTest extends TestCase {

  /**
   * Only the provider for the requested context supplies evidence.
   */
  public function testLookupDoesNotCombineSeparateContexts(): void {
    $discussion = $this->provider('public_discussion', TRUE);
    $wiki = $this->provider('wiki_entry', FALSE);
    $lookup = new PublishedParticipationLookup([$discussion, $wiki]);

    $evidence = $lookup->lookup(42, 'wiki_entry');

    self::assertTrue($evidence->isReliable());
    self::assertFalse($evidence->hasPublishedContribution());
    self::assertTrue($evidence->requiresReview());
  }

  /**
   * Missing and duplicate providers fail closed without caching the failure.
   */
  public function testMissingOrDuplicateProviderIsUnavailable(): void {
    $provider = $this->provider('wiki_entry', TRUE);

    foreach ([[], [$provider, $this->provider('wiki_entry', TRUE)]] as $providers) {
      $evidence = (new PublishedParticipationLookup($providers))->lookup(42, 'wiki_entry');
      self::assertFalse($evidence->isReliable());
      self::assertFalse($evidence->hasPublishedContribution());
      self::assertTrue($evidence->requiresReview());
      self::assertSame(0, $evidence->getCacheMaxAge());
    }
  }

  /**
   * Provider exceptions and invalid identifiers never count as publication.
   */
  public function testProviderFailureAndInvalidAccountFailClosed(): void {
    $failing = new class() implements PublishedParticipationLookupInterface {

      /** Returns the context provided by this fixture. */
      public function getContextId(): string {
        return 'wiki_entry';
      }

      /** Simulates an unavailable provider query. */
      public function lookup(int $accountId): PublishedParticipationEvidence {
        throw new \RuntimeException('Lookup unavailable.');
      }

    };
    $lookup = new PublishedParticipationLookup([$failing]);

    foreach ([$lookup->lookup(42, 'wiki_entry'), $lookup->lookup(0, 'wiki_entry')] as $evidence) {
      self::assertFalse($evidence->isReliable());
      self::assertFalse($evidence->hasPublishedContribution());
      self::assertTrue($evidence->requiresReview());
      self::assertSame(0, $evidence->getCacheMaxAge());
    }
  }

  /**
   * Creates a deterministic provider for one context.
   */
  private function provider(string $contextId, bool $hasPublished): PublishedParticipationLookupInterface {
    return new class($contextId, $hasPublished) implements PublishedParticipationLookupInterface {

      public function __construct(
        private readonly string $contextId,
        private readonly bool $hasPublished,
      ) {}

      /** Returns the provider context. */
      public function getContextId(): string {
        return $this->contextId;
      }

      /** Returns the configured aggregate evidence. */
      public function lookup(int $accountId): PublishedParticipationEvidence {
        return PublishedParticipationEvidence::known($this->hasPublished, ['user'], ['node_list:wiki_entry']);
      }

    };
  }

}
