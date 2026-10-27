<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Plugin\Commerce\CheckoutPane;

use Drupal\Component\Utility\Html;
use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
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
   * Domain-purpose URL resolver. */
  private ?DomainPurposeManager $domainPurpose = NULL;

  /**
   * Configured Social Auth integrations. */
  private ?AuthIntegrationManager $authIntegration = NULL;

  /**
   * Module availability checker. */
  private ?ModuleHandlerInterface $portalModuleHandler = NULL;

  /**
   * Per-IP rate limiter for anonymous account lookup. */
  private ?FloodInterface $flood = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->domainPurpose = $container->get('aculta_portal.domain_purpose');
    $instance->authIntegration = $container->get('aculta_portal.auth_integration');
    $instance->portalModuleHandler = $container->get('module_handler');
    $instance->flood = $container->get('flood');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form = parent::buildPaneForm($pane_form, $form_state, $complete_form);
    $mail = (string) ($form_state->getValue(array_merge($pane_form['#parents'], ['returning_customer', 'name'])) ?? '');
    $known_account = $this->accountForMail($mail) !== NULL;

    $pane_form['google'] = $this->googleButton();
    if ($pane_form['google'] !== []) {
      $pane_form['google']['#weight'] = -30;
    }

    $pane_form['returning_customer']['#title'] = $this->t('Identifique-se para continuar');
    $pane_form['returning_customer']['#attributes']['id'] = 'aculta-checkout-identity-stage';
    $pane_form['returning_customer']['name']['#type'] = 'email';
    $pane_form['returning_customer']['name']['#title'] = $this->t('E-mail');
    $pane_form['returning_customer']['name']['#required'] = TRUE;
    $pane_form['returning_customer']['name']['#default_value'] = $mail;
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
    $pane_form['returning_customer']['password']['#attributes']['hidden'] = !$known_account;
    $pane_form['returning_customer']['password']['#title'] = $this->t('Senha');
    $pane_form['returning_customer']['password']['#attributes']['autocomplete'] = 'current-password';
    $pane_form['returning_customer']['submit']['#attributes']['hidden'] = !$known_account;
    $pane_form['returning_customer']['submit']['#value'] = $this->t('Entrar e continuar');
    $pane_form['guest']['continue']['#access'] = FALSE;
    $pane_form['register']['#access'] = FALSE;
    $pane_form['guest']['text']['#markup'] = $this->t('Se este e-mail ainda não tiver conta, o Commerce criará uma automaticamente ao concluir o pedido e enviará um link para definir a senha.');
    $pane_form['new_customer'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cadastro de novo cliente'),
      '#attributes' => [
        'id' => 'aculta-checkout-new-customer',
        'hidden' => $known_account,
        'data-portal-ui' => 'checkout-guest-registration',
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
        '#access' => !empty($this->configuration['allow_guest_checkout']),
      ],
    ];
    return $pane_form;
  }

  /**
   * Refreshes the checkout email stage after the email field loses focus.
   */
  public function emailLookupAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#array_parents'] ?? [];
    array_pop($parents);
    $element = $form;
    foreach ($parents as $parent) {
      if (!isset($element[$parent])) {
        return new AjaxResponse();
      }
      $element = $element[$parent];
    }
    $mail = (string) ($form_state->getValue($trigger['#parents']) ?? '');
    $client_ip = (string) $this->requestStack->getCurrentRequest()->getClientIp();
    $flood_event = 'aculta_checkout_email_lookup';
    if ($client_ip !== '' && !$this->flood?->isAllowed($flood_event, 30, 600, $client_ip)) {
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#aculta-checkout-email-message', [
        '#type' => 'container',
        '#attributes' => ['id' => 'aculta-checkout-email-message', 'role' => 'alert'],
        '#plain_text' => $this->t('Não foi possível consultar o e-mail agora. Aguarde alguns minutos e tente novamente.'),
      ]));
      return $response;
    }
    if ($client_ip !== '') {
      $this->flood?->register($flood_event, 600, $client_ip);
    }
    $exists = $this->accountForMail($mail) !== NULL;
    $element['password']['#attributes']['hidden'] = !$exists;
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
      $pane = $pane[$parent];
    }
    $new_customer = $pane['new_customer'];
    $new_customer['#attributes']['hidden'] = $exists;
    $new_customer['email']['#plain_text'] = $mail;
    $response->addCommand(new ReplaceCommand('#aculta-checkout-new-customer', $new_customer));
    if (!$exists && filter_var($mail, FILTER_VALIDATE_EMAIL)) {
      $response->addCommand(new InvokeCommand('#aculta-checkout-identity-stage', 'attr', ['data-account-stage', 'guest']));
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    parent::validatePaneForm($pane_form, $form_state, $complete_form);
    $trigger = $form_state->getTriggeringElement()['#op'] ?? 'continue';
    if ($trigger !== 'continue') {
      return;
    }
    $values = $form_state->getValue($pane_form['#parents']);
    $mail = mb_strtolower(trim((string) ($values['returning_customer']['name'] ?? '')));
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
      $form_state->setError($pane_form['returning_customer']['name'], $this->t('Informe um e-mail válido para continuar.'));
      return;
    }
    if ($this->accountForMail($mail) !== NULL) {
      $form_state->setError($pane_form['returning_customer']['name'], $this->t('Este e-mail já possui uma conta. Informe sua senha para entrar.'));
      return;
    }
    $form_state->set('aculta_checkout_guest_mail', $mail);
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    if (($form_state->getTriggeringElement()['#op'] ?? 'continue') === 'continue') {
      $mail = $form_state->get('aculta_checkout_guest_mail');
      if (is_string($mail)) {
        $this->order->setEmail($mail);
      }
    }
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
  }

  /**
   * Finds an active account by its exact email address. */
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
   */
  private function googleButton(): array {
    if (!$this->portalModuleHandler?->moduleExists('social_auth') || !$this->authIntegration?->isGoogleConfigured()) {
      return [];
    }
    $url = $this->domainPurpose?->routeUrl('account', 'social_auth.network.redirect', ['network' => 'google']);
    if (!$url instanceof Url) {
      return [];
    }
    $destination = '/checkout/' . $this->order->id() . '/' . $this->getStepId();
    $query = ['destination' => $destination];
    $purpose = $this->domainPurpose->getCurrentPurpose();
    if ($purpose !== NULL) {
      $query['aculta_destination_purpose'] = $purpose;
    }
    $url->setOption('query', $query);
    $link = sprintf('<a href="%s" data-portal-ui="auth-google">%s <strong>Google</strong></a>', Html::escape($url->toString()), Html::escape((string) $this->t('Continuar com')));
    return [
      '#type' => 'container',
      '#weight' => -30,
      '#attributes' => ['data-portal-ui' => 'checkout-google'],
      'link' => ['#markup' => Markup::create($link)],
    ];
  }

}
