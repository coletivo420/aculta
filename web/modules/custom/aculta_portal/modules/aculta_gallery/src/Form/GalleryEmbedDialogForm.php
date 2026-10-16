<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Form;

use Drupal\aculta_image\UploadPolicy;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Field\WidgetPluginManager;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\editor\Ajax\EditorDialogSave;
use Drupal\filter\FilterFormatInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Diálogo "Criar galeria" do CKEditor 5.
 *
 * Reaproveita o widget de imagens do display de formulário da galeria (`media_gallery.media_gallery.default`),
 * a mesma escolha de mídia do formulário editorial. Cria a entidade media_gallery publicada e devolve a
 * incorporação para o Entity Embed inserir no texto.
 */
final class GalleryEmbedDialogForm extends FormBase {

  /** Pasta das fotos enviadas pelo diálogo. */
  private const UPLOAD_DIRECTORY = 'public://galerias/uploads';

  /** Modo de exibição padrão da incorporação (Entity Embed). */
  private const EMBED_DISPLAY = 'entity_reference:entity_reference_entity_view';

  /** Serviços guardados como propriedades comuns: o Drupal restaura o objeto entre as requisições AJAX. */
  protected EntityTypeManagerInterface $entityTypeManager;
  protected WidgetPluginManager $widgetManager;
  protected AccountProxyInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    WidgetPluginManager $widget_manager,
    AccountProxyInterface $current_user,
    private readonly UploadPolicy $uploadPolicy,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->widgetManager = $widget_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.widget'),
      $container->get('current_user'),
      $container->get('aculta_image.upload_policy'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_gallery_embed_dialog';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FilterFormatInterface $filter_format = NULL): array {
    $form['#parents'] = [];
    $form['#prefix'] = '<div id="aculta-gallery-dialog-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'editor/drupal.editor.dialog';

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Título da galeria'),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];

    $gallery = $this->entityTypeManager->getStorage('media_gallery')->create([]);
    $widget = $this->imagesWidget($gallery);
    $form['images'] = $widget->form($gallery->get('images'), $form, $form_state);
    $form['images']['#required'] = TRUE;
    $form['images']['#description'] = $this->t('Escreva o nome ou o ID de cada imagem já existente na biblioteca de mídia.');

    // Upload direto (elemento de arquivo do Core, sem modal): as fotos novas viram mídias ao criar a galeria.
    $form['new_images'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Enviar fotos novas'),
      '#description' => $this->t('Formatos: png, gif, jpg, jpeg ou webp. Cada arquivo com até 10 MB.'),
      '#multiple' => TRUE,
      '#upload_location' => self::UPLOAD_DIRECTORY,
      '#upload_validators' => [
        ...$this->uploadPolicy->validators(),
        'FileIsImage' => [],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Criar e inserir'),
      '#button_type' => 'primary',
      '#ajax' => ['callback' => '::ajaxSubmit'],
    ];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * Transforma os arquivos enviados no diálogo em mídias de imagem (uma por foto).
   *
   * @return int[]
   *   IDs das mídias criadas, na ordem do envio.
   */
  private function createMediaFromUploads(FormStateInterface $form_state, string $title): array {
    $fids = array_filter(array_map('intval', (array) $form_state->getValue('new_images')));
    $media_ids = [];
    $position = 0;
    foreach ($fids as $fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if ($file === NULL) {
        continue;
      }
      $file->setPermanent();
      $file->save();
      $position++;
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => 'image',
        'name' => $file->getFilename(),
        'uid' => $this->currentUser->id(),
        'status' => 1,
        'field_media_image' => [
          'target_id' => $file->id(),
          'alt' => $title !== '' ? $title . ', foto ' . $position : 'Foto ' . $position,
        ],
      ]);
      $media->save();
      $media_ids[] = (int) $media->id();
    }
    return $media_ids;
  }

  /**
   * Widget do campo de imagens: autocompletar por entidade do Core (múltiplo).
   *
   * Não usa a Media Library: ela abre um modal dentro do diálogo, e o Drupal tem um único elemento de modal
   * (#drupal-modal). A Media Library substituiria o diálogo da galeria. Ver docs/ARQUITETURA.md §4.3.
   */
  private function imagesWidget(EntityInterface $gallery): WidgetInterface {
    return $this->widgetManager->getInstance([
      'field_definition' => $gallery->getFieldDefinition('images'),
      'form_mode' => 'default',
      'configuration' => [
        'type' => 'entity_reference_autocomplete',
        'settings' => ['match_operator' => 'CONTAINS', 'match_limit' => 10, 'size' => 60, 'placeholder' => ''],
        'third_party_settings' => [],
        'weight' => 3,
        'region' => 'content',
      ],
    ]);
  }

  /**
   * Cria a galeria e devolve a incorporação para o editor.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new HtmlCommand('#aculta-gallery-dialog-form', $form));
      return $response;
    }

    $storage = $this->entityTypeManager->getStorage('media_gallery');
    $gallery = $storage->create([
      'title' => $form_state->getValue('title'),
      'status' => 1,
      'uid' => $this->currentUser->id(),
    ]);
    $this->imagesWidget($gallery)->extractFormValues($gallery->get('images'), $form, $form_state);
    foreach ($this->createMediaFromUploads($form_state, (string) $form_state->getValue('title')) as $media_id) {
      $gallery->get('images')->appendItem(['target_id' => $media_id]);
    }
    if ($gallery->get('images')->isEmpty()) {
      $form_state->setErrorByName('images', $this->t('Escolha ou envie ao menos uma imagem para a galeria.'));
      $response->addCommand(new HtmlCommand('#aculta-gallery-dialog-form', $form));
      return $response;
    }
    $gallery->save();

    $attributes = [
      'data-entity-type' => 'media_gallery',
      'data-entity-uuid' => $gallery->uuid(),
      'data-entity-embed-display' => self::EMBED_DISPLAY,
      'data-entity-embed-display-settings' => json_encode(['view_mode' => 'default']),
    ];
    $response->addCommand(new EditorDialogSave(['attributes' => $attributes]));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

}
