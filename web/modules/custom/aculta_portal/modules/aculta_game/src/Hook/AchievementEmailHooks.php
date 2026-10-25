<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\aculta_user\Entity\BadgeAward;

/**
 * Queues the separate achievement email after the award is committed. */
final class AchievementEmailHooks {

  public function __construct(
    private readonly QueueFactory $queues,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
 * Queues the email without creating another site notification. */
  #[Hook('aculta_badge_award_insert')]
  public function awardInserted(BadgeAward $award): void {
    if ($this->queues->get('aculta_game_achievement_email')->createItem(['award_id' => (int) $award->id()]) === FALSE) {
      $this->loggerFactory->get('aculta_game')->error('Não foi possível colocar o e-mail de conquista na fila. Concessão @id.', ['@id' => $award->id()]);
    }
  }

}
