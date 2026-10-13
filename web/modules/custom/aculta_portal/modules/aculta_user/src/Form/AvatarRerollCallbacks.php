<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Botão "Sortear outro avatar" do popup de foto (painel da conta).
 *
 * Com JavaScript, a resposta troca só o bloco da foto (#aculta-user-avatar), sem recarregar a página. Sem
 * JavaScript, o envio comum redireciona para o painel.
 */
final class AvatarRerollCallbacks {

  /** Id do contêiner da foto, definido pelo painel da conta (PortalController). */
  public const WRAPPER = 'aculta-user-avatar';

  public function __construct(
    #[Autowire(service: 'aculta_user.avatar_pool')]
    private readonly AvatarPool $pool,
    #[Autowire(service: 'renderer')]
    private readonly RendererInterface $renderer,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** Submit: sorteia outro avatar para a própria conta. */
  public function submit(array &$form, FormStateInterface $form_state): void {
    $account = $form_state->getFormObject()->getEntity();
    if ($account instanceof UserInterface && (int) $account->id() === (int) $this->currentUser->id()) {
      $this->pool->reroll($account);
    }
    $form_state->setRedirect('aculta_portal.dashboard');
  }

  /** AJAX: devolve só o bloco da foto já atualizada. */
  public function ajax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $account = $form_state->getFormObject()->getEntity();
    $fid = $account instanceof UserInterface ? (int) ($account->get('user_picture')->target_id ?? 0) : 0;
    $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    $build = [
      '#type' => 'container',
      '#attributes' => ['id' => self::WRAPPER],
      'image' => $file ? [
        '#theme' => 'image_style',
        '#style_name' => 'aculta_avatar',
        '#uri' => $file->getFileUri(),
        '#alt' => (string) $account->label(),
        '#attributes' => ['data-portal-ui' => 'account-avatar'],
      ] : [],
    ];
    $html = (string) $this->renderer->renderInIsolation($build);
    $response->addCommand(new ReplaceCommand('#' . self::WRAPPER, $html));
    return $response;
  }

}
