<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Account;

use Drupal\aculta_portal\Form\AccountTermsAcceptForm;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resposta de bloqueio de página restrita sem aceite dos termos.
 *
 * Entrega só o popup de aceite, em página HTML mínima (BareHtmlPageRenderer, sem o tema do site). Assim o conteúdo
 * restrito e a página do site não chegam ao navegador nem ficam visíveis por trás do modal. Status 403, sem cache.
 */
final class AccountTermsGate {

  public function __construct(
    private readonly BareHtmlPageRendererInterface $barePageRenderer,
    private readonly FormBuilderInterface $formBuilder,
    private readonly TranslationInterface $translation,
  ) {}

  public function blockingResponse(): Response {
    $page = [
      '#theme' => 'aculta_portal_account_terms_modal',
      '#title' => $this->translation->translate('Aceite dos termos'),
      '#form' => $this->formBuilder->getForm(AccountTermsAcceptForm::class),
      '#attached' => ['library' => ['aculta_portal/account-terms-modal']],
      '#cache' => ['contexts' => ['user'], 'max-age' => 0],
    ];
    $response = $this->barePageRenderer->renderBarePage($page, (string) $this->translation->translate('Aceite dos termos'), 'maintenance_page');
    $response->setStatusCode(403);
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    return $response;
  }

}
