<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\ForumItemMilestoneAwarder;
use Drupal\aculta_user\Badge\BadgeCatalog;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the per-item milestone thresholds (10/25/50) without touching the database.
 */
#[Group('aculta_forum')]
final class ForumItemMilestoneAwarderTest extends UnitTestCase {

  public function testBelowFirstThresholdEarnsNothing(): void {
    $this->assertSame([], ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_TOPIC, 9));
  }

  public function testTenLikesOnTopicEarnsNiceTopic(): void {
    $this->assertSame(
      [BadgeCatalog::TOPICO_LEGAL_FORUM],
      ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_TOPIC, 10),
    );
  }

  public function testTwentyFiveLikesOnReplyEarnsNiceAndGood(): void {
    $this->assertSame(
      [BadgeCatalog::RESPOSTA_LEGAL_FORUM, BadgeCatalog::RESPOSTA_BOA_FORUM],
      ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_REPLY, 25),
    );
  }

  public function testFiftyLikesEarnsEveryTierForItsKind(): void {
    $this->assertSame(
      [BadgeCatalog::TOPICO_LEGAL_FORUM, BadgeCatalog::TOPICO_BOM_FORUM, BadgeCatalog::TOPICO_EXCELENTE_FORUM],
      ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_TOPIC, 50),
    );
  }

  public function testUnknownKindEarnsNothing(): void {
    $this->assertSame([], ForumItemMilestoneAwarder::badgesForScore('article', 500));
  }

}
