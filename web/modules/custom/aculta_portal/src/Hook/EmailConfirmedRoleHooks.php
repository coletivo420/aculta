<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\aculta_portal\Account\EmailConfirmedRoleGuard;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\UserInterface;

/**
 * Hooks do papel email_confirmed (ver EmailConfirmedRoleGuard).
 */
final class EmailConfirmedRoleHooks {

  /**
   * Implements hook_user_presave().
   *
   * Restaura o papel se a gravação o removeu. Não bloqueia a gravação: o restante dos dados é salvo.
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $account): void {
    if (EmailConfirmedRoleGuard::isRemoval($account, $account->getOriginal())) {
      $account->addRole(EmailConfirmedRoleGuard::ROLE);
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user forms.
   *
   * Trava o checkbox do papel na administração e mantém-no marcado quando a conta o tem.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!isset($form['account']['roles'][EmailConfirmedRoleGuard::ROLE])) {
      return;
    }
    $account = $form_state->getFormObject()->getEntity();
    $element = &$form['account']['roles'][EmailConfirmedRoleGuard::ROLE];
    $element['#disabled'] = TRUE;
    $element['#default_value'] = $account instanceof UserInterface && $account->hasRole(EmailConfirmedRoleGuard::ROLE);
    $element['#description'] = new TranslatableMarkup('Não pode ser removido: a confirmação de e-mail protege o site contra bots e contas falsas.');
  }

}
