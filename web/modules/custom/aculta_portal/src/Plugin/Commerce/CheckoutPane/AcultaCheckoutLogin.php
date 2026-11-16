<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\Component\Utility\Html;
use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\DomainSiteManagerInterface;
use Drupal\aculta_portal\Domain\SiteKey;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\Login;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Email-first checkout login, retaining Commerce Core authentication logic.
 *
 * The email lookup only changes the form presentation. Authentication,
 * credential flood checks and guest account creation remain Core-owned.
 */
final class AcultaCheckoutLogin extends Login {

  /**
   * Domain-purpose URL resolver.
   */
  protected ?DomainSiteManagerInterface $domainSiteManager = NULL;

  /**
   * Configured Social Auth integrations.
   */
  protected ?AuthIntegrationManager $authIntegration = NULL;

  /**
   * Module availability checker.
   */
  protected ?ModuleHandlerInterface $portalModuleHandler = NULL;

  /**
   * Per-IP rate limiter for anonymous account lookup.
   */
  protected ?FloodInterface $flood = NULL;

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    /** @var \Drupal\aculta_portal\Domain\DomainSiteManagerInterface|null $domainSite */
    $domainSite = $container->get('aculta_portal.domain_site');
    $instance->domainSiteManager = $domainSite;
    /** @var \Drupal\aculta_portal\Auth\AuthIntegrationManager|null $authIntegration */
    $authIntegration = $container->get('aculta_portal.auth_integration');
    $instance->authIntegration = $authIntegration;
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface|null $moduleHandler */
    $moduleHandler = $container->get('module_handler');
    $instance->portalModuleHandler = $moduleHandler;
    /** @var \Drupal\Core\Flood\FloodInterface|null $flood */
    $flood = $container->get('flood');
    $instance->flood = $flood;
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   * @param array<mixed> $complete_form
   *
   * @return array<mixed>
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form): array {
    $built = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    $pane_form = is_array($built) ? $built : [];
    /** @var array<array-key, string> $parents */
    $parents = is_array($pane_form['#parents'] ?? NULL) ? $pane_form['#parents'] : [];
    $namePath = array_merge($parents, ['returning_customer', 'name']);
    $rawMail = $form_state->getValue($namePath);
    $mail = is_scalar($rawMail) ? (string) $rawMail : '';
    $known_account = $this->accountForMail($mail) !== NULL;

    $pane_form['google'] = $this->googleButton();
    if ($pane_form['google'] !== []) {
      $pane_form['google']['#weight'] = -30;
    }

    if (!isset($pane_form['returning_customer']) || !is_array($pane_form['returning_customer'])) {
      $pane_form['returning_customer'] = [];
    }
    $pane_form['returning_customer']['#title'] = $this->t('Identifique-se para continuar');
    if (!isset($pane_form['returning_customer']['#attributes']) || !is_array($pane_form['returning_customer']['#attributes'])) {
      $pane_form['returning_customer']['#attributes'] = [];
    }
    $pane_form['returning_customer']['#attributes']['id'] = 'aculta-checkout-identity-stage';

    if (!isset($pane_form['returning_customer']['name']) || !is_array($pane_form['returning_customer']['name'])) {
      $pane_form['returning_customer']['name'] = [];
    }
    $pane_form['returning_customer']['name']['#type'] = 'email';
    $pane_form['returning_customer']['name']['#title'] = $this->t('E-mail');
    $pane_form['returning_customer']['name']['#required'] = TRUE;
    $pane_form['returning_customer']['name']['#default_value'] = $mail;
    if (!isset($pane_form['returning_customer']['name']['#attributes']) || !is_array($pane_form['returning_customer']['name']['#attributes'])) {
      $pane_form['returning_customer']['name']['#attributes'] = [];
    }
    $pane_form['returning_customer']['name']['#attributes']['autocomplete'] = 'email';
    $pane_form['returning_customer']['name']['#ajax'] = [
      'callback' => [$this, 'emailLookupAjax'],
      'event' => 'blur',
      'wrapper' => 'aculta-checkout-identity-stage',
      'progress' => ['type' => 'throbber'],
    ];
    $pane_form['email_lookup_message'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'aculta-checkout-email-message'],
    ];

    if (!isset($pane_form['returning_customer']['password']) || !is_array($pane_form['returning_customer']['password'])) {
      $pane_form['returning_customer']['password'] = [];
    }
    if (!isset($pane_form['returning_customer']['password']['#attributes']) || !is_array($pane_form['returning_customer']['password']['#attributes'])) {
      $pane_form['returning_customer']['password']['#attributes'] = [];
    }
    $pane_form['returning_customer']['password']['#attributes']['hidden'] = !$known_account;
    $pane_form['returning_customer']['password']['#title'] = $this->t('Senha');
    $pane_form['returning_customer']['password']['#attributes']['autocomplete'] = 'current-password';

    if (!isset($pane_form['returning_customer']['submit']) || !is_array($pane_form['returning_customer']['submit'])) {
      $pane_form['returning_customer']['submit'] = [];
    }
    if (!isset($pane_form['returning_customer']['submit']['#attributes']) || !is_array($pane_form['returning_customer']['submit']['#attributes'])) {
      $pane_form['returning_customer']['submit']['#attributes'] = [];
    }
    $pane_form['returning_customer']['submit']['#attributes']['hidden'] = !$known_account;
    $pane_form['returning_customer']['submit']['#value'] = $this->t('Entrar e continuar');

    if (isset($pane_form['guest']) && is_array($pane_form['guest'])) {
      if (isset($pane_form['guest']['continue']) && is_array($pane_form['guest']['continue'])) {
        $pane_form['guest']['continue']['#access'] = FALSE;
      }
      if (isset($pane_form['guest']['text']) && is_array($pane_form['guest']['text'])) {
        $pane_form['guest']['text']['#markup'] = $this->t('Se este e-mail ainda não tiver conta, o Commerce criará uma automaticamente ao concluir o pedido e enviará um link para definir a senha.');
      }
    }
    if (isset($pane_form['register']) && is_array($pane_form['register'])) {
      $pane_form['register']['#access'] = FALSE;
    }

    $pane_form['new_customer'] = [
      '#attached' => ['library' => ['aculta_portal/checkout-presentation']],
      '#type' => 'fieldset',
      '#title' => $this->t('Cadastro de novo cliente'),
      '#attributes' => [
        'id' => 'aculta-checkout-new-customer',
        'hidden' => $known_account,
        'class' => ['aculta-checkout__guest'],
      ],
      'email' => ['#plain_text' => $mail],
      'description' => ['#markup' => $this->t('Ao concluir o pedido, uma conta será criada em segundo plano e enviaremos um link para você definir sua senha.')],
      'continue' => [
        '#type' => 'submit',
        '#value' => $this->t('Continuar como novo cliente'),
        '#op' => 'continue',
        '#attributes' => ['formnovalidate' => 'formnovalidate'],
        '#limit_validation_errors' => [],
        '#submit' => [],
        // Public store, course, and donation flows all support guest checkout.
        // Do not let stale active checkout config hide the email-first path.
        '#access' => TRUE,
      ],
    ];
    return $pane_form;
  }

  /**
   * Refreshes the checkout email stage after the email field loses focus.
   *
   * @param array<mixed> $form
   */
  public function emailLookupAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    if (!is_array($trigger)) {
      return new AjaxResponse();
    }
    /** @var array<array-key, string|int> $parents */
    $parents = is_array($trigger['#array_parents'] ?? NULL) ? $trigger['#array_parents'] : [];
    array_pop($parents);
    $element = $form;
    foreach ($parents as $parent) {
      if (!isset($element[$parent])) {
        return new AjaxResponse();
      }
      $next = $element[$parent];
      if (!is_array($next)) {
        return new AjaxResponse();
      }
      $element = $next;
    }
    /** @var array<array-key, string> $triggerParents */
    $triggerParents = is_array($trigger['#parents'] ?? NULL) ? $trigger['#parents'] : [];
    $rawMail = $form_state->getValue($triggerParents);
    $mail = is_scalar($rawMail) ? (string) $rawMail : '';

    $request = $this->requestStack->getCurrentRequest();
    $client_ip = $request !== NULL ? (string) $request->getClientIp() : '';
    $flood_event = 'aculta_checkout_email_lookup';
    if ($client_ip !== '' && $this->flood instanceof FloodInterface && !$this->flood->isAllowed($flood_event, 30, 600, $client_ip)) {
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#aculta-checkout-email-message', [
        '#type' => 'container',
        '#attributes' => ['id' => 'aculta-checkout-email-message', 'role' => 'alert'],
        '#plain_text' => $this->t('Não foi possível consultar o e-mail agora. Aguarde alguns minutos e tente novamente.'),
      ]));
      return $response;
    }
    if ($client_ip !== '' && $this->flood instanceof FloodInterface) {
      $this->flood->register($flood_event, 600, $client_ip);
    }
    $exists = $this->accountForMail($mail) !== NULL;
    if (!isset($element['password']) || !is_array($element['password'])) {
      $element['password'] = [];
    }
    if (!isset($element['password']['#attributes']) || !is_array($element['password']['#attributes'])) {
      $element['password']['#attributes'] = [];
    }
    $element['password']['#attributes']['hidden'] = !$exists;

    if (!isset($element['submit']) || !is_array($element['submit'])) {
      $element['submit'] = [];
    }
    if (!isset($element['submit']['#attributes']) || !is_array($element['submit']['#attributes'])) {
      $element['submit']['#attributes'] = [];
    }
    $element['submit']['#attributes']['hidden'] = !$exists;
    $element['submit']['#value'] = $this->t('Entrar e continuar');

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#aculta-checkout-identity-stage', $element));
    $response->addCommand(new ReplaceCommand('#aculta-checkout-email-message', [
      '#type' => 'container',
      '#attributes' => ['id' => 'aculta-checkout-email-message'],
      '#plain_text' => '',
    ]));
    $pane_parents = array_slice($parents, 0, -1);
    $pane = $form;
    foreach ($pane_parents as $parent) {
      if (!isset($pane[$parent])) {
        return $response;
      }
      $nextPane = $pane[$parent];
      if (!is_array($nextPane)) {
        return $response;
      }
      $pane = $nextPane;
    }
    if (isset($pane['new_customer']) && is_array($pane['new_customer'])) {
      $new_customer = $pane['new_customer'];
      if (!isset($new_customer['#attributes']) || !is_array($new_customer['#attributes'])) {
        $new_customer['#attributes'] = [];
      }
      $new_customer['#attributes']['hidden'] = $exists;
      if (!isset($new_customer['email']) || !is_array($new_customer['email'])) {
        $new_customer['email'] = [];
      }
      $new_customer['email']['#plain_text'] = $mail;
      $response->addCommand(new ReplaceCommand('#aculta-checkout-new-customer', $new_customer));
    }
    if (!$exists && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
      $response->addCommand(new InvokeCommand('#aculta-checkout-identity-stage', 'attr', ['data-account-stage', 'guest']));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   * @param array<mixed> $complete_form
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    parent::validatePaneForm($pane_form, $form_state, $complete_form);
    $triggeringElement = $form_state->getTriggeringElement();
    $trigger = is_array($triggeringElement) && isset($triggeringElement['#op']) && is_scalar($triggeringElement['#op'])
      ? (string) $triggeringElement['#op']
      : 'continue';
    if ($trigger !== 'continue') {
      return;
    }
    /** @var array<array-key, string> $parents */
    $parents = is_array($pane_form['#parents'] ?? NULL) ? $pane_form['#parents'] : [];
    $values = $form_state->getValue($parents);
    $rawMail = '';
    if (is_array($values) && isset($values['returning_customer']) && is_array($values['returning_customer'])) {
      $candidate = $values['returning_customer']['name'] ?? '';
      $rawMail = is_scalar($candidate) ? (string) $candidate : '';
    }
    $mail = mb_strtolower(trim($rawMail));
    $returningCustomer = isset($pane_form['returning_customer']) && is_array($pane_form['returning_customer'])
      ? $pane_form['returning_customer']
      : [];
    $nameElement = isset($returningCustomer['name']) && is_array($returningCustomer['name'])
      ? $returningCustomer['name']
      : $pane_form;

    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
      $form_state->setError($nameElement, $this->t('Informe um e-mail válido para continuar.'));
      return;
    }
    if ($this->accountForMail($mail) !== NULL) {
      $form_state->setError($nameElement, $this->t('Este e-mail já possui uma conta. Informe sua senha para entrar.'));
      return;
    }
    $form_state->set('aculta_checkout_guest_mail', $mail);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<mixed> $pane_form
   * @param array<mixed> $complete_form
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form): void {
    $triggeringElement = $form_state->getTriggeringElement();
    $trigger = is_array($triggeringElement) && isset($triggeringElement['#op']) && is_scalar($triggeringElement['#op'])
      ? (string) $triggeringElement['#op']
      : 'continue';
    if ($trigger === 'continue') {
      $mail = $form_state->get('aculta_checkout_guest_mail');
      if (is_string($mail)) {
        $this->order->setEmail($mail);
      }
    }
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * Finds an active account by its exact email address.
   */
  private function accountForMail(string $mail): ?UserInterface {
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
      return NULL;
    }
    $mail = mb_strtolower(trim($mail));
    $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $mail]);
    $account = $accounts ? reset($accounts) : NULL;
    return $account instanceof UserInterface ? $account : NULL;
  }

  /**
   * Builds the existing Portal Google sign-in entry above the email field.
   *
   * @return array<string, mixed>
   */
  private function googleButton(): array {
    if (!$this->portalModuleHandler?->moduleExists('social_auth') || !$this->authIntegration?->isGoogleConfigured()) {
      return [];
    }
    $siteManager = $this->domainSiteManager;
    if ($siteManager === NULL) {
      return [];
    }
    $url = $siteManager->routeUrl(SiteKey::Account, 'social_auth.network.redirect', ['network' => 'google']);
    if (!$url instanceof Url) {
      return [];
    }
    $destination = '/checkout/' . (string) $this->order->id() . '/' . $this->getStepId();
    $query = ['destination' => $destination];
    $siteKey = $siteManager->getCurrentSiteKey();
    if ($siteKey !== NULL) {
      $query['aculta_destination_site_key'] = $siteKey->value;
    }
    $url->setOption('query', $query);
    $link = sprintf('<a class="aculta-checkout__google-link" href="%s">%s <strong>Google</strong></a>', Html::escape($url->toString()), Html::escape((string) $this->t('Continuar com')));
    return [
      '#attached' => ['library' => ['aculta_portal/checkout-presentation']],
      '#type' => 'container',
      '#weight' => -30,
      '#attributes' => ['data-portal-ui' => 'checkout-google'],
      'link' => ['#markup' => Markup::create($link)],
    ];
  }

}
