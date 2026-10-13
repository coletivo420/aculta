<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Form\PhotoEditorGuard;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Adiciona o botão "Sortear outro avatar" ao editor de foto do painel (ponto de extensão aculta_portal_photo_editor).
 *
 * O botão é um link AJAX (use-ajax) para a rota de sorteio, com token CSRF. Não é um formulário de conta.
 */
final class PhotoEditorHooks {

  /** Caminho da rota de sorteio (o token CSRF é gerado para este caminho). */
  private const PATH = '/avatar/sortear';

  public function __construct(
    #[Autowire(service: 'csrf_token')]
    private readonly CsrfTokenGenerator $csrfToken,
    #[Autowire(service: 'aculta_user.photo_editor_guard')]
    private readonly PhotoEditorGuard $guard,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  #[Hook('aculta_portal_photo_editor_alter')]
  public function photoEditorAlter(array &$photoForm, UserInterface $account): void {
    $photoForm['reroll'] = [
      '#type' => 'link',
      '#title' => t('Sortear outro avatar'),
      '#url' => Url::fromRoute('aculta_user.avatar_reroll', [], ['query' => ['csrf_token' => $this->csrfToken->get(self::PATH)]]),
      '#attributes' => ['class' => ['use-ajax', 'button', 'btn', 'btn-secondary']],
      '#attached' => ['library' => ['core/drupal.ajax']],
    ];
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user forms: foto do acervo no popup do painel.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_portal.dashboard' || !isset($form['user_picture'])) {
      return;
    }
    $account = $form_state->getFormObject()->getEntity();
    if (!$account instanceof UserInterface || (int) $account->id() !== (int) $this->currentUser->id()) {
      return;
    }
    // O painel envia só a foto. O buildEntity do Core espera o campo "roles": mantém os papéis atuais (sem apagar nenhum).
    $form['roles'] = ['#type' => 'value', '#value' => array_values($account->getRoles(TRUE))];
    $fid = (int) ($account->get('user_picture')->target_id ?? 0);
    $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    if (PhotoEditorGuard::isAcervo($account, $file)) {
      $this->guard->adjustForm($form, $form_state, $fid);
    }
  }

}
