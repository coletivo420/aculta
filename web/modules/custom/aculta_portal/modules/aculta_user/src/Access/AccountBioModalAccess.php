<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A edição da bio só existe dentro do modal: abertura por AJAX (drupal_modal) ou envio do próprio formulário.
 *
 * Mesmo padrão do modal de foto (UserPictureForm, #action na rota): visita direta à URL responde 403.
 */
final class AccountBioModalAccess {

  public function __construct(
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Acesso à rota aculta_user.bio_form.
   */
  public function access(): AccessResult {
    $result = AccessResult::allowedIfHasPermission($this->currentUser, 'access aculta portal')
      ->addCacheContexts(['user.permissions', 'request_format', 'url.query_args:_wrapper_format']);
    $request = $this->requestStack->getCurrentRequest();
    return $result->andIf(AccessResult::allowedIf($request instanceof Request && $this->isModalRequest($request)));
  }

  /**
   * Requisição do modal (AJAX ou _wrapper_format=drupal_modal) ou envio do formulário.
   */
  private function isModalRequest(Request $request): bool {
    return $request->isXmlHttpRequest()
      || $request->query->get('_wrapper_format') === 'drupal_modal'
      || $request->isMethod('POST');
  }

}
