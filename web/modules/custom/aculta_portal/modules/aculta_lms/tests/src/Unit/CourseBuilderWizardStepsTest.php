<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Wizard\Step\CourseDetailsStepForm;
use Drupal\aculta_lms\Wizard\Step\CourseReviewStepForm;
use Drupal\aculta_lms\Wizard\Step\CourseStructureStepForm;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Testes unitarios das etapas do CourseBuilderWizard.
 */
#[CoversClass(CourseDetailsStepForm::class)]
#[CoversClass(CourseStructureStepForm::class)]
#[CoversClass(CourseReviewStepForm::class)]
final class CourseBuilderWizardStepsTest extends UnitTestCase {

  /**
   * Testa a etapa de detalhes do curso.
   */
  public function testCourseDetailsStep(): void {
    $step = new CourseDetailsStepForm();
    $step->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame('aculta_lms_course_builder_wizard_details', $step->getFormId());

    $formState = new FormState();
    $formState->setTemporaryValue('wizard', ['title' => 'Curso Teste', 'description' => 'Descricao']);

    $form = $step->buildForm([], $formState);
    $this->assertIsArray($form['title'] ?? NULL);
    $this->assertSame('Curso Teste', $form['title']['#default_value'] ?? NULL);
    $this->assertIsArray($form['description'] ?? NULL);
    $this->assertSame('Descricao', $form['description']['#default_value'] ?? NULL);

    $formState->setValue('title', 'Novo Titulo');
    $formState->setValue('description', 'Nova Descricao');
    $step->submitForm($form, $formState);

    $cached = $formState->getTemporaryValue('wizard');
    $this->assertIsArray($cached);
    $this->assertSame('Novo Titulo', $cached['title'] ?? NULL);
    $this->assertSame('Nova Descricao', $cached['description'] ?? NULL);
  }

  /**
   * Testa a etapa de estrutura do curso.
   */
  public function testCourseStructureStep(): void {
    $step = new CourseStructureStepForm();
    $step->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame('aculta_lms_course_builder_wizard_structure', $step->getFormId());

    $formState = new FormState();
    $formState->setValue('initial_lessons', "Aula 1\nAula 2");
    $form = [];
    $step->submitForm($form, $formState);

    $cached = $formState->getTemporaryValue('wizard');
    $this->assertIsArray($cached);
    $this->assertSame("Aula 1\nAula 2", $cached['initial_lessons'] ?? NULL);
  }

  /**
   * Testa a etapa de revisao do curso.
   */
  public function testCourseReviewStep(): void {
    $step = new CourseReviewStepForm();
    $step->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame('aculta_lms_course_builder_wizard_review', $step->getFormId());

    $formState = new FormState();
    $formState->setTemporaryValue('wizard', [
      'title' => 'Curso de Teste',
      'description' => 'Descricao Completa',
      'initial_lessons' => 'Aula 1',
    ]);

    $form = $step->buildForm([], $formState);
    $this->assertArrayHasKey('review_summary', $form);
    $this->assertArrayHasKey('publish', $form);

    $formState->setValue('publish', 1);
    $step->submitForm($form, $formState);

    $cached = $formState->getTemporaryValue('wizard');
    $this->assertIsArray($cached);
    $this->assertIsBool($cached['publish'] ?? NULL);
    $this->assertTrue($cached['publish']);
  }

}
