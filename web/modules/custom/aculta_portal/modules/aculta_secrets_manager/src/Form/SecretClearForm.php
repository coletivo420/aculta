<?php

declare(strict_types=1);

namespace Drupal\aculta_secrets_manager\Form;

use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirms removal of a credential value.
 *
 * The variable is removed from this environment's credentials file. Other
 * variables remain, and only the variable name is logged.
 */
final class SecretClearForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    protected readonly SecretsManager $secrets,
  ) {}

  /**
   * Creates the form from the service container.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.secrets_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_secret_clear_form';
  }

  /**
   * Builds the credential removal confirmation form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $name = ''): array {
    $form['#attributes']['data-portal-ui'] = 'secret-dialog';
    $status = $this->secrets->status();
    if (!isset($status[$name])) {
      throw new NotFoundHttpException();
    }
    $form_state->set('name', $name);
    $form['#prefix'] = '<div id="aculta-secret-clear">';
    $form['#suffix'] = '</div>';
    $form['variable'] = ['#type' => 'item', '#title' => $this->t('Variável'), '#plain_text' => $name];
    $form['warning'] = [
      '#component' => 'aculta420_v2:status-messages',
      '#props' => ['type' => 'warning', 'messages' => [(string) $this->t('Apagar o valor remove a variável do arquivo de credenciais deste ambiente. Para usar a credencial de novo, será preciso cadastrar o valor outra vez.')]],
    ];
    $form['actions'] = ['#type' => 'actions'];
    if (!$this->secrets->canSaveHere()) {
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Apagar valor'), '#disabled' => TRUE];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Apagar valor'),
        '#button_type' => 'danger',
      ];
      (new Htmx())
        ->post(Url::fromRoute('aculta_portal.secrets_clear', ['name' => $name]))
        ->select('#aculta-secret-clear')
        ->target('#aculta-secret-clear')
        ->swap('outerHTML')
        ->onlyMainContent()
        ->applyTo($form['actions']['submit']);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = (string) $form_state->get('name');
    $result = $this->secrets->clear($name);
    if ($result['ok']) {
      $this->messenger()->addStatus($this->t('@message', ['@message' => $result['message']]));
    }
    else {
      $this->messenger()->addError($this->t('Não apagado: @reason', ['@reason' => $result['message']]));
    }
    $url = Url::fromRoute('aculta_portal.secrets_import');
    if ($this->isHtmxRequest()) {
      $form_state->setResponse(new Response('', Response::HTTP_OK, ['HX-Redirect' => $url->toString()]));
      return;
    }
    $form_state->setRedirectUrl($url);
  }

}
