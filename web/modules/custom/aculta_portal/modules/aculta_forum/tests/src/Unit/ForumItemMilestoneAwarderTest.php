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

  /**
   * Abaixo de 10 curtidas o item não ganha marco.
   */
  public function testBelowFirstThresholdEarnsNothing(): void {
    $this->assertSame([], ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_TOPIC, 9));
  }

  /**
   * Dez curtidas em tópico concedem o marco Nice Topic.
   */
  public function testTenLikesOnTopicEarnsNiceTopic(): void {
    $this->assertSame(
      [BadgeCatalog::TOPICO_LEGAL_FORUM],
      ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_TOPIC, 10),
    );
  }

  /**
   * Vinte e cinco curtidas em resposta concedem Nice e Good Reply, cumulativamente.
   */
  public function testTwentyFiveLikesOnReplyEarnsNiceAndGood(): void {
    $this->assertSame(
      [BadgeCatalog::RESPOSTA_LEGAL_FORUM, BadgeCatalog::RESPOSTA_BOA_FORUM],
      ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_REPLY, 25),
    );
  }

  /**
   * Cinquenta curtidas em tópico concedem todos os marcos de tópico.
   */
  public function testFiftyLikesEarnsEveryTierForItsKind(): void {
    $this->assertSame(
      [BadgeCatalog::TOPICO_LEGAL_FORUM, BadgeCatalog::TOPICO_BOM_FORUM, BadgeCatalog::TOPICO_EXCELENTE_FORUM],
      ForumItemMilestoneAwarder::badgesForScore(ForumItemMilestoneAwarder::KIND_TOPIC, 50),
    );
  }

  /**
   * Tipo de item fora de tópico e resposta nunca ganha marco.
   */
  public function testUnknownKindEarnsNothing(): void {
    $this->assertSame([], ForumItemMilestoneAwarder::badgesForScore('article', 500));
  }

}
