<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Secrets\Form;

use Drupal\aculta_portal\Secrets\SecretsManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Popup para alterar o valor de uma credencial. Grava só essa variável; as demais permanecem.
 * O valor digitado não é mostrado de volta e não é registrado no log (só o nome).
 */
final class SecretEditForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly SecretsManager $secrets,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.secrets_manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_secret_edit_form';
  }

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
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'notice-warning'],
        'text' => ['#plain_text' => (string) $this->t('⚠ Neste ambiente as credenciais são armazenadas no banco de dados. O formulário não grava aqui.')],
      ];
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar'), '#disabled' => TRUE];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Salvar'),
        '#ajax' => ['callback' => '::ajaxSubmit', 'wrapper' => 'aculta-secret-edit'],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = (string) $form_state->get('name');
    $result = $this->secrets->save([$name => (string) $form_state->getValue('value')]);
    if ($result['ok']) {
      $this->messenger()->addStatus($this->t('@message', ['@message' => $result['message']]));
    }
    else {
      $this->messenger()->addError($this->t('Não salvo: @reason', ['@reason' => $result['message']]));
    }
  }

  /** Com erro de validação, devolve o próprio popup; sem erro, fecha e volta à página de credenciais. */
  public function ajaxSubmit(array $form, FormStateInterface $form_state): array|AjaxResponse {
    if ($form_state->hasAnyErrors()) {
      return $form;
    }
    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new RedirectCommand(Url::fromRoute('aculta_portal.secrets_import')->toString()));
    return $response;
  }

}
