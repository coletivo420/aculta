<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisoService;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicy;
use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
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
    $policy = new ForumNoticeAccessPolicy(
      $this->createMock(EntityTypeManagerInterface::class),
      new DomainPurposeManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
    );
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->willReturn(FALSE);
    $this->assertTrue($policy->supportsTopic($topic));

    $legacy = $this->createMock(NodeInterface::class);
    $legacy->method('bundle')->willReturn('forum');
    $this->assertFalse($policy->supportsTopic($legacy));
  }

  /**
   * Historical Growroom authors on an ACULTA topic do not receive notices.
   */
  public function testHistoricalAuthorTopicIsNotSupported(): void {
    $policy = new ForumNoticeAccessPolicy(
      $this->createMock(EntityTypeManagerInterface::class),
      new DomainPurposeManager(
        $this->createMock(EntityTypeManagerInterface::class),
        $this->createMock(DomainNegotiatorInterface::class),
        $this->createMock(ConfigFactoryInterface::class),
        new RequestStack(),
      ),
    );
    $topic = $this->createMock(NodeInterface::class);
    $topic->method('bundle')->willReturn('aculta_forum_topic');
    $topic->method('hasField')->with('field_autor_legado')->willReturn(TRUE);
    $legacyAuthor = $this->createMock(FieldItemListInterface::class);
    $legacyAuthor->method('isEmpty')->willReturn(FALSE);
    $topic->method('get')->with('field_autor_legado')->willReturn($legacyAuthor);

    $this->assertFalse($policy->supportsTopic($topic));
  }

}
