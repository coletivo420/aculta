<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_resources\Form;

use Drupal\aculta_lms\Access\CourseAuthoringAccessCheck;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Display\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Edits existing course material references in the educator workspace.
 */
final class CourseMaterialsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Material fields owned by this module on the Group course entity.
   *
   * @var list<string>
   */
  private const MATERIAL_FIELDS = [
    'field_lms_readings',
    'field_lms_galleries',
    'field_lms_videos',
  ];

  public function __construct(
    private readonly CurrentRouteMatch $routeMatcher,
    private readonly CourseAuthoringAccessCheck $authoringAccess,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('current_route_match'),
      $container->get('aculta_lms.course_authoring_access'),
      $container->get('entity_type.manager'),
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
      '#plain_text' => $this->t('Vincule publicações, galerias e vídeos já existentes. O conteúdo e as permissões continuam sob responsabilidade dos módulos de origem.'),
    ];

    $display = $this->materialsFormDisplay();
    $available = FALSE;
    foreach (self::MATERIAL_FIELDS as $field_name) {
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
    $display = EntityFormDisplay::create([
      'id' => 'group.lms_course.course_materials',
      'targetEntityType' => 'group',
      'bundle' => 'lms_course',
      'mode' => 'course_materials',
      'status' => TRUE,
    ]);
    if ($source instanceof EntityFormDisplay) {
      foreach (self::MATERIAL_FIELDS as $field_name) {
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

}
