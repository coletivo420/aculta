<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Hook;

use Drupal\commerce_payment\Form\PaymentGatewayForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\user\AccountForm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
    private readonly \Drupal\aculta_portal\Account\EmailConfirmationPolicy $emailPolicy,
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly \Drupal\aculta_portal\Domain\DomainPurposeManager $domainPurpose,
    #[Autowire(service: 'aculta_portal.cart_ajax')]
    private readonly \Drupal\aculta_portal\Cart\AddToCartAjax $cartAjax,
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

    // Com verificação de e-mail ligada, o Core não mostra senha no cadastro e gera uma. O Portal mostra a senha
    // escolhida pelo visitante e a grava depois de salvar; a conta continua bloqueada até confirmar o e-mail.
    if ($formId === 'user_register_form' && !isset($form['account']['pass'])) {
      $form['account']['pass'] = [
        '#type' => 'password_confirm',
        '#required' => TRUE,
        '#size' => 25,
        '#description' => $this->translation->translate('Escolha uma senha com pelo menos 8 caracteres. Enviaremos um link para confirmar o seu e-mail.'),
      ];
      $form['#validate'][] = 'aculta_portal.form_callbacks:validateRegistrationPassword';
      $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:storeRegistrationPassword';
    }
    // Cadastro: a conta fica ativa e o e-mail só é confirmado pelo link enviado (política de confirmação).
    if ($formId === 'user_register_form') {
      // Checkbox de aceite dos termos, obrigatório, logo acima do botão de envio.
      $form['aculta_terms'] = [
        '#type' => 'checkbox',
        '#title' => $this->termsLabel(),
        '#required' => TRUE,
        '#default_value' => TRUE,
        '#weight' => 90,
      ];
      // DT-P06: o salvamento passa pelo Portal, que não cria conta quando o e-mail já existe (ver saveRegistration).
      $form['#validate'][] = 'aculta_portal.form_callbacks:validateRegistrationMail';
      $form['actions']['submit']['#submit'] = array_map(
        static fn($callback) => $callback === '::save' ? 'aculta_portal.form_callbacks:saveRegistration' : $callback,
        $form['actions']['submit']['#submit'] ?? [],
      );
      $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:requestRegistrationConfirmation';
      $form['actions']['submit']['#submit'][] = 'aculta_portal.form_callbacks:acceptRegistrationTerms';
    }

    if ($formId === 'user_login_form') {
      // Core reports blocked or unactivated accounts before the password check,
      // which discloses that an address is registered. The Portal callback
      // replaces Core's authentication step. It is always present exactly once:
      // if Core's step is missing, the callback runs first, so a rename can never
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

    // Formulário da conta em Segurança: a senha atual fica nesta página, sem o link de recuperação do Core.
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

    if (!$formObject instanceof PaymentGatewayForm
      || $formObject->getEntity()->id() !== 'mercado_pago'
      || !isset($form['configuration']['form'])) {
      return;
    }

    $configurationForm = &$form['configuration']['form'];
    // Access Tokens de teste e de produção: campo de senha sem valor exibido. O valor real vem do
    // ambiente (MercadoPagoEnvironmentOverride), nunca da configuração.
    foreach ([
      ['credentials_test', 'access_token_test'],
      ['credentials_prod', 'access_token_prod'],
    ] as [$fieldset, $key]) {
      if (isset($configurationForm[$fieldset][$key])) {
        $configurationForm[$fieldset][$key]['#type'] = 'password';
        $configurationForm[$fieldset][$key]['#default_value'] = '';
        unset($configurationForm[$fieldset][$key]['#value']);
        $configurationForm[$fieldset][$key]['#attributes']['autocomplete'] = 'new-password';
      }
    }
    // Não usados pela integração (modo stage e Client ID/Client Secret): desabilitados e sem valor,
    // para que nenhuma credencial seja gravada na configuração exportada.
    foreach ([
      ['credentials_stage', 'public_key_stage'],
      ['credentials_stage', 'access_token_stage'],
      [NULL, 'client_id'],
      [NULL, 'client_secret'],
    ] as [$fieldset, $key]) {
      $element = &$configurationForm;
      if ($fieldset !== NULL) {
        $element = &$configurationForm[$fieldset];
      }
      if (isset($element[$key])) {
        $element[$key]['#disabled'] = TRUE;
        $element[$key]['#default_value'] = '';
        unset($element[$key]['#value']);
      }
    }
  }


  /**
   * Formulários de wiki e de comentários para conta sem e-mail confirmado: mostra o motivo e o caminho
   * Minha conta > Segurança, e desativa os campos (a escrita já é negada pela checagem de acesso).
   */
  /**
   * Formulários da Minha conta salvam por AJAX (sem recarregar a página): o botão de envio chama um callback que
   * devolve o próprio formulário, com as mensagens, dentro do mesmo bloco (wrapper). Redirecionamentos de salvamento
   * são desligados para esse envio; o foco vai para o primeiro erro (aculta_portal/account-navigation).
   *
   * A bio (aculta_user_account_bio_form) fica fora: salva pelo próprio JS do modal (rota JSON) e fecha o modal.
   *
   * O login (user_login_form) fica fora: ele precisa do redirecionamento do Core após autenticar. Com o AJAX, a sessão
   * era criada, mas o redirecionamento era cancelado e a página continuava no formulário de login.
   */
  private function accountAjaxSubmit(array &$form, string $formId): void {
    $route = $this->routeMatch->getRouteObject();
    if ($route === NULL || $route->getOption('_aculta_domain_purpose') !== 'account' || $formId === 'aculta_portal_account_terms_accept' || $formId === 'aculta_portal_account_password_create'
      || $formId === 'user_login_form' || $formId === 'aculta_user_account_bio_form' || !empty($form['#aculta_json_save']) || !isset($form['actions']['submit'])) {
      return;
    }
    $wrapper = 'portal-account-' . \Drupal\Component\Utility\Html::cleanCssIdentifier($formId);
    $form['#prefix'] = '<div id="' . $wrapper . '">' . ($form['#prefix'] ?? '');
    $form['#suffix'] = ($form['#suffix'] ?? '') . '</div>';
    $submit = &$form['actions']['submit'];
    $submit['#ajax'] = [
      'callback' => 'aculta_portal.form_callbacks:accountAjaxResponse',
      'wrapper' => $wrapper,
      'progress' => ['type' => 'throbber'],
      // Sem devolver o foco ao botão: o foco vai para o primeiro campo com erro (acultaPortalAccountFormErrors).
      'disable-refocus' => TRUE,
    ];
    // O último handler de envio desliga o redirecionamento (o envio fica na página, com mensagem).
    if (!empty($submit['#submit'])) {
      $submit['#submit'][] = 'aculta_portal.form_callbacks:disableAccountRedirect';
    }
    else {
      $form['#submit'][] = 'aculta_portal.form_callbacks:disableAccountRedirect';
    }
  }

  private function restrictedFormNotice(array &$form, string $form_id): void {
    $policy = $this->emailPolicy;
    $is_wiki = in_array($form_id, ['node_wiki_entry_form', 'node_wiki_entry_edit_form', 'taxonomy_term_wiki_category_form', 'taxonomy_term_wiki_category_edit_form'], TRUE);
    $is_comment = preg_match('/^comment_.*_form$/', $form_id) === 1;
    if (!($is_wiki || $is_comment) || $this->currentUser->isAnonymous() || $policy->isConfirmed($this->currentUser)) {
      return;
    }
    $url = Url::fromRoute('aculta_portal.security')->toString();
    $form['aculta_email_restriction'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['data-portal-ui' => 'notice-error email-notice', 'role' => 'alert'],
      'text' => ['#markup' => '<p><strong>' . htmlspecialchars((string) $this->translation->translate('Sua conta está bloqueada por não confirmar o e-mail.'), ENT_QUOTES) . '</strong> '
        . '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars((string) $this->translation->translate('Confirme o e-mail em Minha conta > Segurança'), ENT_QUOTES) . '</a>.</p>'],
    ];
    foreach (array_keys($form) as $key) {
      if (!str_starts_with((string) $key, '#') && $key !== 'aculta_email_restriction') {
        $form[$key]['#access'] = FALSE;
      }
    }
  }

  /**
   * Rótulo do aceite: "Li e aceito os Termos de Uso e a Política de Privacidade.", com links para as páginas
   * do MAIN que abrem em nova aba (o cadastro acontece no host da conta).
   */
  private function termsLabel(): \Drupal\Component\Render\MarkupInterface {
    $link = static function (string $href, string $text): string {
      return '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($text, ENT_QUOTES) . '</a>';
    };
    $terms = $this->domainPurpose->pathUrl('main', '/termos-de-uso')?->toString() ?? '/termos-de-uso';
    $privacy = $this->domainPurpose->pathUrl('main', '/politica-de-privacidade')?->toString() ?? '/politica-de-privacidade';
    $pattern = (string) $this->translation->translate('Li e aceito os @terms e a @privacy.');
    $html = strtr($pattern, [
      '@terms' => $link($terms, (string) $this->translation->translate('Termos de Uso')),
      '@privacy' => $link($privacy, (string) $this->translation->translate('Política de Privacidade')),
    ]);
    return \Drupal\Core\Render\Markup::create($html);
  }

}
