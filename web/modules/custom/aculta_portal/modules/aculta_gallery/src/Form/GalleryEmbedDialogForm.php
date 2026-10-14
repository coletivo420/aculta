<?php

declare(strict_types=1);

namespace Drupal\aculta_gallery\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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

  /** Display de formulário da galeria: fonte de verdade do widget de imagens. */
  private const FORM_DISPLAY = 'media_gallery.media_gallery.default';

  /** Modo de exibição padrão da incorporação (Entity Embed). */
  private const EMBED_DISPLAY = 'entity_reference:entity_reference_entity_view';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WidgetPluginManager $widgetManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.widget'),
      $container->get('current_user'),
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
    $component = EntityFormDisplay::load(self::FORM_DISPLAY)?->getComponent('images');
    if ($component !== NULL) {
      $widget = $this->widgetManager->getInstance([
        'field_definition' => $gallery->getFieldDefinition('images'),
        'form_mode' => 'default',
        'configuration' => $component,
      ]);
      $form['images'] = $widget->form($gallery->get('images'), $form, $form_state);
      $form['images']['#required'] = TRUE;
    }

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
    $widget = $this->widgetManager->getInstance([
      'field_definition' => $gallery->getFieldDefinition('images'),
      'form_mode' => 'default',
      'configuration' => EntityFormDisplay::load(self::FORM_DISPLAY)->getComponent('images'),
    ]);
    $widget->extractFormValues($gallery->get('images'), $form, $form_state);
    if ($gallery->get('images')->isEmpty()) {
      $form_state->setErrorByName('images', $this->t('Escolha ao menos uma imagem para a galeria.'));
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
