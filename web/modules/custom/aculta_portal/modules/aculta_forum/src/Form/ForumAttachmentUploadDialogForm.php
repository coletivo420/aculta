<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\filter\FilterFormatInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uploads one private file and inserts its unpublished Media into CKEditor 5.
 */
final class ForumAttachmentUploadDialogForm extends FormBase {

  private const UPLOAD_DIRECTORY = 'private://forum/anexos';

  private const EMBED_DISPLAY = 'entity_reference:entity_reference_entity_view';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly UserUploadPolicy $uploadPolicy,
    private readonly UserUploadDialogBuilder $dialogBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.user_upload_policy'),
      $container->get('aculta_portal.user_upload_dialog_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_forum_attachment_upload_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormatInterface $filter_format = NULL): array {
    $this->dialogBuilder->wrap($form, 'aculta-forum-attachment-dialog', 'forum', 'forum-attachment-dialog');
    $form['introduction'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('O arquivo ficará privado até ser referenciado por uma publicação ou comentário publicado num tópico público do Fórum.'),
    ];

    $this->dialogBuilder->addFileControls($form, 'forum', self::UPLOAD_DIRECTORY, 'image_file', 'document_file');
    $form['image_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Descrição da imagem'),
      '#description' => $this->t('Obrigatória ao enviar uma imagem. Descreva o que ela mostra; documentos não usam este campo.'),
      '#maxlength' => 512,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar e inserir'),
      '#button_type' => 'primary',
      '#ajax' => ['callback' => '::ajaxSubmit'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $image_fids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('image_file'))));
    $document_fids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('document_file'))));
    if (count($image_fids) + count($document_fids) !== 1) {
      $form_state->setErrorByName('image_file', $this->t('Envie um arquivo por vez.'));
      return;
    }

    $is_image_upload = $image_fids !== [];
    if ($is_image_upload && trim((string) $form_state->getValue('image_alt')) === '') {
      $form_state->setErrorByName('image_alt', $this->t('Informe uma descrição para a imagem.'));
      return;
    }
    $fid = $is_image_upload ? reset($image_fids) : reset($document_fids);
    $file = $this->entityTypeManager->getStorage('file')->load($fid);
    if (!$file instanceof FileInterface) {
      $form_state->setErrorByName($is_image_upload ? 'image_file' : 'document_file', $this->t('O arquivo enviado não está disponível. Envie-o novamente.'));
      return;
    }

    $is_valid = $is_image_upload
      ? $this->uploadPolicy->isValidImage($file, 'forum')
      : $this->uploadPolicy->isValidDocument($file, 'forum');
    if (!$is_valid) {
      $form_state->setErrorByName($is_image_upload ? 'image_file' : 'document_file', $this->t('O arquivo não atende às extensões ou ao tamanho permitidos.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Creates unpublished Media and inserts the Entity Embed in the editor.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new HtmlCommand('#aculta-forum-attachment-dialog', $form));
      return $response;
    }

    $fids = array_values(array_filter(array_map('intval', array_merge(
      (array) $form_state->getValue('image_file'),
      (array) $form_state->getValue('document_file'),
    ))));
    $file = $this->entityTypeManager->getStorage('file')->load((int) reset($fids));
    if (!$file instanceof FileInterface) {
      $form_state->setErrorByName('image_file', $this->t('O arquivo enviado não está disponível. Envie-o novamente.'));
      $response->addCommand(new HtmlCommand('#aculta-forum-attachment-dialog', $form));
      return $response;
    }

    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => ForumAttachmentPublication::BUNDLE,
      'name' => $file->getFilename(),
      'uid' => $this->currentUser->id(),
      'status' => FALSE,
      'field_media_forum_attachment' => ['target_id' => $file->id()],
    ]);
    if (!$media instanceof MediaInterface) {
      throw new \UnexpectedValueException('The Forum attachment Media bundle could not be created.');
    }
    if (str_starts_with((string) $file->getMimeType(), 'image/')) {
      $media->set('field_media_forum_image', [
        'target_id' => $file->id(),
        'alt' => trim((string) $form_state->getValue('image_alt')),
      ]);
    }
    $media->save();
    // Media's file source does not register File API usage for this reference.
    // Keep the managed upload beyond Core's temporary-file garbage collection;
    // ForumAttachmentCleanup removes it with its orphan Media after one hour.
    if ($file->isTemporary()) {
      $file->setPermanent();
      $file->save();
    }

    $attributes = [
      'data-entity-type' => 'media',
      'data-entity-uuid' => $media->uuid(),
      'data-entity-embed-display' => self::EMBED_DISPLAY,
      'data-entity-embed-display-settings' => json_encode(['view_mode' => 'default'], JSON_THROW_ON_ERROR),
    ];
    $response->addCommand(new EditorDialogSave(['attributes' => $attributes]));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
