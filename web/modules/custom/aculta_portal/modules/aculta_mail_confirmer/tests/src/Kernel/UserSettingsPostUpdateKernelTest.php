<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mail_confirmer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies migration of user confirmation settings to the consolidated owner.
 */
#[Group('aculta_mail_confirmer')]
final class UserSettingsPostUpdateKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'token', 'aculta_mail_confirmer'];

  /**
   * Moves user settings and preserves the primary module's settings.
   */
  public function testUserSettingsMoveToMailConfirmerOwner(): void {
    $this->installConfig(['aculta_mail_confirmer']);
    $this->container->get('config.storage')->write('aculta_mail_confirmer_user.settings', [
      'user_email_change' => [
        'enabled' => FALSE,
        'notify_current' => FALSE,
        'consider_existent' => TRUE,
        'limit_user_realm' => FALSE,
      ],
      'user_login' => [
        'sync_core_confirmation' => FALSE,
        'sync_core_onetimeloginlinks' => TRUE,
      ],
    ]);
    $originalLifetime = $this->config('aculta_mail_confirmer.settings')->get('confirmation_lifetime');

    require_once DRUPAL_ROOT . '/' . $this->container->get('extension.list.module')->getPath('aculta_mail_confirmer') . '/aculta_mail_confirmer.post_update.php';
    aculta_mail_confirmer_post_update_move_user_settings_to_module();

    self::assertFalse($this->config('aculta_mail_confirmer.settings')->get('user_email_change.enabled'));
    self::assertFalse($this->config('aculta_mail_confirmer.settings')->get('user_email_change.notify_current'));
    self::assertFalse($this->config('aculta_mail_confirmer.settings')->get('user_login.sync_core_confirmation'));
    self::assertTrue($this->config('aculta_mail_confirmer.settings')->get('user_login.sync_core_onetimeloginlinks'));
    self::assertSame($originalLifetime, $this->config('aculta_mail_confirmer.settings')->get('confirmation_lifetime'));
    self::assertTrue($this->config('aculta_mail_confirmer_user.settings')->isNew());
  }

}
