<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\aculta_user\Badge\BadgeCatalog;
use Drupal\aculta_user\Badge\BadgeService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\comment\CommentInterface;
use Drupal\node\NodeInterface;
use Drupal\profile\Entity\ProfileInterface;

/**
 * Avaliação única das conquistas automáticas do Portal.
 *
 * Cada regra concede no fato que a cumpre; a concessão é idempotente. Automáticas nunca são revogadas.
 */
final class BadgeEvaluationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.badge_service')]
    private readonly BadgeService $badges,
  ) {}

  /** Primeiro comentário publicado no Fórum. */
  #[Hook('comment_insert')]
  public function commentInsert(CommentInterface $comment): void {
    if ($comment->bundle() !== 'comment_forum' || !$comment->isPublished()) {
      return;
    }
    $this->badges->award((int) $comment->getOwnerId(), BadgeCatalog::PRIMEIRO_COMENTARIO);
  }

  /** Primeira edição na Wiki: a pessoa criou uma revisão de verbete. */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    if ($node->bundle() !== 'wiki_entry') {
      return;
    }
    $uid = (int) $node->getRevisionUserId();
    if ($uid > 0) {
      $this->badges->award($uid, BadgeCatalog::PRIMEIRA_EDICAO_WIKI);
    }
  }

  /** Perfil básico: apelido, nome, sobrenome e WhatsApp preenchidos. */
  #[Hook('profile_update')]
  public function profileUpdate(ProfileInterface $profile): void {
    $this->profileBasic($profile);
  }

  #[Hook('profile_insert')]
  public function profileInsert(ProfileInterface $profile): void {
    $this->profileBasic($profile);
  }

  /** Pedido concluído (apoio ou pagamento) concede Apoiador. */
  #[Hook('commerce_order_update')]
  public function orderUpdate(EntityInterface $order): void {
    if (!method_exists($order, 'getState') || $order->getState()->getId() !== 'completed') {
      return;
    }
    $this->badges->award((int) $order->getCustomerId(), BadgeCatalog::APOIADOR);
  }

  /** Curso concluído com status "passed" no LMS. */
  #[Hook('lms_course_status_update')]
  public function courseStatusUpdate(EntityInterface $status): void {
    if (!method_exists($status, 'getStatus') || $status->getStatus() !== 'passed') {
      return;
    }
    $this->badges->award((int) $status->getOwnerId(), BadgeCatalog::PRIMEIRO_CURSO);
  }

  private function profileBasic(ProfileInterface $profile): void {
    if ($profile->bundle() !== 'participante') {
      return;
    }
    foreach (['field_nickname', 'field_first_name', 'field_last_name', 'field_whatsapp'] as $field) {
      if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) {
        return;
      }
    }
    $this->badges->award((int) $profile->getOwnerId(), BadgeCatalog::PERFIL_BASICO);
  }

}
