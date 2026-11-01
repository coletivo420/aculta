<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\RecentUploads;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;

/**
 * Applies the Portal upload policy to LMS File Upload answer forms.
 */
final class SubmissionImageHooks {

  use StringTranslationTrait;

  /**
   * The Portal policy is optional at container level, as in other LMS hooks.
   *
   * The aculta_lms module requires aculta_portal in production, so the cap
   * always applies there.
   */
  public function __construct(
    TranslationInterface $stringTranslation,
    private readonly ?ConsumerUploadPolicy $uploadPolicy = NULL,
    private readonly ?EntityTypeManagerInterface $entityTypeManager = NULL,
    private readonly ?AccountProxyInterface $currentUser = NULL,
    private readonly ?RecentUploads $recentUploads = NULL,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Caps the contrib managed upload element to the LMS upload policy.
   *
   * The activity still chooses its extensions, but only inside the consumer
   * policy from aculta_portal. The ACULTA image constraint then inspects raster
   * image bytes; other extensions keep their native validation.
   */
  #[Hook('form_lms_answer_form_alter')]
  public function answerFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['file_upload']['#upload_validators']) || !is_array($form['file_upload']['#upload_validators'])) {
      return;
    }

    $validators = $form['file_upload']['#upload_validators'];
    $validators['AcultaLmsSubmissionImage'] = [];
    if ($this->uploadPolicy !== NULL) {
      $validators = $this->uploadPolicy->restrictValidators($validators, 'lms');
      // The help text lists what is accepted, not what the activity asked for.
      $form['file_upload']['#description'] = $this->t('Envie um arquivo. Extensões permitidas: @extensions.', [
        '@extensions' => $this->uploadPolicy->acceptedExtensions($validators),
      ]);
    }
    $form['file_upload']['#upload_validators'] = $validators;
    $this->addStudioPick($form);
  }

  /**
   * Adds the Studio choice: a recent file of the aluno, used when the upload is empty.
   */
  private function addStudioPick(array &$form): void {
    if ($this->recentUploads === NULL || $this->uploadPolicy === NULL || $this->currentUser === NULL) {
      return;
    }
    $isAdmin = $this->currentUser->hasPermission('administer site configuration');
    $allowed = array_merge($this->uploadPolicy->imageExtensions('lms'), $this->uploadPolicy->documentExtensions('lms'));
    $options = array_filter(
      $this->recentUploads->forUser((int) $this->currentUser->id(), 'lms', 20, $isAdmin),
      static fn (string $name): bool => in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $allowed, TRUE),
    );
    if ($options === []) {
      return;
    }
    $form['studio_file'] = [
      '#type' => 'select',
      '#title' => $this->t('Ou escolha um envio recente do Studio'),
      '#options' => ['' => $this->t('- Nenhum -')] + $options,
      '#default_value' => '',
      '#description' => $this->t('Usado somente se nenhum arquivo for enviado acima.'),
    ];
    // The required check runs before form validators; validateStudioPick covers it.
    $this->relax($form['file_upload']);
    $form['#validate'] = array_merge(
      ['Drupal\aculta_lms\Hook\SubmissionImageHooks:validateStudioPick'],
      $form['#validate'] ?? [],
    );
  }

  /**
   * Turns the Studio choice into the answer file, when the upload is empty.
   */
  public function validateStudioPick(array &$form, FormStateInterface $form_state): void {
    $pick = (int) $form_state->getValue('studio_file');
    $upload = $form_state->getValue('file_upload');
    $hasUpload = is_array($upload) ? array_filter($upload) !== [] : (int) $upload > 0;

    if ($pick === 0) {
      if (!$hasUpload) {
        $form_state->setErrorByName('file_upload', $this->t('Envie um arquivo ou escolha um envio recente do Studio.'));
      }
      return;
    }
    if ($hasUpload) {
      $form_state->setErrorByName('studio_file', $this->t('Envie um arquivo ou escolha um envio recente, não os dois.'));
      return;
    }
    if ($form_state->hasAnyErrors() || $this->entityTypeManager === NULL || $this->recentUploads === NULL || $this->currentUser === NULL) {
      return;
    }

    $file = $this->entityTypeManager->getStorage('file')->load($pick);
    $isAdmin = $this->currentUser->hasPermission('administer site configuration');
    $isImage = $file instanceof FileInterface && in_array(strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION)), $this->uploadPolicy->imageExtensions('lms'), TRUE);
    $valid = $file instanceof FileInterface
      && $this->recentUploads->isOwnedBy($file, (int) $this->currentUser->id(), 'lms', $isAdmin)
      && ($isImage ? $this->uploadPolicy->isValidImage($file, 'lms') : $this->uploadPolicy->isValidDocument($file, 'lms'));
    if (!$valid) {
      $form_state->setErrorByName('studio_file', $this->t('O arquivo escolhido não está disponível. Escolha novamente.'));
      return;
    }
    // O contrib lê o arquivo como data['file_upload'][0].
    $form_state->setValue('file_upload', [(int) $file->id()]);
  }

  /**
   * Removes the required flag from the widget tree. validateStudioPick covers it.
   */
  private function relax(array &$element): void {
    if (isset($element['#required'])) {
      $element['#required'] = FALSE;
    }
    foreach (Element::children($element) as $child) {
      $this->relax($element[$child]);
    }
  }

}
