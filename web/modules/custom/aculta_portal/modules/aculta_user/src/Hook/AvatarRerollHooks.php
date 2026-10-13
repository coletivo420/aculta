<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\aculta_user\Form\AvatarRerollCallbacks;
use Drupal\user\UserInterface;

/**
 * Adiciona o botão "Sortear outro avatar" ao formulário de foto do painel da própria conta.
 */
final class AvatarRerollHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user forms.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_portal.dashboard' || !isset($form['actions'])) {
      return;
    }
    $account = $form_state->getFormObject()->getEntity();
    if (!$account instanceof UserInterface || (int) $account->id() !== (int) $this->currentUser->id()) {
      return;
    }
    $form['actions']['sortear_avatar'] = [
      '#type' => 'submit',
      '#value' => t('Sortear outro avatar'),
      '#limit_validation_errors' => [],
      '#submit' => ['aculta_user.avatar_reroll_callbacks:submit'],
      '#ajax' => [
        'callback' => 'aculta_user.avatar_reroll_callbacks:ajax',
        'wrapper' => AvatarRerollCallbacks::WRAPPER,
        'progress' => ['type' => 'throbber'],
      ],
    ];
  }

}
