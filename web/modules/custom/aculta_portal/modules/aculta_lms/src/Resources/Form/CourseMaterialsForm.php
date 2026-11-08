<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Resources\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\aculta_lms\Resources\Access\CourseDocumentAccessCheck;
use Drupal\Core\Cache\Cache;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edits existing course material references in the educator workspace.
 */
final class CourseMaterialsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Owner fields copied into this contextual Group course form.
   *
   * @var list<string>
   */
  private const CONTEXTUAL_FIELDS = [
    'field_lms_readings',
    'field_lms_galleries',
    'field_lms_videos',
    // Optional integration field owned by aculta_lms_h5p.
    'field_h5p_activities',
  ];

  public function __construct(
    private readonly CurrentRouteMatch $routeMatcher,
    private readonly CourseAuthoringAccessCheck $authoringAccess,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CourseDocumentAccessCheck $documentAccess,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_authoring_access'),
      $container->get('entity_type.manager'),
      $container->get('aculta_lms_resources.course_document_access'),
      $container->get('module_handler'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_lms_course_materials_form';
  }

  /**
   * Builds only the course's existing material reference widgets.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $course = $this->course();
    $this->assertAccess($course);

    $form['#attributes']['data-aculta-lms'] = 'course-materials-form';
    $form['intro'] = [
      '#plain_text' => $this->t('Vincule publicações, galerias, vídeos e atividades interativas existentes. O conteúdo e as permissões continuam sob responsabilidade dos módulos de origem.'),
    ];

    $display = $this->materialsFormDisplay();
    $available = FALSE;
    foreach (self::CONTEXTUAL_FIELDS as $field_name) {
      if (!$course->hasField($field_name) || !$display->getComponent($field_name)) {
        continue;
      }
      $available = TRUE;
    }
    if ($available) {
      $display->buildForm($course, $form, $form_state);
    }
    else {
      $form['unavailable'] = ['#plain_text' => $this->t('Os campos de materiais ainda não estão disponíveis neste curso.')];
    }

    $form['course_documents'] = $this->buildCourseDocuments($course);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar materiais'),
      '#button_type' => 'primary',
      '#disabled' => !$available,
    ];
    $form['actions']['cancel'] = Link::fromTextAndUrl(
      $this->t('Voltar à estrutura do curso'),
      Url::fromRoute('aculta_lms.course_structure', ['course' => $course->id()]),
    )->toRenderable();
    return $form;
  }

  /**
   * Saves only the existing material fields after a fresh contextual check.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $course = $this->course();
    $this->assertAccess($course);

    $display = $this->materialsFormDisplay();
    $display->extractFormValues($course, $form, $form_state);
    $course->save();
    $this->messenger()->addStatus($this->t('Os materiais do curso foram atualizados.'));
    $form_state->setRedirect('aculta_lms.course_structure', ['course' => $course->id()]);
  }

  /**
   * Creates a transient display containing only the owned resource widgets.
   */
  private function materialsFormDisplay(): EntityFormDisplay {
    $source = $this->entityTypeManager->getStorage('entity_form_display')->load('group.lms_course.default');
    if ($source instanceof EntityFormDisplay) {
      // Owner modules such as aculta_lms_h5p attach their widget at render
      // time; apply the same hook before copying components into this form.
      $displayContext = [
        'entity_type' => 'group',
        'bundle' => 'lms_course',
        'form_mode' => 'default',
      ];
      $this->moduleHandler->alter('entity_form_display', $source, $displayContext);
    }
    $display = EntityFormDisplay::create([
      'id' => 'group.lms_course.course_materials',
      'targetEntityType' => 'group',
      'bundle' => 'lms_course',
      'mode' => 'course_materials',
      'status' => TRUE,
    ]);
    if ($source instanceof EntityFormDisplay) {
      foreach (self::CONTEXTUAL_FIELDS as $field_name) {
        $component = $source->getComponent($field_name);
        if ($component !== NULL) {
          $display->setComponent($field_name, $component);
        }
      }
    }
    return $display;
  }

  /**
   * Returns the route's course Group or throws when it is malformed.
   */
  private function course(): GroupInterface {
    $course = $this->routeMatcher->getParameter('course');
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course') {
      throw new NotFoundHttpException();
    }
    return $course;
  }

  /**
   * Revalidates the LMS's contextual educator policy.
   */
  private function assertAccess(GroupInterface $course): void {
    if (!$this->authoringAccess->access($course)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Builds create/edit actions for the owner's private course-document Media.
   */
  private function buildCourseDocuments(GroupInterface $course): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'aculta-lms-course-materials--documents'],
      'title' => ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Documentos PDF privados do curso')],
      'description' => [
        '#plain_text' => $this->t('Estes arquivos são privados e usam a relação Group e o visualizador do módulo de documentos.'),
      ],
    ];
    $pluginId = $this->documentAccess->relationPluginId($course);
    if ($pluginId === NULL) {
      $build['unavailable'] = ['#plain_text' => $this->t('O visualizador de documentos privados não está disponível.')];
      return $build;
    }

    $createAccess = $this->documentAccess->createAccess($course);
    $build['#cache'] = [
      'contexts' => $createAccess->getCacheContexts(),
      'tags' => $createAccess->getCacheTags(),
      'max-age' => $createAccess->getCacheMaxAge(),
    ];
    if ($createAccess->isAllowed()) {
      $build['add'] = Link::fromTextAndUrl(
        $this->t('Adicionar documento PDF'),
        Url::fromRoute('aculta_lms.course_document_add', ['course' => $course->id()]),
      )->toRenderable();
      $build['add']['#access'] = $createAccess;
    }

    $items = [];
    foreach ($course->getRelatedEntities($pluginId) as $document) {
      if (!$document instanceof MediaInterface || $document->bundle() !== 'course_document') {
        continue;
      }
      $viewAccess = $document->access('view', $this->currentUser(), TRUE);
      if (!$viewAccess->isAllowed()) {
        continue;
      }
      $row = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'aculta-lms-course-materials--document'],
        'label' => ['#plain_text' => (string) $document->label()],
      ];
      $updateAccess = $this->documentAccess->updateAccess($course, $document);
      $row['#cache'] = [
        'contexts' => array_values(array_unique(array_merge(
          $updateAccess->getCacheContexts(),
          $viewAccess->getCacheContexts(),
        ))),
        'tags' => array_values(array_unique(array_merge($updateAccess->getCacheTags(), $viewAccess->getCacheTags()))),
        'max-age' => Cache::mergeMaxAges($updateAccess->getCacheMaxAge(), $viewAccess->getCacheMaxAge()),
      ];
      if ($updateAccess->isAllowed()) {
        $row['edit'] = Link::fromTextAndUrl(
          $this->t('Editar documento'),
          Url::fromRoute('aculta_lms.course_document_edit', [
            'course' => $course->id(),
            'media' => $document->id(),
          ]),
        )->toRenderable();
        $row['edit']['#access'] = $updateAccess;
      }
      $items[] = $row;
    }
    if ($items !== []) {
      $build['items'] = [
        '#theme' => 'item_list',
        '#list_type' => 'ul',
        '#items' => $items,
      ];
    }
    elseif (!$createAccess->isAllowed()) {
      $build['empty'] = ['#plain_text' => $this->t('Não há documentos disponíveis para edição neste curso.')];
    }
    return $build;
  }

}
