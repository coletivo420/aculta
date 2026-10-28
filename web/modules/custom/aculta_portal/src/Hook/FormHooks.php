<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\Core\Render\Markup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\aculta_portal\Cart\AddToCartAjax;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\user\AccountForm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Account and Commerce form alterations owned by the Portal.
 */
final class FormHooks {

  public function __construct(
    #[Autowire(service: 'current_route_match')]
    private readonly CurrentRouteMatch $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'string_translation')]
    private readonly TranslationInterface $translation,
    #[Autowire(service: 'aculta_portal.email_confirmation_policy')]
    private readonly EmailConfirmationPolicy $emailPolicy,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $domainPurpose,
    #[Autowire(service: 'aculta_portal.cart_ajax')]
    private readonly AddToCartAjax $cartAjax,
    #[Autowire(service: 'request_stack')]
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Alters Portal-owned account and Commerce form surfaces.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();
    $this->accountAjaxSubmit($form, $formId);
    $this->cartAjax->alter($form, $formId);

    $this->restrictedFormNotice($form, $formId);
    $this->recoveryPasswordModal($form, $formState, $formId);

    // Com a verificação de e-mail ligada, o Core gera a senha no cadastro.
    // O Portal mostra a senha escolhida e salva depois; a conta ativa permanece
    // restrita até a confirmação do endereço.
    if ($formId === 'user_register_form' && !isset($form['account']['pass'])) {
      $form['account']['pass'] = [
        '#type' => 'password_confirm',
        '#required' => TRUE,
        '#size' => 25,
        '#description' => $this->translation->translate('Escolha uma senha com pelo menos 8 caracteres.')
        . ' ' . $this->translation->translate('Enviaremos um link para confirmar o seu e-mail.'),
      ];
      $form['#validate'][] = 'aculta_portal.form_callbacks:validateRegistrationPassword';
      $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:storeRegistrationPassword';
    }
    // Cadastro: a conta fica ativa; o link enviado confirma o endereço.
    if ($formId === 'user_register_form') {
      // Checkbox obrigatório de aceite dos termos, acima do botão de envio.
      $form['aculta_terms'] = [
        '#type' => 'checkbox',
        '#title' => $this->termsLabel(),
        '#required' => TRUE,
        '#default_value' => TRUE,
        '#weight' => 90,
      ];
      // DT-P06: o Portal não cria contas com e-mail existente.
      $form['#validate'][] = 'aculta_portal.form_callbacks:validateRegistrationMail';
      $form['actions']['submit']['#submit'] = array_map(
        static fn($callback) => $callback === '::save' ? 'aculta_portal.form_callbacks:saveRegistration' : $callback,
        $form['actions']['submit']['#submit'] ?? [],
      );
      $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:requestRegistrationConfirmation';
      $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:recordAcultaUserAgreementAcceptance';
    }

    if ($formId === 'user_login_form') {
      // Core reports blocked or unactivated accounts before the password check,
      // which discloses that an address is registered. The Portal callback
      // replaces Core's authentication step. It is always present exactly once:
      // If Core's step is missing, this callback runs first. A rename cannot
      // silently restore the disclosure.
      $wrapper = 'aculta_portal.form_callbacks:validateLoginAuthentication';
      $validators = array_values(array_filter($form['#validate'] ?? [], static fn($v) => $v !== $wrapper));
      $index = array_search('::validateAuthentication', $validators, TRUE);
      if ($index === FALSE) {
        array_unshift($validators, $wrapper);
      }
      else {
        $validators[$index] = $wrapper;
      }
      $form['#validate'] = $validators;
    }

    // Na Segurança, a senha atual fica aqui, sem o link de recuperação do Core.
    if ($formId === 'user_form' && $this->routeMatch->getRouteName() === 'aculta_portal.security_password' && isset($form['account']['current_pass'])) {
      $form['account']['current_pass']['#description'] = $this->translation->translate('Obrigatório para alterar a senha: informe sua senha atual.');
    }

    if ($formId === 'user_form'
      && $this->routeMatch->getRouteName() === 'aculta_portal.security_password'
      && $formObject instanceof AccountForm) {
      $account = $formObject->getEntity();
      if ((int) $account->id() === (int) $this->currentUser->id()) {
        $formState->set('user_pass_reset', FALSE);
        $form['account']['current_pass']['#access'] = TRUE;
        $form['account']['current_pass']['#required'] = TRUE;
        $form['account']['current_pass']['#title'] = $this->translation->translate('Senha atual');
        $form['account']['current_pass']['#attributes']['autocomplete'] = 'current-password';
        $form['account']['pass']['#title'] = $this->translation->translate('Nova senha');
        $form['account']['pass']['#attributes']['autocomplete'] = 'new-password';
        $form['#after_build'][] = 'aculta_portal.form_callbacks:securityPasswordAfterBuild';
        $form['#attributes']['data-portal-ui'] = 'security-password-form';
        $form['actions']['submit']['#value'] = $this->translation->translate('Salvar nova senha');

        foreach (array_keys($form) as $key) {
          if (!str_starts_with((string) $key, '#')
            && !in_array($key, ['account', 'actions', 'form_build_id', 'form_token', 'form_id'], TRUE)) {
            unset($form[$key]);
          }
        }
        foreach (array_keys($form['account']) as $key) {
          if (!str_starts_with((string) $key, '#')
            && !in_array($key, ['current_pass', 'pass'], TRUE)) {
            unset($form['account'][$key]);
          }
        }
        $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:securityPasswordRedirect';
      }
    }

    if (isset($form['commerce_donation_pane']['field_donation_amount'])) {
      // The contrib widget converts an empty custom value to zero while
      // massaging values. Require a real positive custom value server-side.
      $form['#validate'][] = 'aculta_portal.form_callbacks:validateDonationAmount';
      $form['commerce_donation_pane']['#title'] = $this->translation->translate('Seu apoio');
      $amountWidget = &$form['commerce_donation_pane']['field_donation_amount']['widget'][0]['donation_level'];
      if (isset($amountWidget)) {
        $amountWidget['#title'] = $this->translation->translate('Escolha um valor');
        if (isset($amountWidget['value']['#options']['custom_amount'])) {
          $amountWidget['value']['#options']['custom_amount'] = $this->translation->translate('Outro valor');
        }
        if (isset($amountWidget['amount'])) {
          $amountWidget['amount']['#title'] = $this->translation->translate('Outro valor');
          $amountWidget['amount']['#placeholder'] = $this->translation->translate('Outro valor');
        }
      }
    }

  }

  /**
   * Adds AJAX submit behavior to account forms.
   *
   * The submit callback returns the form and its messages inside the same
   * wrapper without reloading. Save redirects are disabled and focus moves to
   * the first error (aculta_portal/account-navigation).
   *
   * The bio form uses its modal's JSON route and stays outside this behavior.
   *
   * The login form stays outside this behavior so Core can redirect after
   * authentication. AJAX previously kept the new session on the login page.
   */
  private function accountAjaxSubmit(array &$form, string $formId): void {
    $route = $this->routeMatch->getRouteObject();
    if ($route === NULL || $route->getOption('_aculta_domain_purpose') !== 'account' || $formId === 'aculta_portal_aculta_user_agreement_accept' || $formId === 'aculta_portal_account_password_create'
      || $formId === 'user_login_form' || $formId === 'user_pass_reset' || $formId === 'aculta_mail_confirmer_response' || $formId === 'aculta_user_account_bio_form' || !empty($form['#aculta_json_save']) || !isset($form['actions']['submit'])) {
      return;
    }
    $wrapper = 'portal-account-' . Html::cleanCssIdentifier($formId);
    $form['#prefix'] = '<div id="' . $wrapper . '">' . ($form['#prefix'] ?? '');
    $form['#suffix'] = ($form['#suffix'] ?? '') . '</div>';
    $submit = &$form['actions']['submit'];
    $submit['#ajax'] = [
      'callback' => 'aculta_portal.form_callbacks:accountAjaxResponse',
      'wrapper' => $wrapper,
      'progress' => ['type' => 'throbber'],
      // Focus moves to the first form error through the account error handler.
      'disable-refocus' => TRUE,
    ];
    // Keep the response and status message in place instead of redirecting.
    if (!empty($submit['#submit'])) {
      $submit['#submit'][] = 'aculta_portal.form_callbacks:disableAccountRedirect';
    }
    else {
      $form['#submit'][] = 'aculta_portal.form_callbacks:disableAccountRedirect';
    }
  }

  /**
   * Recuperação de senha em modal sem fechar, só com a nova senha.
   *
   * O link do e-mail leva ao formulário de conta com o token do Core. Só o campo de senha aparece, em modal sem botão
   * de fechar (js/account-password-modal.js), como na criação de senha do cadastro simplificado.
   */
  private function recoveryPasswordModal(array &$form, FormStateInterface $formState, string $formId): void {
    $formObject = $formState->getFormObject();
    $request = $this->requestStack->getCurrentRequest();
    if ($formId !== 'user_form' || !$formObject instanceof AccountForm || $request === NULL) {
      return;
    }
    $account = $formObject->getEntity();
    if (!AccountRouteSubscriber::isValidCorePasswordResetRequest($request, (int) $account->id(), (int) $this->currentUser->id())) {
      return;
    }
    $manter = ['account', 'actions', 'form_build_id', 'form_token', 'form_id'];
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && !in_array($key, $manter, TRUE)) {
        unset($form[$key]);
      }
    }
    foreach (array_keys($form['account']) as $key) {
      if (!str_starts_with((string) $key, '#') && $key !== 'pass') {
        unset($form['account'][$key]);
      }
    }
    // O Core espera o campo de papéis no buildEntity: mantém os papéis atuais, sem exibir o campo.
    $papeis = array_values(array_diff($account->getRoles(), ['authenticated']));
    $form['account']['roles'] = ['#type' => 'value', '#value' => array_combine($papeis, $papeis) ?: []];
    $form['actions']['submit']['#value'] = $this->translation->translate('Salvar nova senha');
    $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:recoveryPasswordRedirect';
    $title = Html::escape((string) $this->translation->translate('Crie sua nova senha'));
    $form['#prefix'] = '<div id="aculta-account-password" data-dialog-title="' . $title . '" hidden>';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'aculta_portal/account-password-modal';
  }

  /**
   * Adds the confirmation notice and hides protected form fields.
   *
   * Wiki and comment writes are already denied by access checks. This directs
   * the user to account security to confirm the address.
   */
  private function restrictedFormNotice(array &$form, string $form_id): void {
    $policy = $this->emailPolicy;
    $is_wiki = in_array(
      $form_id,
      [
        'node_wiki_entry_form',
        'node_wiki_entry_edit_form',
        'taxonomy_term_wiki_category_form',
        'taxonomy_term_wiki_category_edit_form',
      ],
      TRUE,
    );
    $is_comment = preg_match('/^comment_.*_form$/', $form_id) === 1;
    if (!($is_wiki || $is_comment) || $this->currentUser->isAnonymous() || $policy->isConfirmed($this->currentUser)) {
      return;
    }
    $url = Url::fromRoute('aculta_portal.security')->toString();
    $form['aculta_email_restriction'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['data-portal-ui' => 'notice-error email-notice', 'role' => 'alert'],
      'text' => [
        '#markup' => '<p><strong>' . htmlspecialchars((string) $this->translation->translate('Você precisa confirmar seu e-mail para participar desta área.'), ENT_QUOTES) . '</strong> '
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars((string) $this->translation->translate('Confirme o e-mail em Minha conta > Segurança'), ENT_QUOTES) . '</a>.</p>',
      ],
    ];
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && $key !== 'aculta_email_restriction') {
        $form[$key]['#access'] = FALSE;
      }
    }
  }

  /**
   * Builds the terms acceptance label with links to the MAIN pages.
   *
   * Links open in a new tab because registration happens on the account host.
   */
  private function termsLabel(): MarkupInterface {
    $link = static function (string $href, string $text): string {
      return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($text, ENT_QUOTES) . '</a>';
    };
    $terms = $this->domainPurpose->pathUrl('main', '/sobre/termos-de-uso')?->toString() ?? '/sobre/termos-de-uso';
    $privacy = $this->domainPurpose->pathUrl('main', '/sobre/politica-de-privacidade')?->toString() ?? '/sobre/politica-de-privacidade';
    $pattern = (string) $this->translation->translate('Li e aceito os @terms e a @privacy.');
    $html = strtr($pattern, [
      '@terms' => $link($terms, (string) $this->translation->translate('Termos de Uso')),
      '@privacy' => $link($privacy, (string) $this->translation->translate('Política de Privacidade')),
    ]);
    return Markup::create($html);
  }

}
