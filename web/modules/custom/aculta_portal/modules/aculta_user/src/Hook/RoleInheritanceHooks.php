<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_user\Role\HerancaDePapeis;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/**
 * Herança de papéis: estende a conta ao carregar, retira os papéis herdados ao salvar e não os mostra como marcados.
 */
final class RoleInheritanceHooks {

  public function __construct(#[Autowire(service: 'aculta_user.heranca_papeis')] private readonly HerancaDePapeis $heranca) {}

  #[Hook('user_load')]
  public function userLoad(array $users): void {
    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      foreach ($this->heranca->herdados($user->getRoles()) as $papel) {
        if (!$user->hasRole($papel)) {
          $user->addRole($papel);
        }
      }
    }
  }

  #[Hook('user_presave')]
  public function userPresave(UserInterface $user): void {
    foreach ($this->heranca->herdadosNaoGravados((int) $user->id()) as $papel) {
      if ($user->hasRole($papel)) {
        $user->removeRole($papel);
      }
    }
  }

  #[Hook('form_user_form_alter')]
  public function formUserAlter(array &$form, FormStateInterface $form_state): void {
    $conta = $form_state->getFormObject()->getEntity();
    if (!isset($form['account']['roles']['#default_value']) || !$conta instanceof UserInterface) {
      return;
    }
    $form['account']['roles']['#default_value'] = array_values(array_diff(
      $form['account']['roles']['#default_value'],
      $this->heranca->herdadosNaoGravados((int) $conta->id()),
    ));
  }

}
