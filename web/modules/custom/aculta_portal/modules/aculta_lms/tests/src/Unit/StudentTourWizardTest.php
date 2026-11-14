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

    $welcome = $operations['welcome'];
    $navigation = $operations['navigation'];
    $activities = $operations['activities'];
    $complete = $operations['complete'];
    $this->assertIsArray($welcome);
    $this->assertIsArray($navigation);
    $this->assertIsArray($activities);
    $this->assertIsArray($complete);
    $this->assertSame('Boas-vindas', $this->operationTitle($welcome));
    $this->assertSame('Navegacao e Sala de Aula', $this->operationTitle($navigation));
    $this->assertSame('Atividades e Avaliacoes', $this->operationTitle($activities));
    $this->assertSame('Conclusao e Certificados', $this->operationTitle($complete));
  }

  /**
   * Reads the title from an operation definition.
   *
   * @param array<array-key, mixed> $operation
   *   Wizard operation definition.
   */
  private function operationTitle(array $operation): string {
    $title = $operation['title'] ?? NULL;
    $this->assertIsString($title);
    return $title;
  }

}
