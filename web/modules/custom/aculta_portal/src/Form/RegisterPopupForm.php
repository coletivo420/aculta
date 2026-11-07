<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\user\RegisterForm;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cadastro no modal (popup), aberto pelo visitante a partir de um convite (ex.: checkout de doação).
 *
 * Herda do Core o cadastro (validação, criação da conta e políticas de e-mail). Troca só o ID do formulário, por isso o
 * desafio Turnstile de user_register_form não se aplica a este modal. Decisão do responsável: o cadastro em modal não
 * usa Turnstile. Exceção registrada em Captcha\TurnstileToggle e em docs/portal/TURNSTILE.md.
 *
 * Em requisição HTMX (ADR-050, ACULTA420 V2) o formulário devolve o fragmento
 * e usa `HX-Redirect` no sucesso. O fluxo sem HTMX mantém o redirect nativo e
 * a resposta Drupal AJAX legada para a V1.
 */
final class RegisterPopupForm extends RegisterForm {

  /**
   * Id do wrapper trocado pelo AJAX em caso de erro de validação. */
  public const WRAPPER_ID = 'aculta-register-popup';

  /**
   *
   */
  public function getFormId() {
    return 'aculta_register_popup_form';
  }

  /**
   * Sem formulário base: o módulo captcha, ao não achar o ponto deste formulário, usaria o do ID base (user_form), e a
   * regra global de CAPTCHA passaria a valer. O cadastro em modal fica só com o ponto desligado deste formulário.
   */
  public function getBaseFormId() {
    return NULL;
  }

  /**
   *
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['data-portal-ui'] = 'register-popup';
    $form['destination'] = [
      '#type' => 'hidden',
      '#value' => LoginPopupForm::safeDestination($this->getRequest()->query->get('destination')),
    ];
    if ($this->isHtmxRequest()) {
      (new Htmx())
        ->post(Url::fromRoute('aculta_portal.register_popup'))
        ->select('#' . self::WRAPPER_ID)
        ->swap('innerHTML')
        ->onlyMainContent()
        ->applyTo($form);
    }
    else {
      // Keep the pre-HTMX contract for the V1 theme while V2 uses HTMX.
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::ajaxSubmit',
        'wrapper' => self::WRAPPER_ID,
        'progress' => ['type' => 'throbber'],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Preserves the safe return path for native and HTMX submissions.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    if (!$this->isHtmxRequest()) {
      return;
    }
    $destination = LoginPopupForm::safeDestination($form_state->getValue('destination'));
    $form_state->setRedirectUrl(Url::fromUserInput($destination));
    $form_state->setResponse(new Response('', Response::HTTP_OK, ['HX-Redirect' => $destination]));
  }

  /**
   * Após o cadastro, volta ao destino (a etapa do checkout). Em erro, devolve o formulário com as mensagens.
   *
   * Fallback Drupal AJAX usado somente pela V1.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): array|AjaxResponse {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand(LoginPopupForm::safeDestination($form_state->getValue('destination'))));
    return $response;
  }

}
