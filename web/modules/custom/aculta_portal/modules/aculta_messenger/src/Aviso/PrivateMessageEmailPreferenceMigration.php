<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\UserDataInterface;

/**
 * Migrates Private Message email settings to Messenger-owned preferences. */
final class PrivateMessageEmailPreferenceMigration {

  private const LEGACY_MODULE = 'private_message';

  private const LEGACY_NAME = 'receive_notification';

  private const NEW_NAME = 'avisos_email_mode_' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly UserDataInterface $userData,
    private readonly Connection $database,
  ) {}

  /**
   * Copies the old global policy into Messenger config without overwriting it. */
  public function migrateAdministrativeDefaults(): void {
    $legacy = $this->configFactory->get('private_message.settings');
    $settings = $this->configFactory->getEditable('aculta_messenger.settings');
    $modes = $settings->get('email_modes.' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES);
    $modes = is_array($modes) ? $modes : [];
    $legacyEmailEnabled = $legacy->get('enable_notifications') !== FALSE;

    if (!array_key_exists('immediate', $modes)) {
      $modes['immediate'] = $legacyEmailEnabled;
    }
    if (!array_key_exists('digest', $modes)) {
      $modes['digest'] = $legacyEmailEnabled;
    }
    if (!array_key_exists('default_mode', $modes)) {
      $defaultEnabled = $legacy->get('notify_by_default') !== FALSE;
      $modes['default_mode'] = $defaultEnabled && $modes['immediate']
        ? AvisoPreferencias::EMAIL_IMMEDIATE
        : AvisoPreferencias::EMAIL_OFF;
    }

    $settings->set('email_modes.' . AvisoPreferencias::EMAIL_PRIVATE_MESSAGES, $modes);
    if ($settings->get('private_message_digest_anchor') === NULL) {
      $settings->set('private_message_digest_anchor', '2026-10-26');
    }
    $settings->save();
  }

  /**
   * Migrates one bounded batch of explicit per-user settings.
   *
   * The legacy row is deleted only after its value is copied or an existing
   * Messenger preference has been retained. A repeated batch is idempotent.
   *
   * @param int $lastUid
   *   UID cursor; updated to the last visited record.
   * @param int $limit
   *   Maximum number of source records to process.
   *
   * @return bool
   *   TRUE when another batch may remain.
   */
  public function migrateUserBatch(int &$lastUid, int $limit = 500): bool {
    $limit = min(500, max(1, $limit));
    $uids = $this->database->select('users_data', 'ud')
      ->fields('ud', ['uid'])
      ->condition('module', self::LEGACY_MODULE)
      ->condition('name', self::LEGACY_NAME)
      ->condition('uid', $lastUid, '>')
      ->orderBy('uid', 'ASC')
      ->range(0, $limit)
      ->execute()
      ->fetchCol();

    foreach ($uids as $uid) {
      $uid = (int) $uid;
      $legacy = $this->userData->get(self::LEGACY_MODULE, $uid, self::LEGACY_NAME);
      $current = $this->userData->get(AvisoPreferencias::MODULO, $uid, self::NEW_NAME);
      if ($current === NULL) {
        $mode = (bool) $legacy
          ? AvisoPreferencias::EMAIL_IMMEDIATE
          : AvisoPreferencias::EMAIL_OFF;
        $this->userData->set(AvisoPreferencias::MODULO, $uid, self::NEW_NAME, $mode);
      }
      $this->userData->delete(self::LEGACY_MODULE, $uid, self::LEGACY_NAME);
      $lastUid = $uid;
    }

    return count($uids) === $limit;
  }

}
