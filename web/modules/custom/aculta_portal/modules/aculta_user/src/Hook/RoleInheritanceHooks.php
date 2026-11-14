<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_user\Role\HerancaDePapeis;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Herança de papéis: estende a conta ao carregar, retira os papéis herdados ao salvar e não os mostra como marcados.
 */
final class RoleInheritanceHooks {

  public function __construct(
    #[Autowire(service: 'aculta_user.heranca_papeis')]
    private readonly HerancaDePapeis $heranca,
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly ?EmailConfirmationPolicy $emailConfirmationPolicy = NULL,
  ) {}

  /**
   * Aplica os papéis herdados às contas carregadas.
   *
   * @param array<array-key, mixed> $users
   *   Contas de usuário carregadas.
   */
  #[Hook('user_load')]
  public function userLoad(array $users): void {
    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $emailConfirmado = $this->emailConfirmationPolicy?->isConfirmed($user) ?? TRUE;
      foreach ($this->heranca->herdados($this->stringsOf($user->getRoles()), $emailConfirmado) as $papel) {
        if (!$user->hasRole($papel)) {
          $user->addRole($papel);
        }
      }
    }
  }

  /**
   * Remove os papéis herdados que não devem ser gravados.
   *
   * @param \Drupal\user\UserInterface $user
   *   Conta de usuário prestes a ser salva.
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $user): void {
    $emailConfirmado = $this->emailConfirmationPolicy?->isConfirmed($user) ?? TRUE;
    foreach ($this->heranca->herdadosNaoGravados((int) $user->id(), $emailConfirmado) as $papel) {
      if ($user->hasRole($papel)) {
        $user->removeRole($papel);
      }
    }
  }

  /**
   * Ajusta os papéis padrão no formulário de conta do usuário.
   *
   * @param array<array-key, mixed> $form
   *   Formulário alterado por referência.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado do formulário.
   */
  #[Hook('form_user_form_alter')]
  public function formUserAlter(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    $conta = $formObject instanceof EntityFormInterface ? $formObject->getEntity() : NULL;
    // O valor padrão é lido e gravado de volta por nível, sem escrita aninhada em valores não tipados.
    $account = is_array($form['account'] ?? NULL) ? $form['account'] : [];
    $roles = is_array($account['roles'] ?? NULL) ? $account['roles'] : [];
    if (!array_key_exists('#default_value', $roles) || $roles['#default_value'] === NULL || !$conta instanceof UserInterface) {
      return;
    }
    $roles['#default_value'] = array_values(array_diff(
      $this->stringsOf($roles['#default_value']),
      $this->heranca->herdadosNaoGravados(
        (int) $conta->id(),
        $this->emailConfirmationPolicy?->isConfirmed($conta) ?? TRUE,
      ),
    ));
    $account['roles'] = $roles;
    $form['account'] = $account;
  }

  /**
   * Ao excluir um papel, ele sai da cadeia; laterais que dependiam dele voltam ao autenticado.
   */
  #[Hook('user_role_delete')]
  public function userRoleDelete(RoleInterface $papel): void {
    $this->heranca->removerPapel($this->textValue($papel->id()));
  }

  /**
   * Só os itens textuais de uma lista (papéis são strings).
   *
   * @return list<string>
   *   Itens que são strings, na mesma ordem.
   */
  private function stringsOf(mixed $values): array {
    $strings = [];
    foreach (is_array($values) ? $values : [] as $value) {
      if (is_string($value)) {
        $strings[] = $value;
      }
    }
    return $strings;
  }

  /**
   * Texto de um valor; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
  }

}
