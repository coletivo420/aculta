<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Form;

use Drupal\aculta_forum\Attachment\ForumAttachmentPublication;
use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\RecentUploads;
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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uploads one private file and inserts its unpublished Media into CKEditor 5.
 */
final class ForumAttachmentUploadDialogForm extends FormBase {

  private const UPLOAD_DIRECTORY = 'private://forum/anexos';

  private const EMBED_DISPLAY = 'entity_reference:entity_reference_entity_view';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly ConsumerUploadPolicy $uploadPolicy,
    protected readonly UserUploadDialogBuilder $dialogBuilder,
    protected readonly RecentUploads $recentUploads,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_file_uploader.consumer_policy'),
      $container->get('aculta_portal.user_upload_dialog_builder'),
      $container->get('aculta_file_uploader.recent_uploads'),
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
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   * @param ?FilterFormatInterface $filter_format
   *   Value used for $filter_format.
   *
   * @return array<string, mixed>
   *   Render array returned by the method.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormatInterface $filter_format = NULL): array {
    $this->dialogBuilder->wrap($form, 'aculta-forum-attachment-dialog', 'forum', 'forum-attachment-dialog');
    $form['introduction'] = [
      '#type' => 'item',
      '#plain_text' => $this->t('O arquivo ficará privado até ser referenciado por uma publicação ou comentário publicado num tópico público do Fórum.'),
    ];

    $this->dialogBuilder->addFileControls($form, 'forum', self::UPLOAD_DIRECTORY, 'image_file', 'document_file');
    // Envios recentes do Studio: o próprio usuário vê os seus; administrador vê todos.
    $studio_options = $this->recentUploads->forUser((int) $this->currentUser->id(), 'forum', 10, $this->currentUser->hasPermission('administer site configuration'));
    if ($studio_options !== []) {
      $form['studio_file'] = [
        '#type' => 'select',
        '#title' => $this->t('Ou escolha um envio recente do Studio'),
        '#options' => ['' => $this->t('- Nenhum -')] + $studio_options,
        '#default_value' => '',
        '#description' => $this->t('Só vale se nenhum arquivo novo for enviado acima.'),
      ];
    }
    if (isset($form['image_file'])) {
      $form['image_alt'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Descrição da imagem'),
        '#description' => $this->t('Obrigatória ao enviar uma imagem. Descreva o que ela mostra; documentos não usam este campo.'),
        '#maxlength' => 512,
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar e inserir'),
      '#button_type' => 'primary',
      '#ajax' => ['callback' => '::ajaxSubmit'],
    ];
    /** @var array<string, mixed> $form */
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $selection = $this->resolveSelection($form_state);
    if ($selection['error'] !== NULL) {
      $form_state->setErrorByName($selection['field'], $selection['error']);
      return;
    }
    if ($selection['file'] === NULL) {
      return;
    }
    if ($selection['is_image']) {
      $image_alt_val = $form_state->getValue('image_alt');
      $image_alt = is_scalar($image_alt_val) ? trim((string) $image_alt_val) : '';
      if ($image_alt === '' || mb_strlen($image_alt) > 512) {
        $form_state->setErrorByName('image_alt', $this->t('Informe uma descrição não vazia de até 512 caracteres para a imagem.'));
        return;
      }
    }
    $is_valid = $selection['is_image']
      ? $this->uploadPolicy->isValidImage($selection['file'], 'forum')
      : $this->uploadPolicy->isValidDocument($selection['file'], 'forum');
    if (!$is_valid) {
      $form_state->setErrorByName($selection['field'], $this->t('O arquivo não atende às extensões ou ao tamanho permitidos.'));
    }
  }

  /**
   * Resolve o arquivo escolhido: upload novo, ou envio recente do Studio do dono.
   *
   * @return array{file: ?FileInterface, is_image: bool, field: string, error: ?\Drupal\Core\StringTranslation\TranslatableMarkup}
   *   Dados do arquivo resolvido e eventual mensagem de erro.
   */
  private function resolveSelection(FormStateInterface $form_state): array {
    $raw_image = $form_state->getValue('image_file');
    $image_fids = [];
    if (is_array($raw_image)) {
      foreach ($raw_image as $item) {
        $val = filter_var($item, FILTER_VALIDATE_INT);
        if ($val !== FALSE && $val > 0) {
          $image_fids[] = (int) $val;
        }
      }
    }
    $raw_doc = $form_state->getValue('document_file');
    $document_fids = [];
    if (is_array($raw_doc)) {
      foreach ($raw_doc as $item) {
        $val = filter_var($item, FILTER_VALIDATE_INT);
        if ($val !== FALSE && $val > 0) {
          $document_fids[] = (int) $val;
        }
      }
    }
    $studio_val = filter_var($form_state->getValue('studio_file'), FILTER_VALIDATE_INT);
    $studio_fid = ($studio_val !== FALSE && $studio_val > 0) ? (int) $studio_val : 0;
    $uploaded = count($image_fids) + count($document_fids);

    if ($uploaded > 1 || ($uploaded === 1 && $studio_fid > 0)) {
      return [
        'file' => NULL,
        'is_image' => FALSE,
        'field' => 'image_file',
        'error' => $this->t('Envie um arquivo por vez.'),
      ];
    }
    if ($uploaded === 0 && $studio_fid === 0) {
      return [
        'file' => NULL,
        'is_image' => FALSE,
        'field' => 'image_file',
        'error' => $this->t('Envie um arquivo ou escolha um envio recente.'),
      ];
    }

    if ($uploaded === 1) {
      $is_image = $image_fids !== [];
      $fid = $is_image ? reset($image_fids) : reset($document_fids);
      $field = $is_image ? 'image_file' : 'document_file';
      $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
      if (!$file instanceof FileInterface) {
        return [
          'file' => NULL,
          'is_image' => $is_image,
          'field' => $field,
          'error' => $this->t('O arquivo enviado não está disponível. Envie-o novamente.'),
        ];
      }
      return ['file' => $file, 'is_image' => $is_image, 'field' => $field, 'error' => NULL];
    }

    // Envio recente do Studio: só do próprio usuário, ou de todos se administrador.
    $file = $this->entityTypeManager->getStorage('file')->load($studio_fid);
    $filename = $file instanceof FileInterface ? (string) $file->getFilename() : '';
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $is_image = $file instanceof FileInterface && in_array($ext, $this->uploadPolicy->imageExtensions('forum'), TRUE);
    if (!$file instanceof FileInterface || !$this->recentUploads->isOwnedBy($file, (int) $this->currentUser->id(), 'forum', $this->currentUser->hasPermission('administer site configuration'))) {
      return [
        'file' => NULL,
        'is_image' => FALSE,
        'field' => 'studio_file',
        'error' => $this->t('O arquivo escolhido não está disponível. Envie-o novamente.'),
      ];
    }
    return ['file' => $file, 'is_image' => $is_image, 'field' => 'studio_file', 'error' => NULL];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return void
   *   Value returned by the method.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Creates unpublished Media and inserts the Entity Embed in the editor.
   *
   * @param array<array-key, mixed> $form
   *   Value used for $form.
   * @param FormStateInterface $form_state
   *   Value used for $form_state.
   *
   * @return AjaxResponse
   *   Value returned by the method.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new HtmlCommand('#aculta-forum-attachment-dialog', $form));
      return $response;
    }

    $selection = $this->resolveSelection($form_state);
    if ($selection['error'] !== NULL || $selection['file'] === NULL) {
      $form_state->setErrorByName($selection['field'], $selection['error'] ?? $this->t('O arquivo enviado não está disponível. Envie-o novamente.'));
      $response->addCommand(new HtmlCommand('#aculta-forum-attachment-dialog', $form));
      return $response;
    }
    $file = $selection['file'];
    $is_image_upload = $selection['is_image'];
    $field_name = $selection['field'];

    $is_valid = $is_image_upload
      ? $this->uploadPolicy->isValidImage($file, 'forum')
      : $this->uploadPolicy->isValidDocument($file, 'forum');
    if (!$is_valid) {
      $form_state->setErrorByName($field_name, $this->t('O arquivo não atende às extensões ou ao tamanho permitidos.'));
      $response->addCommand(new HtmlCommand('#aculta-forum-attachment-dialog', $form));
      return $response;
    }

    $image_alt_val = $form_state->getValue('image_alt');
    $image_alt = is_scalar($image_alt_val) ? trim((string) $image_alt_val) : '';
    if ($is_image_upload && ($image_alt === '' || mb_strlen($image_alt) > 512)) {
      $form_state->setErrorByName('image_alt', $this->t('Informe uma descrição não vazia de até 512 caracteres para a imagem.'));
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
    if ($is_image_upload) {
      $media->set('field_media_forum_image', [
        'target_id' => $file->id(),
        'alt' => $image_alt,
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
