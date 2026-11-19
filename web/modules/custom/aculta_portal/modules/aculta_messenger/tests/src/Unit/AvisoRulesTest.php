<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicy;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_forum\Origin\DiscussionOrigin;
use Drupal\aculta_forum\Origin\DiscussionOriginClassification;
use Drupal\aculta_forum\Origin\DiscussionOriginResolverInterface;
use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Regras puras de avisos por pessoa: deduplicação e intervalo do resumo.
 */
#[Group('aculta_messenger')]
class AvisoRulesTest extends UnitTestCase {

  /**
   * Comentário novo conta; repetição do mesmo comentário não conta.
   */
  public function testComentarioSoContaUmaVez(): void {
    $this->assertTrue(AvisoService::isNewComment(10, 0));
    $this->assertTrue(AvisoService::isNewComment(11, 10));
    $this->assertFalse(AvisoService::isNewComment(10, 10));
    $this->assertFalse(AvisoService::isNewComment(9, 10));
  }

  /**
   * Revisão nova conta; a mesma revisão não conta de novo.
   */
  public function testRevisaoSoContaUmaVez(): void {
    $this->assertTrue(AvisoService::isNewRevision(5, 0));
    $this->assertFalse(AvisoService::isNewRevision(5, 5));
    $this->assertFalse(AvisoService::isNewRevision(4, 5));
  }

  /**
   * O resumo não envia antes de 20 horas desde o último envio.
   */
  public function testResumoRespeitaIntervalo(): void {
    $agora = 1_000_000;
    $this->assertTrue(AvisosResumo::podeEnviar($agora, 0));
    $this->assertFalse(AvisosResumo::podeEnviar($agora, $agora - 3600));
    $this->assertTrue(AvisosResumo::podeEnviar($agora, $agora - AvisosResumo::INTERVALO_SEGUNDOS));
  }

  /**
   * Only current ACULTA public topics are eligible for follow notices.
   */
  public function testOnlyCurrentPublicForumBundleIsSupported(): void {
    $policy = $this->policy();
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $this->assertTrue($policy->supportsTopic($topic));

    $legacy = $this->createMock(NodeInterface::class);
    $legacy->method('bundle')->willReturn('forum');
    $this->assertFalse($policy->supportsTopic($legacy));
  }

  /**
   * Historical Growroom authors on an ACULTA topic do not receive notices.
   */
  public function testHistoricalAuthorTopicIsNotSupported(): void {
    $policy = $this->policy(DiscussionOrigin::ARCHIVE);
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $this->assertFalse($policy->supportsTopic($topic));
  }

  /**
   * Creates a forum notice policy fixture.
   */
  private function policy(string $origin = DiscussionOrigin::CURRENT): ForumNoticeAccessPolicy {
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $siteManager = new DomainSiteManager(
      $entityTypeManager,
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
    $originResolver = $this->createMock(DiscussionOriginResolverInterface::class);
    $originResolver->method('classify')->willReturn(
      DiscussionOriginClassification::from($origin, new CacheableMetadata()),
    );
    return new ForumNoticeAccessPolicy($entityTypeManager, $siteManager, $originResolver);
  }

}
