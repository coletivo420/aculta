<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_portal\Upload\UserUploadPolicy;
use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
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
use Drupal\media_gallery\MediaGalleryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a private Gallery album from images in the Forum editor. */
final class ForumGalleryUploadDialogForm extends FormBase {

  private const UPLOAD_DIRECTORY = 'private://forum/anexos';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly UserUploadPolicy $uploadPolicy,
    private readonly UserUploadDialogBuilder $dialogBuilder,
  ) {}

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_portal.user_upload_policy'),
      $container->get('aculta_portal.user_upload_dialog_builder'),
    );
  }

  /**
   * {@inheritdoc} */
  public function getFormId(): string {
    return 'aculta_forum_gallery_upload_dialog';
  }

  /**
   * {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormatInterface $filter_format = NULL): array {
    $this->dialogBuilder->wrap($form, 'aculta-forum-gallery-dialog', 'forum', 'forum-gallery-dialog');
    $form['introduction'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('As imagens ficam privadas. A galeria e as imagens só são publicadas com uma publicação pública do Fórum.'),
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da galeria'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $this->dialogBuilder->addFileControls($form, 'forum', self::UPLOAD_DIRECTORY, 'images', NULL, TRUE);
    $form['image_alts'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Descrição de cada imagem'),
      '#description' => $this->t('Informe uma descrição por linha, na mesma ordem das imagens enviadas. Todas são obrigatórias.'),
      '#rows' => 4,
      '#maxlength' => 16384,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Criar galeria e inserir'),
      '#button_type' => 'primary',
      '#ajax' => ['callback' => '::ajaxSubmit'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $fids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('images'))));
    if ($fids === []) {
      $form_state->setErrorByName('images', $this->t('Envie ao menos uma imagem.'));
      return;
    }
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
    if (count($files) !== count($fids)) {
      $form_state->setErrorByName('images', $this->t('Uma das imagens enviadas não está disponível. Envie-a novamente.'));
      return;
    }
    $alt_lines = preg_split('/\\R/u', trim((string) $form_state->getValue('image_alts'))) ?: [];
    if (count($alt_lines) !== count($fids)
      || array_filter($alt_lines, static fn (string $alt): bool => trim($alt) === '') !== []) {
      $form_state->setErrorByName('image_alts', $this->t('Informe uma descrição não vazia para cada imagem, uma por linha e na mesma ordem.'));
      return;
    }
    foreach ($files as $file) {
      if (!$file instanceof FileInterface || !$this->uploadPolicy->isValidImage($file, 'forum')) {
        $form_state->setErrorByName('images', $this->t('Uma das imagens não atende às extensões ou ao tamanho permitidos.'));
        return;
      }
    }
  }

  /**
   * {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Creates private Media and a Gallery, then inserts its Entity Embed. */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new HtmlCommand('#aculta-forum-gallery-dialog', $form));
      return $response;
    }

    $fids = array_values(array_filter(array_map('intval', (array) $form_state->getValue('images'))));
    $files = $this->entityTypeManager->getStorage('file')->loadMultiple($fids);
    $alt_lines = preg_split('/\\R/u', trim((string) $form_state->getValue('image_alts'))) ?: [];
    if (count($files) !== count($fids)) {
      $form_state->setErrorByName('images', $this->t('Uma das imagens enviadas não está mais disponível. Envie-a novamente.'));
      $response->addCommand(new HtmlCommand('#aculta-forum-gallery-dialog', $form));
      return $response;
    }
    foreach ($files as $file) {
      if (!$file instanceof FileInterface || !$this->uploadPolicy->isValidImage($file, 'forum')) {
        $form_state->setErrorByName('images', $this->t('Uma das imagens não atende às extensões ou ao tamanho permitidos.'));
        $response->addCommand(new HtmlCommand('#aculta-forum-gallery-dialog', $form));
        return $response;
      }
    }
    $media_ids = [];
    foreach ($fids as $position => $fid) {
      $file = $files[$fid] ?? NULL;
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => ForumAttachmentPublication::BUNDLE,
        'name' => $file->getFilename(),
        'uid' => $this->currentUser->id(),
        'status' => FALSE,
        'field_media_forum_attachment' => ['target_id' => $file->id()],
        'field_media_forum_image' => [
          'target_id' => $file->id(),
          'alt' => trim($alt_lines[$position]),
        ],
      ]);
      if (!$media instanceof MediaInterface) {
        throw new \UnexpectedValueException('The Forum attachment Media bundle could not be created.');
      }
      $media->save();
      $media_ids[] = ['target_id' => $media->id()];
      if ($file->isTemporary()) {
        $file->setPermanent();
        $file->save();
      }
    }

    $gallery = $this->entityTypeManager->getStorage('media_gallery')->create([
      'title' => $form_state->getValue('title'),
      'uid' => $this->currentUser->id(),
      'status' => FALSE,
      'field_gallery_purpose' => 'forum',
      'images' => $media_ids,
    ]);
    if (!$gallery instanceof MediaGalleryInterface) {
      throw new \UnexpectedValueException('The ACULTA Gallery entity could not be created.');
    }
    $gallery->save();

    $response->addCommand(new EditorDialogSave([
      'attributes' => [
        'data-entity-type' => 'media_gallery',
        'data-entity-uuid' => $gallery->uuid(),
        'data-entity-embed-display' => 'entity_reference:entity_reference_entity_view',
        'data-entity-embed-display-settings' => json_encode(['view_mode' => 'default'], JSON_THROW_ON_ERROR),
      ],
    ]));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
