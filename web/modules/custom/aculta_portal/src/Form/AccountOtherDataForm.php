<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Form;

use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Outros Dados: CPF ou CNPJ da pessoa, gravado uma única vez.
 *
 * Antes de salvar, mostra o campo. Depois, mostra o valor formatado em somente leitura. A validação e a
 * cifra ficam no contrato AccountDocumentInterface (implementado pelo aculta_id_cpf). O formulário nunca
 * inclui a entrada em mensagens de erro.
 */
final class AccountOtherDataForm extends FormBase {

  public function __construct(
    private readonly ?AccountDocumentInterface $document,
    private readonly AccountInterface $currentUser,
    private readonly EntityTypeManagerInterface $entities,
    private readonly MessengerInterface $messages,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->has('aculta_id_cpf.account_document') ? $container->get('aculta_id_cpf.account_document') : NULL,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
    );
  }

  public function getFormId(): string {
    return 'aculta_portal_account_other_data';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache'] = ['contexts' => ['user'], 'max-age' => 0];
    if ($this->document === NULL || !$this->document->isAvailable()) {
      return $this->unavailableForm($form);
    }

    try {
      $saved = $this->document->savedDocument($this->user());
    }
    catch (\RuntimeException) {
      $form['unavailable'] = ['#plain_text' => (string) $this->t('Não foi possível ler o documento salvo. Tente novamente mais tarde.')];
      return $form;
    }

    if ($saved !== NULL) {
      // A máscara completa do CNPJ tem barra; a do CPF não. O rótulo mostra o tipo que foi gravado.
      // Campo desabilitado: mesma caixa da digitação, sem edição e sem envio. O valor já vem mascarado do serviço.
      $form['saved'] = [
        '#type' => 'textfield',
        '#title' => str_contains($saved, '/') ? $this->t('CNPJ') : $this->t('CPF'),
        '#default_value' => $saved,
        '#disabled' => TRUE,
        '#size' => 20,
        '#attributes' => ['data-portal-ui' => 'account-document-input'],
        '#description' => $this->t('Documento salvo. Ele não pode ser alterado.'),
      ];
      return $form;
    }

    if (!$this->document->canSave()) {
      return $this->unavailableForm($form);
    }

    $form['document'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CPF ou CNPJ'),
      '#description' => $this->t('CPF: 000.000.000-00. CNPJ: 00.000.000/0000-00. Pode digitar sem pontuação. Depois de salvo, não poderá ser alterado.'),
      '#required' => TRUE,
      '#size' => 20,
      '#attributes' => ['data-portal-ui' => 'account-document-input'],
      '#maxlength' => 64,
      '#autocomplete' => 'off',
      '#spellcheck' => 'false',
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($this->document === NULL) {
      $form_state->setErrorByName('document', $this->unavailableMessage());
      return;
    }
    $canonical = $this->document->canonicalize((string) $form_state->getValue('document'));
    if ($canonical === NULL) {
      $form_state->setErrorByName('document', $this->t('Informe um CPF ou CNPJ válido.'));
      return;
    }
    $form_state->set('canonical', $canonical);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->document->save($this->user(), (string) $form_state->get('canonical'));
      $this->messages->addStatus($this->t('Documento salvo.'));
    }
    catch (\LogicException) {
      // Mesma mensagem para "já cadastrado em outra conta" e "já salvo": não revela se o documento existe.
      $this->messages->addError($this->t('Não foi possível salvar este documento.'));
    }
    catch (\RuntimeException) {
      $this->messages->addError($this->t('Não foi possível salvar agora. Tente novamente mais tarde.'));
    }
    $form_state->setRedirect('aculta_portal.my_data_other');
  }

  /**
   * Formulário de indisponibilidade: uma única mensagem para todos os caminhos de "cadastro indisponível".
   */
  private function unavailableForm(array $form): array {
    $form['unavailable'] = ['#plain_text' => (string) $this->unavailableMessage()];
    return $form;
  }

  private function unavailableMessage(): TranslatableMarkup {
    return $this->t('Este cadastro não está disponível neste ambiente.');
  }

  private function user(): \Drupal\user\UserInterface {
    return $this->entities->getStorage('user')->load($this->currentUser->id());
  }

}
