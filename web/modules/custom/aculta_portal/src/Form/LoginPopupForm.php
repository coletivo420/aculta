<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\user\Form\UserLoginForm;
use Drupal\user\LoginFinalizer;
use Drupal\user\UserAuthenticationInterface;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserFloodControlInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login do popup "Entrar" da barra multidomínio.
 *
 * Herda do Core a validação (user.auth, flood control e bloqueio de conta).
 * Troca só o ID do formulário, então o desafio Turnstile de user_login_form
 * não se aplica. Exceção registrada em Captcha\TurnstileToggle e no gate
 * validate-turnstile-exceptions.php. O popup usa HTMX para carregar e enviar
 * o fragmento; V1 mantém o fallback Drupal AJAX e sem JavaScript o fluxo é
 * um formulário nativo com retorno local validado.
 */
final class LoginPopupForm extends UserLoginForm {

  public const FORM_ID = 'aculta_login_popup_form';

  public const WRAPPER_ID = 'aculta-login-popup';

  public function __construct(
    UserFloodControlInterface $user_flood_control,
    UserStorageInterface $user_storage,
    UserAuthInterface|UserAuthenticationInterface $user_auth,
    RendererInterface $renderer,
    BareHtmlPageRendererInterface $bare_html_renderer,
    LoginFinalizer $login_finalizer,
    protected readonly DomainPurposeManager $domainPurpose,
    protected readonly AuthIntegrationManager $authIntegration,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($user_flood_control, $user_storage, $user_auth, $renderer, $bare_html_renderer, $login_finalizer);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.flood_control'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('user.auth'),
      $container->get('renderer'),
      $container->get('bare_html_page_renderer'),
      $container->get(LoginFinalizer::class),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_portal.auth_integration'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return self::FORM_ID;
  }

  /**
   * Returns a local same-host destination outside authentication routes.
   */
  public static function safeDestination(mixed $value): string {
    $path = is_string($value) ? $value : '';
    $isLocalPath = preg_match('#^/(?![/\\\\])[^\s\\\\]*$#', $path) === 1;
    $isAuthPath = preg_match('#^/(?:entrar|oauth|sair|recuperar(?:-senha|-acesso)?)(?:/|$)#', $path) === 1;
    return $isLocalPath && !$isAuthPath ? $path : '/';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $validators = $form['#validate'] ?? [];
    $authentication_wrapper = 'aculta_portal.form_callbacks:validateLoginAuthentication';
    $authentication_validators = array_values(array_filter(
      $validators,
      static fn($validator) => $validator !== $authentication_wrapper,
    ));
    $authentication_index = array_search('::validateAuthentication', $authentication_validators, TRUE);
    if ($authentication_index === FALSE) {
      array_unshift($authentication_validators, $authentication_wrapper);
    }
    else {
      $authentication_validators[$authentication_index] = $authentication_wrapper;
    }
    $form['#validate'] = $authentication_validators;
    $form['name']['#title'] = $this->t('Nome de usuário, e-mail ou CPF');
    $form['pass']['#title'] = $this->t('Senha');
    $form['actions']['submit']['#value'] = $this->t('Entrar');
    $form['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['data-portal-ui'] = 'login-popup';
    $form['#cache']['contexts'][] = 'domain';
    $form['destination'] = [
      '#type' => 'hidden',
      '#value' => self::safeDestination($this->getRequest()->query->get('destination')),
    ];

    $links = [];
    $passwordUrl = $this->domainPurpose->routeUrl('account', 'user.pass');
    if ($passwordUrl !== NULL) {
      $links['password'] = ['#type' => 'link', '#title' => $this->t('Esqueceu sua senha?'), '#url' => $passwordUrl];
    }
    $registerUrl = $this->domainPurpose->routeUrl('account', 'user.register');
    if ($registerUrl !== NULL) {
      // Divisor entre "Esqueceu sua senha?" e "Cadastre-se", como no anexo.
      $links['divider'] = [
        '#type' => 'html_tag',
        '#tag' => 'hr',
        '#attributes' => ['data-portal-ui' => 'login-popup-divider'],
      ];
      $links['register'] = ['#type' => 'link', '#title' => $this->t('Cadastre-se'), '#url' => $registerUrl];
    }
    $form['links'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'login-popup-links'],
    ] + $links;

    // Google (Social Auth, mesmo fluxo do /entrar). A rota OAuth é a do host
    // da conta, onde as URIs de retorno estão cadastradas; o retorno volta ao
    // host de origem pelo destino (aculta_destination_purpose).
    if ($this->moduleHandler->moduleExists('social_auth') && $this->authIntegration->isGoogleConfigured()) {
      $google = $this->googleButton($form['destination']['#value']);
      if ($google !== NULL) {
        $form['social_auth'] = $google;
        $form['social_auth_divider'] = [
          '#type' => 'html_tag',
          '#tag' => 'hr',
          '#attributes' => ['data-portal-ui' => 'login-popup-divider'],
          '#weight' => -19,
        ];
      }
    }

    // Entrar logo após a senha e os links depois dele. O contêiner não é
    // "form-actions": assim o Core não copia o botão para o rodapé do diálogo
    // (dialog.jquery-ui.js, prepareDialogButtons).
    $form['actions']['#type'] = 'container';
    $form['actions']['#weight'] = 5;
    $form['links']['#weight'] = 10;
    if ($this->isHtmxRequest()) {
      (new Htmx())
        ->post()
        ->select('#' . self::WRAPPER_ID)
        ->target('#aculta-login-content')
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
   * Returns the popup's Google sign-in link when its route exists.
   */
  private function googleButton(string $destination): ?array {
    $url = $this->domainPurpose->routeUrl('account', 'social_auth.network.redirect', ['network' => 'google']);
    if ($url === NULL) {
      return NULL;
    }
    $query = ['destination' => $destination];
    $purpose = $this->domainPurpose->getCurrentPurpose();
    if ($purpose !== NULL) {
      $query['aculta_destination_purpose'] = $purpose;
    }
    $url->setOption('query', $query);
    $markup = sprintf(
      '<a href="%s" data-portal-ui="auth-google"><span>%s <strong>Google</strong></span></a>',
      Html::escape($url->toString()),
      Html::escape((string) $this->t('Continuar com')),
    );
    return [
      '#type' => 'container',
      '#weight' => -20,
      '#attributes' => ['data-portal-ui' => 'auth-provider'],
      'google' => ['#markup' => Markup::create($markup)],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateFinal(array &$form, FormStateInterface $form_state) {
    if ($form_state->get('uid')) {
      parent::validateFinal($form, $form_state);
      return;
    }
    $flood_config = $this->config('user.flood');
    $this->userFloodControl->register('user.failed_login_ip', $flood_config->get('ip_window'));
    if ($identifier = $form_state->get('flood_control_user_identifier')) {
      $this->userFloodControl->register('user.failed_login_user', $flood_config->get('user_window'), $identifier);
    }
    $passwordUrl = $this->domainPurpose->routeUrl('account', 'user.pass')?->toString() ?? '#';
    $triggered = $form_state->get('flood_control_triggered');
    if ($triggered === 'user') {
      $form_state->setErrorByName('name', $this->formatPlural(
        $flood_config->get('user_limit'),
        'There has been more than one failed login attempt for this account. It is temporarily blocked. Try again later or <a href=":url">request a new password</a>.',
        'There have been more than @count failed login attempts for this account. It is temporarily blocked. Try again later or <a href=":url">request a new password</a>.',
        [':url' => $passwordUrl],
      ));
    }
    elseif ($triggered) {
      $form_state->setErrorByName('name', $this->t(
        'Too many failed login attempts from your IP address. This IP address is temporarily blocked. Try again later or <a href=":url">request a new password</a>.',
        [':url' => $passwordUrl],
      ));
    }
    else {
      $form_state->setErrorByName('name', $this->t('Unrecognized username or password. <a href=":password">Forgot your password?</a>', [':password' => $passwordUrl]));
      $this->logger('user')->notice('Login attempt failed from %ip.', ['%ip' => $this->getRequest()->getClientIp()]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Preserves the safe return path for native and HTMX submissions.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = $form_state->get('uid');
    if (empty($uid)) {
      return;
    }
    $account = $this->userStorage->load($uid);
    if ($account instanceof UserInterface) {
      $this->loginFinalizer->finalizeLogin($account);
    }
    $destination = self::safeDestination($form_state->getValue('destination'));
    if ($this->isHtmxRequest()) {
      $form_state->setResponse(new Response('', Response::HTTP_OK, ['HX-Redirect' => $destination]));
    }
    else {
      $form_state->setRedirectUrl(Url::fromUserInput($destination));
    }
  }

  /**
   * Keeps the legacy non-HTMX popup response for ACULTA420 V1.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): array|AjaxResponse {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $response = new AjaxResponse();
    $response->addCommand(new RedirectCommand(self::safeDestination($form_state->getValue('destination'))));
    return $response;
  }

}
