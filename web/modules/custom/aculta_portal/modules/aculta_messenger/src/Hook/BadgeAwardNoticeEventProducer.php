<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Hook;

use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_user\Entity\BadgeAward;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Publishes Game grants through Messenger's public event contract. */
final class BadgeAwardNoticeEventProducer {

  public function __construct(
    private readonly MessengerNoticePublisherInterface $publisher,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
 * Publishes one event for an award created by the optional Game module. */
  #[Hook('aculta_badge_award_insert')]
  public function awardInserted(BadgeAward $award): void {
    if (!$this->moduleHandler->moduleExists('aculta_game')) {
      return;
    }
    $uid = (int) $award->get('uid')->target_id;
    $awardId = (int) $award->id();
    $uuid = (string) $award->uuid();
    $timestamp = (int) $award->get('concedido_em')->value;
    if ($uid < 1 || $awardId < 1 || $uuid === '' || $timestamp < 1) {
      return;
    }
    $this->publisher->publish(self::event($uuid, $awardId, $uid, $timestamp));
  }

  /**
   * Builds the stable event for a new or migrated award. */
  public static function event(string $uuid, int $awardId, int $uid, int $timestamp): MessengerNoticeEvent {
    return new MessengerNoticeEvent(
      'game.achievement_awarded',
      'aculta_game',
      'aculta_badge_award',
      $uuid,
      (string) $awardId,
      $uid,
      'account',
      new \DateTimeImmutable('@' . $timestamp),
      implode(':', ['game', 'achievement_awarded', $uuid, (string) $awardId, (string) $uid]),
      ['notice_type' => 'achievement'],
    );
  }

}
