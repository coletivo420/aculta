<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Locks the cross-card update contract after HTMX reaction changes.
 */
#[Group('aculta_forum')]
final class ForumReactionCounterSyncContractTest extends UnitTestCase {

  private function source(string $path): string {
    $module = dirname(__DIR__, 3);
    $repository = dirname($module, 6);
    $file = str_starts_with($path, 'web/') ? $repository . '/' . $path : $module . '/' . $path;
    $contents = file_get_contents($file);
    self::assertIsString($contents, $file);
    return $contents;
  }

  public function testBuilderCarriesServerComputedCounters(): void {
    $builder = $this->source('src/Service/DiscussionReactionBuilder.php');
    foreach (["'author_reactions_received'", "'viewer_reactions_given'", "'aculta_forum/reaction-counter-sync'", 'aculta_user_reactions:'] as $contract) {
      self::assertStringContainsString($contract, $builder);
    }

    $bar = $this->source('web/themes/custom/aculta420_v2/components/discussion-reaction-bar/discussion-reaction-bar.twig');
    foreach (['data-author-uid=', 'data-author-reactions-received=', 'data-viewer-reactions-given=', 'data-reaction-total=', 'name="reaction" value="undo"', "name: 'thumbs-down-medium'"] as $contract) {
      self::assertStringContainsString($contract, $bar);
    }
  }

  public function testHeadersAndDuplicateBarsUseSameMessageIdentity(): void {
    $hook = $this->source('src/Hook/DiscussionReactionEntityViewHooks.php');
    self::assertStringContainsString("'total_count' => (int) (\$barProps['total_count'] ?? 0)", $hook);
    self::assertStringContainsString(" . '-header'", $hook);
    self::assertStringContainsString("\$totals[\$cid] = \$reactions['total_count']", $hook);

    $received = $this->source('web/themes/custom/aculta420_v2/components/discussion-reactions-received/discussion-reactions-received.twig');
    self::assertStringContainsString('data-entity-type=', $received);
    self::assertStringContainsString('data-entity-id=', $received);
  }

  public function testClientUsesHtmlInsteadOfExtraFetch(): void {
    $js = $this->source('js/reaction-counter-sync.js');
    foreach (['htmx:afterSwap', 'copyReactionBreakdown', 'updateAuthorCards', 'data-discussion-reactions-received'] as $contract) {
      self::assertStringContainsString($contract, $js);
    }
    self::assertStringNotContainsString('fetch(', $js);
    self::assertStringNotContainsString('localStorage', $js);

    $card = $this->source('web/themes/custom/aculta420_v2/components/forum-post-card/forum-post-card.twig');
    self::assertStringContainsString('data-forum-author-reactions-received', $card);
  }

}
