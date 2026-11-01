<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\aculta_lms\Access\ClassDashboardAccessCheck;
use Drupal\group\Entity\GroupInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Lets course educators control student visibility of one class roster.
 */
final class ClassRosterVisibilityForm extends FormBase {

  public function __construct(
    private readonly CurrentRouteMatch $currentRouteMatch,
    private readonly ClassDashboardAccessCheck $classAccess,
  ) {}

  /**
   * Creates the form with route and contextual access services.
   */
  public static function create(ContainerInterface $container): static {
    $form = new static(
      $container->get('current_route_match'),
      $container->get('aculta_lms.class_dashboard_access'),
    );
    $form->setStringTranslation($container->get('string_translation'));
    $form->setMessenger($container->get('messenger'));
    return $form;
  }

  /**
   * Returns the stable form ID.
   */
  public function getFormId(): string {
    return 'aculta_lms_class_roster_visibility';
  }

  /**
   * Builds the per-class setting form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    [$course, $class] = $this->routeEntities();
    $this->assertAccess($course, $class);

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Turma'),
      '#plain_text' => $class->label(),
    ];
    $form['visible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permitir que estudantes vejam a lista de participantes'),
      '#description' => $this->t('Quando ativada, cada estudante matriculado nesta turma verá somente os nomes dos colegas da mesma turma na página do curso. A lista fica oculta por padrão e não mostra progresso, notas ou respostas.'),
      '#default_value' => $class->hasField('field_roster_visible')
        ? (int) $class->get('field_roster_visible')->value
        : 0,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar visibilidade'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Voltar ao painel da turma'),
      '#url' => Url::fromRoute('aculta_lms.class_dashboard', [
        'course' => $course->id(),
        'group' => $class->id(),
      ]),
    ];
    return $form;
  }

  /**
   * Saves the visibility preference after rechecking contextual access.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$course, $class] = $this->routeEntities();
    $this->assertAccess($course, $class);
    if (!$class->hasField('field_roster_visible')) {
      throw new \LogicException('The class roster visibility field is unavailable.');
    }
    $class->set('field_roster_visible', (bool) $form_state->getValue('visible'));
    $class->save();
    $this->messenger()->addStatus($this->t('A visibilidade da lista de participantes foi atualizada.'));
    $form_state->setRedirect('aculta_lms.class_dashboard', [
      'course' => $course->id(),
      'group' => $class->id(),
    ]);
  }

  /**
   * Gets and type-checks the route's course and class.
   *
   * @return array{0: \Drupal\lms\Entity\Bundle\CourseInterface, 1: \Drupal\group\Entity\GroupInterface}
   *   The current course and its class.
   */
  private function routeEntities(): array {
    $course = $this->currentRouteMatch->getParameter('course');
    $class = $this->currentRouteMatch->getParameter('group');
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
      || !$class instanceof GroupInterface || $class->bundle() !== 'lms_class') {
      throw new \LogicException('The class roster visibility route requires its course and class.');
    }
    return [$course, $class];
  }

  /**
   * Denies stale, mismatched, or no longer authorized form submissions.
   */
  private function assertAccess(CourseInterface $course, GroupInterface $class): void {
    if (!$this->classAccess->access($course, $class)->isAllowed()) {
      throw new AccessDeniedHttpException();
    }
  }

}
