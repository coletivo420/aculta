<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Upload;

use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Validates and stores the lead image of a Wiki entry as image Media.
 *
 * The upload controls live in the "Imagem de destaque" tab of the verbete
 * form. This service owns both halves so the same rules run on add and edit:
 * the policy validators from aculta_portal (consumer "wiki") decide what is
 * accepted, and an image Media connects the file to
 * field_wiki_image.
 */
final class WikiLeadImageUploader {

  use StringTranslationTrait;

  /**
   * Node field that holds the lead image.
   */
  public const FIELD = 'field_wiki_image';

  /**
   * Consumer key in the central Portal upload policy.
   */
  public const CONSUMER = 'wiki';

  /**
   * Details tab that groups the upload controls in the verbete form.
   */
  public const TAB = 'wiki_lead_image';

  /**
   * Managed file element inside the tab.
   */
  public const FILE = 'file';

  /**
   * Alt text element inside the tab.
   */
  public const ALT = 'alt';

  /**
   * Checkbox that clears the current image.
   */
  public const REMOVE = 'remove';

  /**
   * Form state storage key for the Media created during a submission.
   */
  public const STORAGE_KEY = 'aculta_wiki.lead_image';

  /**
   * Private directory that receives the verbete images.
   */
  public const DIRECTORY = 'private://wiki/imagens';

  /**
   * Maximum length of the alt text, as in the Forum upload dialog.
   */
  public const ALT_MAX_LENGTH = 512;

  /**
   * Transaction spanning the lead Media and its Wiki Node submission.
   */
  private ?Transaction $submissionTransaction = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly FileSystemInterface $fileSystem,
    private readonly UserUploadPolicy $uploadPolicy,
    private readonly AccountProxyInterface $currentUser,
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * Rejects an upload outside the "wiki" consumer policy or without alt text.
   *
   * Runs for the whole form, so an untouched tab costs one empty-file check.
   */
  public function validateUpload(array &$form, FormStateInterface $form_state): void {
    $fids = $this->uploadedFids($form_state);
    if ($fids === []) {
      return;
    }
    if (count($fids) > 1) {
      $form_state->setErrorByName($this->errorName(self::FILE), $this->t('Envie uma imagem por vez.'));
      return;
    }

    $alt = $this->altValue($form_state);
    if ($alt === '' || mb_strlen($alt) > self::ALT_MAX_LENGTH) {
      $form_state->setErrorByName($this->errorName(self::ALT), $this->t('Informe uma descrição não vazia de até @length caracteres para a imagem de destaque.', [
        '@length' => self::ALT_MAX_LENGTH,
      ]));
      return;
    }

    $file = $this->entityTypeManager->getStorage('file')->load((int) reset($fids));
    if (!$file instanceof FileInterface) {
      $form_state->setErrorByName($this->errorName(self::FILE), $this->t('A imagem enviada não está disponível. Envie-a novamente.'));
      return;
    }
    if (!$this->uploadPolicy->isValidImage($file, self::CONSUMER)) {
      $form_state->setErrorByName($this->errorName(self::FILE), $this->t('A imagem não atende às extensões ou ao tamanho permitidos.'));
    }
  }

  /**
   * Creates the image Media and points field_wiki_image at it.
   *
   * Prepended to the form's submit handlers so it runs before the node is
   * saved. Without a new upload it only honours the removal checkbox.
   */
  public function applyUpload(array &$form, FormStateInterface $form_state): void {
    $fids = $this->uploadedFids($form_state);
    if ($fids === []) {
      if ($form_state->getValue([self::TAB, self::REMOVE])) {
        $form_state->setValue([self::FIELD], []);
      }
      return;
    }

    $fid = (int) reset($fids);
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file instanceof FileInterface || !$this->uploadPolicy->isValidImage($file, self::CONSUMER)) {
      $form_state->setErrorByName($this->errorName(self::FILE), $this->t('A imagem não atende às extensões ou ao tamanho permitidos.'));
      return;
    }

    $this->submissionTransaction ??= $this->database->startTransaction();
    try {
      $directory = self::DIRECTORY;
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \UnexpectedValueException('O diretório privado das imagens de destaque da Wiki não está disponível.');
      }
      $media = $this->storedMedia($form_state, $fid);
      if ($media === NULL) {
        // The file and Media rows must commit or roll back with the Wiki Node.
        // The private scheme also prevents a draft's file URL bypassing Media
        // entity access before that transaction is complete.
        if ($file->isTemporary()) {
          $file->setPermanent();
          $file->save();
        }
        $media = $this->entityTypeManager->getStorage('media')->create([
          'bundle' => 'image',
          'name' => $file->getFilename(),
          'uid' => $this->currentUser->id(),
          'status' => FALSE,
        ]);
        if (!$media instanceof MediaInterface) {
          throw new \UnexpectedValueException('A mídia da imagem de destaque da Wiki não pôde ser criada.');
        }
        $media->set('field_media_image', [
          'target_id' => $fid,
          'alt' => $this->altValue($form_state),
        ]);
        $media->save();
        $form_state->set(self::STORAGE_KEY, ['fid' => $fid, 'media_id' => (int) $media->id()]);
      }

      $form_state->setValue([self::FIELD], [['target_id' => (int) $media->id()]]);
    }
    catch (\Throwable $exception) {
      $this->rollbackSubmission($form_state);
      throw $exception;
    }
  }

  /**
   * Aligns new Media status with the submitted Wiki revision.
   */
  public function prepareNodeSave(array &$form, FormStateInterface $form_state): void {
    if ($this->submissionTransaction === NULL) {
      return;
    }

    try {
      $stored = $form_state->get(self::STORAGE_KEY);
      $media = is_array($stored)
        ? $this->entityTypeManager->getStorage('media')->load((int) ($stored['media_id'] ?? 0))
        : NULL;
      $formObject = $form_state->getFormObject();
      $node = $formObject instanceof EntityFormInterface ? $formObject->getEntity() : NULL;
      $imageTargetId = $node instanceof NodeInterface && $node->hasField(self::FIELD)
        ? $node->get(self::FIELD)->target_id
        : NULL;
      if (!$media instanceof MediaInterface
        || !$node instanceof NodeInterface
        || $node->bundle() !== 'wiki_entry'
        || (string) $imageTargetId !== (string) $media->id()) {
        throw new \UnexpectedValueException('O formulário não manteve a referência esperada da imagem de destaque da Wiki.');
      }

      // This row remains uncommitted until the Node save succeeds. Pending
      // revisions therefore never make their lead Media publicly accessible.
      $media->set('status', $node->isPublished());
      $media->save();
    }
    catch (\Throwable $exception) {
      $this->rollbackSubmission($form_state);
      throw $exception;
    }
  }

  /**
   * Reconciles lead image publication after a Wiki Node is saved.
   *
   * The current default revision controls this verbete's public image. Shared
   * Media remains published while another published Node still references it.
   */
  public function syncPublishedMedia(NodeInterface $node, bool $deleted = FALSE): void {
    if ($node->bundle() !== 'wiki_entry'
      || !$node->hasField(self::FIELD)
      || $node->get(self::FIELD)->isEmpty()) {
      return;
    }

    $media = $node->get(self::FIELD)->entity;
    if (!$media instanceof MediaInterface || $media->bundle() !== 'image') {
      return;
    }

    $publicReferences = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition(self::FIELD, $media->id())
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();
    $shouldPublish = (!$deleted && $node->isPublished()) || $publicReferences !== [];
    if ($media->isPublished() === $shouldPublish) {
      return;
    }

    $shouldPublish ? $media->setPublished() : $media->setUnpublished();
    $media->save();
  }

  /**
   * Saves the Node and commits or rolls back its staged lead image atomically.
   */
  public function saveNode(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      $this->rollbackSubmission($form_state);
      throw new \UnexpectedValueException('O formulário da Wiki não é um formulário de entidade.');
    }

    try {
      $formObject->save($form, $form_state);
    }
    catch (\Throwable $exception) {
      $this->rollbackSubmission($form_state);
      throw $exception;
    }

    if ($form_state->isRebuilding()) {
      $this->rollbackSubmission($form_state);
    }
    else {
      $this->commitSubmission();
    }
  }

  /**
   * Returns the Media already created for this upload in the same form state.
   *
   * A rebuild after a submit error resubmits the same file; reusing the Media
   * avoids a duplicate on every attempt.
   */
  private function storedMedia(FormStateInterface $form_state, int $fid): ?MediaInterface {
    $stored = $form_state->get(self::STORAGE_KEY);
    if (!is_array($stored) || (int) ($stored['fid'] ?? 0) !== $fid) {
      return NULL;
    }
    $media = $this->entityTypeManager->getStorage('media')->load((int) ($stored['media_id'] ?? 0));
    return $media instanceof MediaInterface ? $media : NULL;
  }

  /**
   * Returns the submitted file IDs of the managed file element.
   */
  private function uploadedFids(FormStateInterface $form_state): array {
    return array_values(array_filter(array_map('intval', (array) $form_state->getValue([self::TAB, self::FILE]))));
  }

  /**
   * Returns the trimmed alt text submitted with the image.
   */
  private function altValue(FormStateInterface $form_state): string {
    return trim((string) $form_state->getValue([self::TAB, self::ALT]));
  }

  /**
   * Builds the nested error key of an element inside the upload tab.
   */
  private function errorName(string $element): string {
    return self::TAB . '][' . $element;
  }

  /**
   * Commits the image transaction after a successful Node save.
   */
  private function commitSubmission(): void {
    $transaction = $this->submissionTransaction;
    $this->submissionTransaction = NULL;
    $transaction?->commitOrRelease();
  }

  /**
   * Rolls back the image transaction after a failed Node submission.
   */
  private function rollbackSubmission(FormStateInterface $form_state): void {
    $transaction = $this->submissionTransaction;
    $this->submissionTransaction = NULL;
    $transaction?->rollBack();

    // Database rollback does not invalidate entity objects already loaded in
    // Drupal's static caches. A rebuilt form may retry the same managed file,
    // so make it look temporary again and discard the rolled-back Media ID.
    $stored = $form_state->get(self::STORAGE_KEY);
    $fid = $this->uploadedFids($form_state)[0] ?? (is_array($stored) ? (int) ($stored['fid'] ?? 0) : 0);
    if ($fid > 0) {
      $this->entityTypeManager->getStorage('file')->resetCache([$fid]);
    }
    $mediaId = is_array($stored) ? (int) ($stored['media_id'] ?? 0) : 0;
    $this->entityTypeManager->getStorage('media')->resetCache($mediaId > 0 ? [$mediaId] : NULL);
    $form_state->set(self::STORAGE_KEY, NULL);
  }

}
