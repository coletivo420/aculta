<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leva o administrador ao formulário do próprio usuário, a partir do painel (MAIN).
 */
final class AdminSelfEditController extends ControllerBase {

  /**
   * Redireciona para entity.user.edit_form do usuário logado.
   */
  public function redirectToOwnEdit(): Response {
    $uid = (int) $this->currentUser()->id();
    if ($uid <= 0) {
      return new Response('', Response::HTTP_FORBIDDEN, ['Cache-Control' => 'private, no-store']);
    }
    $path = Url::fromRoute('entity.user.edit_form', ['user' => $uid])->toString();
    return new LocalRedirectResponse($path, Response::HTTP_FOUND, ['Cache-Control' => 'private, no-store']);
  }

}
