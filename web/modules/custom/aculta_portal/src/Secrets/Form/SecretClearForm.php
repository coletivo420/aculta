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
 * Confirmação para apagar o valor de uma credencial. A variável sai do arquivo de credenciais deste
 * ambiente; as demais permanecem. Só o nome vai ao log.
 */
final class SecretClearForm extends FormBase implements ContainerInjectionInterface {

  public function __construct(
    private readonly SecretsManager $secrets,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.secrets_manager'),
    );
  }

  public function getFormId(): string {
    return 'aculta_secret_clear_form';
  }

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
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'notice-warning'],
      'text' => ['#plain_text' => (string) $this->t('Apagar o valor remove a variável do arquivo de credenciais deste ambiente. Para usar a credencial de novo, será preciso cadastrar o valor outra vez.')],
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
        '#ajax' => ['callback' => '::ajaxSubmit', 'wrapper' => 'aculta-secret-clear'],
      ];
    }
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $name = (string) $form_state->get('name');
    $result = $this->secrets->clear($name);
    if ($result['ok']) {
      $this->messenger()->addStatus($this->t('@message', ['@message' => $result['message']]));
    }
    else {
      $this->messenger()->addError($this->t('Não apagado: @reason', ['@reason' => $result['message']]));
    }
  }

  /** Com erro, devolve a própria confirmação; sem erro, fecha e volta à página de credenciais. */
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
