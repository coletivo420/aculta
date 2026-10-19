<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Plugin\Field\FieldWidget;

use Drupal\aculta_lms_h5p\Access\H5PCourseAccessManager;
use Drupal\aculta_lms_h5p\Access\H5PLibraryAccessManager;
use Drupal\aculta_lms_h5p\Plugin\Field\H5PWidgetBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\Entity\H5PContent;
use Drupal\aculta_lms_h5p\Plugin\Field\FieldType\H5PItem;
use Drupal\aculta_lms_h5p\H5PEditor\H5PEditorUtilities;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Plugin implementation of the H5P editor widget. */
#[FieldWidget(
  id: 'h5p_editor',
  label: new TranslatableMarkup('Editor H5P'),
  field_types: ['h5p'],
)]
class H5PEditorWidget extends H5PWidgetBase implements ContainerFactoryPluginInterface {

  protected static $counter = 0;

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    // Keep injected services protected and mutable: WidgetBase inherits
    // PluginBase's DependencySerializationTrait, which serializes protected
    // dependencies by service ID for Form API's cached build. Private readonly
    // properties are omitted by the inherited trait and become uninitialized
    // when Drupal rebuilds a cached Group form.
    protected H5PCourseAccessManager $courseAccess,
    protected H5PLibraryAccessManager $libraryAccess,
    protected AccountProxyInterface $currentUser,
    protected H5PEditorUtilities $editorUtilities,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'] ?? [],
      $container->get('aculta_lms_h5p.course_access'),
      $container->get('aculta_lms_h5p.library_access'),
      $container->get('current_user'),
      $container->get('aculta_lms_h5p.editor_utilities'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $parentElement = parent::formElement($items, $delta, $element, $form, $form_state);
    $element = &$parentElement['h5p_content'];
    if (empty($element['id'])) {
      return $parentElement; // No content id, use parent element
    }

    $course = $items->getEntity();
    $h5p_content_id = (int) ($items[$delta]->h5p_content_id ?? 0);
    if (!$course instanceof CourseInterface || !$course->id()
      || !$this->courseAccess->editorAccess((int) $course->id(), $h5p_content_id, $this->currentUser)->isAllowed()) {
      return [
        'h5p_content' => [
          '#type' => 'item',
          '#title' => $this->t('Atividades interativas'),
          '#markup' => '<p>' . $this->t('A edição de atividades fica disponível após salvar o curso e confirmar o acesso de educador.') . '</p>',
        ],
      ];
    }

    $field_name = $items->getName();

    if ($h5p_content_id) {
      // Load H5P Content entity
      $h5p_content = H5PContent::load($h5p_content_id);

      $parentElement['h5p_content_id'] = [
        '#type' => 'hidden',
        '#default_value' => $h5p_content_id,
      ];
    }

    $element['parameters'] = [
      '#type' => 'hidden',
      '#default_value' => empty($h5p_content) ? '' : $h5p_content->getEditorJSON(),
      '#attributes' => [
        'id' => "h5p-content-parameters-" . self::$counter,
      ],
      '#element_validate' => [[$this, 'validateParameters']],
    ];

    $element['library'] = [
      '#type' => 'hidden',
      '#default_value' => empty($h5p_content) ? '' : $h5p_content->getLibraryString(),
      '#attributes' => [
        'id' => "h5p-content-library-" . self::$counter,
      ],
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];
    self::$counter++;

    // Add editor element
    $element['editor'] = [
      '#type' => 'item',
      '#title' => $this->t('Tipo de atividade'),
      '#markup' => '<div class="h5p-editor" data-parametersid="' . $element['parameters']['#attributes']['id'] . '" data-libraryid="' . $element['library']['#attributes']['id'] . '"' . (empty($h5p_content) ? '' : ' data-content-id="' . $h5p_content_id . '"') . '>' . $this->t('Aguardando o JavaScript...') . '</div>',
      '#attached' => [
        'drupalSettings' => [
          'h5p' => [
              'H5PIntegration' => H5PDrupal::getGenericH5PIntegrationSettings($course)
          ],
              'h5peditor' => $this->editorUtilities->getEditorSettings($course),
        ],
        'library' => [
          'aculta_lms_h5p/h5p.editor',
        ],
      ],
    ];

    return $parentElement;
  }

  /**
   * Validate the h5p file upload
   */
  public function validate($element, FormStateInterface $form_state) {
    $value = (string) ($element['#value'] ?? '');
    // An unused multiple-value row and a deliberately cleared editor both
    // submit an empty library. The field item's massage step handles those
    // values; they are not malformed library names.
    if ($value === '') {
      return;
    }
    $library = \H5PCore::libraryFromString($value);
    if (!is_array($library) || !$this->courseHasEditorAccess($form_state, $this->contentIdForElement($form_state, $element))) {
      $form_state->setError($element, $this->t('Não foi possível validar o tipo de atividade para este curso.'));
      return;
    }

    if (!$this->libraryAccess->canUse(
      (string) $library['machineName'],
      (int) $library['majorVersion'],
      (int) $library['minorVersion'],
      $this->currentUser,
    )) {
      $form_state->setError($element, $this->t('Este tipo de atividade não está disponível para sua conta.'));
      return;
    }

    // Prevent saving an outdated content type after its replacement is installed.
    $core = H5PDrupal::getInstance('core');
    if ($core->h5pF->libraryHasUpgrade($library)) {
      // We do not allow storing old content due to security concerns
      $form_state->setError($element, $this->t('Não foi possível salvar: atualize o tipo de atividade antes.'));
    }
  }

  /** Validates submitted editor data before it reaches the H5P storage layer. */
  public function validateParameters(array &$element, FormStateInterface $form_state): void {
    $value = (string) ($element['#value'] ?? '');
    $valueParents = $element['#parents'];
    array_pop($valueParents);
    $submitted = $form_state->getValue($valueParents, []);
    if ($value === '' && empty($submitted['library'])) {
      // Field API renders an additional empty row for unlimited fields. Allow
      // it to remain blank; if a library was selected, parameters are required.
      return;
    }
    if ($value === '' || strlen($value) > 2_000_000) {
      $form_state->setError($element, $this->t('O conteúdo da atividade está vazio ou excede o limite permitido.'));
      return;
    }
    $parameters = json_decode($value);
    if (!$parameters instanceof \stdClass
      || !(($parameters->params ?? NULL) instanceof \stdClass)
      || !(($parameters->metadata ?? NULL) instanceof \stdClass)
      || !$this->courseHasEditorAccess($form_state, $this->contentIdForElement($form_state, $element))) {
      $form_state->setError($element, $this->t('Os dados da atividade ou o acesso ao curso são inválidos.'));
      return;
    }

    $library = \H5PCore::libraryFromString((string) ($submitted['library'] ?? ''));
    if (!is_array($library) || !$this->libraryAccess->canUse(
      (string) $library['machineName'],
      (int) $library['majorVersion'],
      (int) $library['minorVersion'],
      $this->currentUser,
    )) {
      $form_state->setError($element, $this->t('Este tipo de atividade não está disponível para sua conta.'));
      return;
    }

    $libraryName = (string) ($submitted['library'] ?? '');
    $libraryParameters = (object) [
      'library' => $libraryName,
      'params' => $parameters->params,
      'metadata' => $parameters->metadata,
    ];
    $validator = H5PDrupal::getInstance('contentvalidator');
    $validator->validateLibrary($libraryParameters, (object) ['options' => [$libraryName]]);
    if (!($libraryParameters instanceof \stdClass)) {
      $form_state->setError($element, $this->t('Os parâmetros não correspondem ao tipo de atividade selecionado.'));
      return;
    }
    $parameters->params = $libraryParameters->params;
    $parameters->metadata = $libraryParameters->metadata;
    $form_state->setValueForElement($element, json_encode($parameters, JSON_THROW_ON_ERROR));
  }

  /** Returns whether the submitted Group and content ID are editable here. */
  private function courseHasEditorAccess(FormStateInterface $form_state, int $contentId): bool {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return FALSE;
    }
    $course = $formObject->getEntity();
    return $course instanceof CourseInterface
      && $course->id() !== NULL
      && $this->courseAccess->editorAccess((int) $course->id(), $contentId, $this->currentUser)->isAllowed();
  }

  /** Reads this delta's original H5P ID from the entity being edited. */
  private function contentIdForElement(FormStateInterface $form_state, array $element): int {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return 0;
    }
    $entity = $formObject->getEntity();
    $fieldName = $this->fieldDefinition->getName();
    if (!$entity instanceof CourseInterface || !$entity->hasField($fieldName)) {
      return 0;
    }
    $parents = $element['#parents'] ?? [];
    $fieldIndex = array_search($fieldName, $parents, TRUE);
    if ($fieldIndex === FALSE || !isset($parents[$fieldIndex + 1]) || !is_numeric($parents[$fieldIndex + 1])) {
      return 0;
    }
    $item = $entity->get($fieldName)->get((int) $parents[$fieldIndex + 1]);
    if ($item && $item->h5p_content_id) {
      return (int) $item->h5p_content_id;
    }
    return 0;
  }

  /**
   * Help message out each value from the submitted form
   *
   * @param array $value
   * @param integer $delta
   * @param boolean $do_new_revision
   */
  protected function massageFormValue(array $value, $delta, $do_new_revision) {
    // Prepare default messaged return values
    $return_value = [
      'h5p_content_revisioning_handled' => TRUE,
      'h5p_content_id' => $value['id'],
      'h5p_content_new_translation' => $value['new_translation'],
    ];

    // Skip saving content if no library is selector, or clearing content
    if (!$value['library'] || $value['clear_content']) {
      $return_value['h5p_content_id'] = NULL;

      if ($value['id'] && !$do_new_revision && !$value['new_translation']) {
        // Not a new revision or translation, delete existing content
        H5PItem::deleteH5PContent($value['id']);
      }

      return $return_value;
    }

    // Load existing content
    $h5p_content = $value['id'] ? H5PContent::load($value['id']) : NULL;
    $old_library = empty($h5p_content) ? NULL : $h5p_content->getLibrary(TRUE);
    $old_params = empty($h5p_content) ? NULL : $h5p_content->getParameters();
    if (empty($h5p_content)) {
      $value['id'] = NULL; // Invalid, content has been deleted
    }

    $params = json_decode($value['parameters']);

    // Prepare content values
    $core = H5PDrupal::getInstance('core');
    $content = [
      'library' => $this->editorUtilities->getLibraryProperty($value['library']),
      'params' => json_encode($params->params),
      'metadata' => $params->metadata,
      'disable' => $core->getStorableDisplayOptions($value, !empty($h5p_content) ? $h5p_content->get('disabled_features')->value : 0),
    ];
    if ($value['id'] && !$do_new_revision && !$value['new_translation']) {
      $content['id'] = $value['id'];
    }

    // Save the new content
    $return_value['h5p_content_id'] = $core->saveContent($content);

    // If we had existing content and did a new revision we need to make a copy
    // of the content folder from the old revision
    if ($value['id'] && ($do_new_revision || $value['new_translation'])) {
      $core->fs->cloneContent($value['id'], $return_value['h5p_content_id']);
    }

    // Keep new files, delete files from old parameters
    $editor = $this->editorUtilities->getEditor();
    $editor->processParameters(
      $return_value['h5p_content_id'],
      $content['library'],
      $params->params,
      $old_library,
      $old_params
    );

    return $return_value;
  }

}
