<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_learning_contracts\Learning\StudentLearningSummary;
use Drupal\aculta_learning_contracts\Learning\StudentLearningSummaryReaderInterface;
use Drupal\aculta_studio\Learning\LearnerDashboardComposer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Covers the learner dashboard's theme-neutral Studio fidelity scope.
 */
#[CoversClass(LearnerDashboardComposer::class)]
#[Group('aculta_studio')]
final class LearnerDashboardComposerTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Url::fromRoute() precisa de um url_generator registrado nos testes
    // unitários: os botões do SDC recebem a URL já gerada.
    $generator = $this->createMock(UrlGeneratorInterface::class);
    $generator->method('generateFromRoute')->willReturn('/cursos');
    $container = new ContainerBuilder();
    $container->set('url_generator', $generator);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * The dashboard root carries only the semantic Studio scope token.
   */
  public function testDashboardRootCarriesOnlyTheSemanticStudioScope(): void {
    $account = $this->createMock(AccountProxyInterface::class);
    $summary = new StudentLearningSummary([], NULL, [], new CacheableMetadata());
    $reader = $this->createMock(StudentLearningSummaryReaderInterface::class);
    $reader->expects(self::once())
      ->method('getStudentLearningSummary')
      ->with($account)
      ->willReturn($summary);
    $composer = new LearnerDashboardComposer(
      $reader,
      $account,
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );

    $build = $composer->build();

    self::assertSame('studio-fidelity-scope', $build['#attributes']['data-portal-ui']);
    self::assertSame('true', $build['#attributes']['data-aculta-learning-home']);
    self::assertSame('Minha aprendizagem', $build['context_header']['#props']['title']);
    self::assertArrayHasKey('courses_section', $build);
    self::assertArrayNotHasKey('continue_section', $build);
    self::assertArrayHasKey('#cache', $build);
  }

  /**
   * A missing provider keeps the experience unavailable and fails closed.
   */
  public function testUnavailableWithoutSummaryReader(): void {
    $composer = new LearnerDashboardComposer(
      NULL,
      $this->createMock(AccountProxyInterface::class),
      $this->createMock(DateFormatterInterface::class),
      $this->getStringTranslationStub(),
    );

    self::assertFalse($composer->available());
    $this->expectException(NotFoundHttpException::class);
    $composer->build();
  }

}
