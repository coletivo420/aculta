<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Service;

use Drupal\aculta_forum\Event\ForumItemReactionEvent;
use Drupal\aculta_user\Badge\BadgeCatalog;
use Drupal\aculta_user\Badge\BadgeService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Concede os marcos Nice/Good/Great de Tópico e de Resposta (10/25/50 curtidas).
 *
 * O limiar usa o saldo do próprio item (curtidas positivas menos descurtidas, com
 * piso zero), nunca a soma de todos os posts do autor. Não conta autocurtida nem
 * contas inativas. Não revoga: a concessão é histórica, e Undo ou descurtida não
 * geram marco nem revogação.
 *
 * Desligado por padrão (aculta_forum.settings:item_milestones_enabled): a política
 * antiabuso dos limiares precisa ser aprovada antes de ativar.
 */
final class ForumItemMilestoneAwarder implements EventSubscriberInterface {

  public const KIND_TOPIC = 'topic';
  public const KIND_REPLY = 'reply';

  /**
   * Badges por tipo de item, indexadas pelo limiar de curtidas do item.
   */
  public const BADGES = [
    self::KIND_TOPIC => [
      10 => BadgeCatalog::TOPICO_LEGAL_FORUM,
      25 => BadgeCatalog::TOPICO_BOM_FORUM,
      50 => BadgeCatalog::TOPICO_EXCELENTE_FORUM,
    ],
    self::KIND_REPLY => [
      10 => BadgeCatalog::RESPOSTA_LEGAL_FORUM,
      25 => BadgeCatalog::RESPOSTA_BOA_FORUM,
      50 => BadgeCatalog::RESPOSTA_EXCELENTE_FORUM,
    ],
  ];

  public const CONFIG_KEY = 'item_milestones_enabled';

  public function __construct(
    private readonly Connection $database,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    // Opcional: sem o aculta_user não há conquistas para conceder, e o Fórum continua funcionando.
    #[Autowire(service: 'aculta_user.badge_service')]
    private readonly ?BadgeService $badges,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return array<string, string>
   *   Nome do evento mapeado ao método que o trata.
   */
  public static function getSubscribedEvents(): array {
    return [ForumItemReactionEvent::ADDED => 'onPositiveReaction'];
  }

  /**
   * Avalia o marco do item após uma curtida positiva nova.
   */
  public function onPositiveReaction(ForumItemReactionEvent $event): void {
    if (!(bool) $this->configFactory->get('aculta_forum.settings')->get(self::CONFIG_KEY)) {
      return;
    }
    if ($event->authorUid <= 0 || $event->authorUid === $event->uid) {
      return;
    }
    $kind = $this->kindOf($event->entityType, $event->entityId);
    if ($kind === NULL) {
      return;
    }
    $score = $this->score($event->entityType, $event->entityId, $event->authorUid);
    foreach (self::badgesForScore($kind, $score) as $badge) {
      $this->badges?->award($event->authorUid, $badge);
    }
  }

  /**
   * Retorna as badges cujo limiar o saldo atingiu para o tipo de item.
   *
   * @return list<string>
   *   Identificadores das badges elegíveis; a concessão é idempotente no BadgeService.
   */
  public static function badgesForScore(string $kind, int $score): array {
    $earned = [];
    foreach (self::BADGES[$kind] ?? [] as $threshold => $badge) {
      if ($score >= $threshold) {
        $earned[] = $badge;
      }
    }
    return $earned;
  }

  /**
   * Classifica o alvo como tópico ou resposta. Retorna NULL se não for item elegível.
   */
  private function kindOf(string $entityType, int $entityId): ?string {
    if ($entityId <= 0 || !$this->entityTypeManager->hasDefinition($entityType)) {
      return NULL;
    }
    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return NULL;
    }
    if ($entity instanceof EntityPublishedInterface && !$entity->isPublished()) {
      return NULL;
    }
    if ($entityType === 'node' && $entity->bundle() === 'aculta_forum_topic') {
      return self::KIND_TOPIC;
    }
    if ($entityType === 'aculta_forum_post' || $entityType === 'comment') {
      return self::KIND_REPLY;
    }
    return NULL;
  }

  /**
   * Saldo do item: curtidas públicas de contas ativas, menos descurtidas, sem autocurtida.
   */
  private function score(string $entityType, int $entityId, int $authorUid): int {
    $query = $this->database->select(DiscussionReactionManager::TABLE_REACTIONS, 'r')
      ->condition('r.entity_type', $entityType)
      ->condition('r.entity_id', $entityId)
      ->condition('r.is_public_context', 1)
      ->condition('r.uid', $authorUid, '<>');
    $query->innerJoin('users_field_data', 'u', 'u.uid = r.uid AND u.status = 1');
    $query->addExpression(
      'COALESCE(SUM(CASE WHEN r.reaction = :dislike THEN -1 ELSE 1 END), 0)',
      'score',
      [':dislike' => DiscussionReactionManager::DISLIKE_REACTION],
    );
    $value = $query->execute()?->fetchField();
    return max(0, is_numeric($value) ? (int) $value : 0);
  }

}
