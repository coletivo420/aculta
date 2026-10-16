<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\aculta_user\Entity\BadgeAward;
use Drupal\user\UserDataInterface;

/** Queues one email for each newly granted achievement. */
final class AchievementNotificationHooks {

  public function __construct(
    private readonly QueueFactory $queues,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly UserDataInterface $userData,
  ) {}

  #[Hook('aculta_badge_award_insert')]
  public function awardInserted(BadgeAward $award): void {
    $uid = (int) $award->get('uid')->target_id;
    $pending = array_map('intval', (array) $this->userData->get('aculta_user', $uid, 'game_achievement_notice_queue'));
    $pending[] = (int) $award->id();
    $this->userData->set('aculta_user', $uid, 'game_achievement_notice_queue', array_values(array_unique($pending)));
    if ($this->queues->get('aculta_game_achievement_email')->createItem(['award_id' => (int) $award->id()]) === FALSE) {
      $this->loggerFactory->get('aculta_game')->error('Não foi possível colocar o aviso de conquista na fila. Concessão @id.', ['@id' => $award->id()]);
    }
  }

}
