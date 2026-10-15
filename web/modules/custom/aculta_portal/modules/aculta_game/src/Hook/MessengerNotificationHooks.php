<?php

declare(strict_types=1);

namespace Drupal\aculta_game\Hook;

use Drupal\aculta_game\Form\AchievementNoticesForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserDataInterface;

/** Adds achievement notices to the existing account notification drawer. */
final class MessengerNotificationHooks {

  public function __construct(
    private readonly UserDataInterface $userData,
    private readonly FormBuilderInterface $forms,
  ) {}

  #[Hook('aculta_messenger_personal_notices_alter')]
  public function personalNotices(array &$items, AccountInterface $account): void {
    if (!$account->isAuthenticated()) {
      return;
    }
    $uid = (int) $account->id();
    $pending = (array) $this->userData->get('aculta_user', $uid, 'game_achievement_notice_queue');
    if ($pending !== []) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'achievement-notices'],
        'notices' => $this->forms->getForm(AchievementNoticesForm::class),
      ];
    }
  }

  #[Hook('aculta_messenger_unread_notice_count_alter')]
  public function unreadCount(int &$count, AccountInterface $account): void {
    if (!$account->isAuthenticated()) {
      return;
    }
    $pending = (array) $this->userData->get('aculta_user', (int) $account->id(), 'game_achievement_notice_queue');
    $count += count(array_unique(array_map('intval', $pending)));
  }

}
