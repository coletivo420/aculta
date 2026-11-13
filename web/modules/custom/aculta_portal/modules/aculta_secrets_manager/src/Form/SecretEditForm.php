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
 * Edits the value of one credential in a popup.
 *
 * Other variables remain unchanged. The submitted value is not echoed or
 * logged; only the variable name is recorded.
 */
final class SecretEditForm extends FormBase implements ContainerInjectionInterface {

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
    return 'aculta_secret_edit_form';
  }

  /**
   * Builds the credential edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $name = ''): array {
    $form['#attributes']['data-portal-ui'] = 'secret-dialog';
    $status = $this->secrets->status();
    if (!isset($status[$name])) {
      throw new NotFoundHttpException();
    }
    $form_state->set('name', $name);
    $form['#prefix'] = '<div id="aculta-secret-edit">';
    $form['#suffix'] = '</div>';
    $form['variable'] = ['#type' => 'item', '#title' => $this->t('Variável'), '#plain_text' => $name];
    $form['value'] = [
      '#type' => 'password',
      '#title' => $this->t('Novo valor'),
      '#description' => $status[$name]['present'] ? $this->t('Substitui o valor atual. O valor não é mostrado de volta.') : $this->t('Ainda sem valor.'),
      '#required' => TRUE,
      '#autocomplete' => 'new-password',
      '#attributes' => ['spellcheck' => 'false', 'autocapitalize' => 'off'],
    ];
    $form['actions'] = ['#type' => 'actions'];
    if (!$this->secrets->canSaveHere()) {
      $form['storage_notice'] = [
        '#component' => 'aculta420_v2:status-messages',
        '#props' => ['type' => 'warning', 'messages' => [(string) $this->t('⚠ Neste ambiente as credenciais são armazenadas no banco de dados. O formulário não grava aqui.')]],
      ];
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar'), '#disabled' => TRUE];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Salvar'),
      ];
      // Erro de validação devolve o popup; sucesso navega com HX-Redirect
      // (a navegação fecha o diálogo).
      (new Htmx())
        ->post(Url::fromRoute('aculta_portal.secrets_edit', ['name' => $name]))
        ->select('#aculta-secret-edit')
        ->target('#aculta-secret-edit')
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
    $result = $this->secrets->save([$name => (string) $form_state->getValue('value')]);
    if ($result['ok']) {
      $this->messenger()->addStatus($this->t('@message', ['@message' => $result['message']]));
    }
    else {
      $this->messenger()->addError($this->t('Não salvo: @reason', ['@reason' => $result['message']]));
    }
    $url = Url::fromRoute('aculta_portal.secrets_import');
    if ($this->isHtmxRequest()) {
      $form_state->setResponse(new Response('', Response::HTTP_OK, ['HX-Redirect' => $url->toString()]));
      return;
    }
    $form_state->setRedirectUrl($url);
  }

}
