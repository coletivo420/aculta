<?php

declare(strict_types=1);

namespace Drupal\aculta_mail_confirmer_user\Hook;

use Drupal\aculta_mail_confirmer\AcultaMailConfirmationInterface;
use Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Login: registra como confirmado o e-mail de conta criada ou de link de uso.
 */
final class UserLoginConfirmationHooks {

  /**
   * Creates the hooks.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\aculta_mail_confirmer\AcultaMailConfirmerManagerInterface $manager
   *   The email confirmation service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AcultaMailConfirmerManagerInterface $manager,
    private readonly TimeInterface $time,
    private readonly RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin(AccountInterface $account): void {
    $config = $this->configFactory->get('aculta_mail_confirmer_user.settings')->get('user_login');

    // Register a confirmed email confirmation for new created accounts on their
    // first access or when a user logins through a one time login link.
    if (((!$account->getLastAccessedTime() && $config['sync_core_confirmation'])
        || ($this->routeMatch->getRouteName() == 'user.reset.login' && $config['sync_core_onetimeloginlinks']))
      && !$this->manager->getConfirmation($account->getEmail(), 'confirmed')) {
      $confirmation = $this->manager->createConfirmation($account->getEmail());
      $confirmation->setRealm('aculta_mail_confirmer_user')
        ->setLastRequestDate($this->time->getRequestTime());
      $confirmation->get('confirmed')->setValue(AcultaMailConfirmationInterface::CONFIRMED);
      $confirmation->save();
    }
  }

}
