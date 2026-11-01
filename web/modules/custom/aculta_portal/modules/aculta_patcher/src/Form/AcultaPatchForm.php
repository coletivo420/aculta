<?php

declare(strict_types=1);

namespace Drupal\aculta_patcher\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\aculta_patcher\PatchFormRules;

/**
 * Formulário administrativo de registro de patch. */
final class AcultaPatchForm extends EntityForm {

  private const MAX_PATCH_BYTES = 262144;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\aculta_patcher\Entity\AcultaPatch $patch */
    $patch = $this->entity;
    $form['notice'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'notice-warning'],
      'text' => ['#markup' => $this->t('O estado ativo inclui este diff no manifesto do próximo build Composer. Salvar aqui não altera arquivos de código nem ativa o patch no site em execução; exporte a configuração, gere os artefatos e revise o commit antes do deploy.')],
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Nome do patch'),
      '#default_value' => $patch->label(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#title' => $this->t('Identificador'),
      '#default_value' => $patch->id(),
      '#machine_name' => ['exists' => [$this, 'exists'], 'source' => ['label']],
      '#disabled' => !$patch->isNew(),
    ];
    $form['package'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pacote Composer'),
      '#description' => $this->t('Nome exato do pacote, por exemplo drupal/h5p.'),
      '#default_value' => $patch->getPackage(),
      '#required' => TRUE,
      '#maxlength' => 128,
    ];
    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Versão exata validada'),
      '#description' => $this->t('O exportador só inclui o patch se o pacote instalado tiver esta versão exata.'),
      '#default_value' => $patch->getVersion(),
      '#required' => TRUE,
      '#maxlength' => 64,
    ];
    $form['issue_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Issue ou referência upstream'),
      '#default_value' => $patch->getIssueUrl(),
      '#maxlength' => 2048,
    ];
    $form['patch'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Patch/snippet em unified diff'),
      '#description' => $this->t('Cole um diff revisado. Código inserido pode executar com os privilégios do site; mantenha o acesso restrito e registre origem, versão e revisão.'),
      '#default_value' => $patch->getPatch(),
      '#required' => TRUE,
      '#rows' => 24,
      '#maxlength' => self::MAX_PATCH_BYTES,
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ativo para o próximo build Composer'),
      '#description' => $this->t('O patch só será aplicado quando os artefatos exportados forem revisados e Composer install for executado.'),
      '#default_value' => $patch->status(),
    ];
    return $form;
  }

  /**
   * Checks whether a patch identifier is already in use.
   */
  public function exists(string $id): bool {
    return (bool) $this->entityTypeManager->getStorage('aculta_patch')->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $messages = [
      'package_invalid' => $this->t('Informe um nome de pacote Composer válido.'),
      'version_not_exact' => $this->t('Informe uma versão exata do pacote, sem faixa de versões.'),
      'patch_too_large' => $this->t('O diff excede o limite de 256 KiB.'),
      'patch_not_unified' => $this->t('O conteúdo precisa ser um unified diff com arquivos e ao menos um bloco de alteração.'),
      'patch_path_escapes' => $this->t('O diff não pode apontar para caminhos absolutos ou sair do pacote.'),
    ];
    $errors = (new PatchFormRules())->errors(
      (string) $form_state->getValue('package'),
      (string) $form_state->getValue('version'),
      (string) $form_state->getValue('patch'),
    );
    foreach ($errors as $field => $code) {
      $form_state->setErrorByName($field, $messages[$code]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\aculta_patcher\Entity\AcultaPatch $patch */
    $patch = $this->entity;
    $patch->set('label', trim((string) $form_state->getValue('label')));
    $patch->set('package', trim((string) $form_state->getValue('package')));
    $patch->set('version', trim((string) $form_state->getValue('version')));
    $patch->set('issue_url', trim((string) $form_state->getValue('issue_url')));
    $patch->set('patch', rtrim((string) $form_state->getValue('patch')) . "\n");
    $patch->setStatus((bool) $form_state->getValue('status'));
    $status = $patch->save();
    $this->messenger()->addStatus($this->t('O patch %label foi salvo. Gere e revise os artefatos Composer antes de aplicá-lo.', ['%label' => $patch->label()]));
    $form_state->setRedirect('entity.aculta_patch.collection');
    return $status;
  }

}
