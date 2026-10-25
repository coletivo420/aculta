<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\aculta_game\ForumParticipationPolicy;
use Drupal\aculta_portal\Profile\PerfilDoPapel;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\node\NodeInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Avaliação única das conquistas automáticas do Portal.
 *
 * Cada regra concede no fato que a cumpre; a concessão é idempotente.
 * Conquistas automáticas nunca são revogadas.
 *
 * Forum is optional: only current ACULTA public discussions match.
 */
final class BadgeEvaluationHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.badge_service')]
    private readonly BadgeService $badges,
    #[Autowire(service: 'aculta_game.forum_participation_policy')]
    private readonly ForumParticipationPolicy $forumParticipation,
  ) {}

  /**
   * Avalia uma resposta publicada em discussão compatível com o Fórum.
   */
  #[Hook('comment_insert')]
  #[Hook('comment_update')]
  public function commentInsert(CommentInterface $comment): void {
    $topic = $comment->getCommentedEntity();
    if (!$topic instanceof NodeInterface
      || !$this->forumParticipation->isEligible($comment, $topic)) {
      return;
    }
    $this->badges->awardForTrigger((int) $comment->getOwnerId(), 'forum_comment');
  }

  /**
   * Avalia a primeira publicação de um post revisionável do Growroom.
   */
  #[Hook('entity_insert')]
  #[Hook('entity_update')]
  public function forumPostSave(EntityInterface $entity): void {
    if (!$entity instanceof EntityOwnerInterface
      || !$this->forumParticipation->isEligiblePost($entity)) {
      return;
    }

    $this->badges->awardForTrigger((int) $entity->getOwnerId(), 'forum_comment');
  }

  /**
   * Primeira edição na Wiki: a pessoa criou uma revisão de verbete.
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    if (!$this->isLive($node) || $node->bundle() !== 'wiki_entry' || !$node->isPublished() || !$node->isDefaultRevision()) {
      return;
    }
    $original = $node->getOriginal();
    // Uma nova revisão publicada ou a aprovação posterior de uma revisão.
    if (!$node->isNewRevision() && !($original !== NULL && !$original->isPublished())) {
      return;
    }
    $uid = (int) $node->getRevisionUserId();
    if ($uid > 0) {
      $this->badges->awardForTrigger($uid, 'wiki_revision');
    }
  }

  /**
   * Perfil básico: apelido, nome, sobrenome e WhatsApp preenchidos.
   */
  #[Hook('profile_update')]
  public function profileUpdate(ProfileInterface $profile): void {
    $this->profileBasic($profile);
  }

  /**
   * Avalia o perfil básico após sua criação.
   */
  #[Hook('profile_insert')]
  public function profileInsert(ProfileInterface $profile): void {
    $this->profileBasic($profile);
  }

  /**
   * Pedido concluído (apoio ou pagamento) concede Apoiador.
   */
  #[Hook('commerce_order_update')]
  #[Hook('commerce_order_insert')]
  public function orderUpdate(OrderInterface $order): void {
    if (!$this->isLive($order) || $order->getState()->getId() !== 'completed') {
      return;
    }
    $original = $order->getOriginal();
    if ($original !== NULL && $original->getState()->getId() === 'completed') {
      return;
    }
    $this->badges->awardForTrigger((int) $order->getCustomerId(), 'order_completed');
    // Commerce Donation Flow registra apoio como order item donation.
    foreach ($order->getItems() as $item) {
      if ($item->bundle() === 'donation') {
        $this->badges->awardForTrigger((int) $order->getCustomerId(), 'donation_completed');
        break;
      }
    }
  }

  /**
   * Curso concluído com status "passed" no LMS.
   */
  #[Hook('lms_course_status_update')]
  #[Hook('lms_course_status_insert')]
  public function courseStatusUpdate(CourseStatusInterface $status): void {
    if (!$this->isLive($status) || $status->getStatus() !== CourseStatusInterface::STATUS_PASSED) {
      return;
    }
    $original = $status->getOriginal();
    if ($original !== NULL && $original->getStatus() === CourseStatusInterface::STATUS_PASSED) {
      return;
    }
    $this->badges->awardForTrigger((int) $status->getUserId(), 'course_passed');
  }

  /**
   * Verifica os campos exigidos para o marco de perfil básico.
   */
  private function profileBasic(ProfileInterface $profile): void {
    if (!$this->isLive($profile) || !PerfilDoPapel::ehTipoDePapel($profile)) {
      return;
    }
    foreach (['field_nickname', 'field_first_name', 'field_last_name', 'field_whatsapp'] as $field) {
      if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) {
        return;
      }
    }
    $this->badges->awardForTrigger((int) $profile->getOwnerId(), 'profile_basic');
  }

  /**
   * O Migrate Core marca entidades como syncing; não premiar restauração.
   */
  private function isLive(EntityInterface $entity): bool {
    return !($entity instanceof SynchronizableInterface && $entity->isSyncing());
  }

}
