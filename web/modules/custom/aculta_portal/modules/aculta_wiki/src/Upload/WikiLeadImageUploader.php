<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Upload;

use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Validates and stores the lead image of a Wiki entry as image Media.
 *
 * The upload controls live in the "Imagem de destaque" tab of the verbete
 * form. This service owns both halves so the same rules run on add and edit:
 * the policy validators from aculta_portal (consumer "wiki") decide what is
 * accepted, and a published image Media connects the file to
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
   * Public directory that receives the verbete images.
   */
  public const DIRECTORY = 'public://wiki/imagens';

  /**
   * Maximum length of the alt text, as in the Forum upload dialog.
   */
  public const ALT_MAX_LENGTH = 512;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
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

    $media = $this->storedMedia($form_state, $fid);
    if ($media === NULL) {
      // Media's file source does not register File API usage for this
      // reference; keep the upload out of temporary-file garbage collection.
      if ($file->isTemporary()) {
        $file->setPermanent();
        $file->save();
      }
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'image',
        'name' => $file->getFilename(),
        'uid' => $this->currentUser->id(),
        'status' => TRUE,
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

}
