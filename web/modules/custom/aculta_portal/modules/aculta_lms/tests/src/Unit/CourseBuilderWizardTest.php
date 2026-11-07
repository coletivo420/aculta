<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Wizard\CourseBuilderWizard;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Testes unitarios do CTools CourseBuilderWizard.
 */
#[CoversClass(CourseBuilderWizard::class)]
final class CourseBuilderWizardTest extends UnitTestCase {

  /**
   * Valida parametros estruturais e operacoes do wizard.
   */
  public function testWizardOperationsAndParameters(): void {
    $tempstoreFactory = $this->createMock(SharedTempStoreFactory::class);
    $sharedTempstore = $this->createMock(SharedTempStore::class);
    $tempstoreFactory->method('get')->willReturn($sharedTempstore);

    $formBuilder = $this->createMock(FormBuilderInterface::class);
    $classResolver = $this->createMock(ClassResolverInterface::class);
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $renderer = $this->createMock(RendererInterface::class);

    $wizard = new CourseBuilderWizard(
      $tempstoreFactory,
      $formBuilder,
      $classResolver,
      $eventDispatcher,
      $routeMatch,
      $renderer,
      'aculta_lms.course_builder_wizard'
    );
    $wizard->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame('aculta_lms.course_builder_wizard', $wizard->getTempstoreId());
    $this->assertSame('aculta_lms.course_builder_wizard', $wizard->getRouteName());

    $operations = $wizard->getOperations([]);
    $this->assertArrayHasKey('details', $operations);
    $this->assertArrayHasKey('structure', $operations);
    $this->assertArrayHasKey('review', $operations);
    $this->assertSame('Dados do Curso', (string) $operations['details']['title']);
    $this->assertSame('Estrutura e Aulas', (string) $operations['structure']['title']);
    $this->assertSame('Revisao e Publicacao', (string) $operations['review']['title']);
  }

}
