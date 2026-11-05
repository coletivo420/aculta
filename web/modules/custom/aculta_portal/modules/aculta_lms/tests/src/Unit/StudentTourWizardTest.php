<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Wizard\StudentTourWizard;
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
 * Testes unitarios do StudentTourWizard via CTools.
 */
#[CoversClass(StudentTourWizard::class)]
final class StudentTourWizardTest extends UnitTestCase {

  /**
   * Valida parametros estruturais e operacoes do tour wizard.
   */
  public function testTourWizardOperationsAndParameters(): void {
    $tempstoreFactory = $this->createMock(SharedTempStoreFactory::class);
    $sharedTempstore = $this->createMock(SharedTempStore::class);
    $tempstoreFactory->method('get')->willReturn($sharedTempstore);

    $formBuilder = $this->createMock(FormBuilderInterface::class);
    $classResolver = $this->createMock(ClassResolverInterface::class);
    $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $routeMatch = $this->createMock(RouteMatchInterface::class);
    $renderer = $this->createMock(RendererInterface::class);

    $wizard = new StudentTourWizard(
      $tempstoreFactory,
      $formBuilder,
      $classResolver,
      $eventDispatcher,
      $routeMatch,
      $renderer,
      'aculta_lms.student_tour_wizard'
    );
    $wizard->setStringTranslation($this->getStringTranslationStub());

    $this->assertSame('aculta_lms.student_tour_wizard', $wizard->getTempstoreId());
    $this->assertSame('aculta_lms.student_tour_wizard', $wizard->getRouteName());

    $operations = $wizard->getOperations([]);
    $this->assertArrayHasKey('welcome', $operations);
    $this->assertArrayHasKey('navigation', $operations);
    $this->assertArrayHasKey('activities', $operations);
    $this->assertArrayHasKey('complete', $operations);

    $this->assertSame('Boas-vindas', (string) $operations['welcome']['title']);
    $this->assertSame('Navegacao e Sala de Aula', (string) $operations['navigation']['title']);
    $this->assertSame('Atividades e Avaliacoes', (string) $operations['activities']['title']);
    $this->assertSame('Conclusao e Certificados', (string) $operations['complete']['title']);
  }

}
