<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Kernel;

use Drupal\aculta_document_viewer\Document\ReadableMime;
use Drupal\aculta_events\Editorial\EventEditorialPresenter;
use Drupal\aculta_events\Entity\Event;
use Drupal\aculta_portal\Editorial\CardVariationManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Validates editorial resources.
 *
 * Covers ReadableMime, CardVariationManager and EventEditorialPresenter.
 */
#[Group('aculta_portal')]
#[RunTestsInSeparateProcesses]
final class EditorialResourcesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'options',
    'taxonomy',
    'aculta_events',
  ];

  /**
   * The readable MIME service under test.
   *
   * @var \Drupal\aculta_document_viewer\Document\ReadableMime
   */
  protected ReadableMime $readableMime;

  /**
   * The card variation manager under test.
   *
   * @var \Drupal\aculta_portal\Editorial\CardVariationManager
   */
  protected CardVariationManager $cardVariationManager;

  /**
   * The event editorial presenter under test.
   *
   * @var \Drupal\aculta_events\Editorial\EventEditorialPresenter
   */
  protected EventEditorialPresenter $editorialPresenter;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $root = dirname(__DIR__, 7);
    $loader = require $root . '/vendor/autoload.php';
    $loader->addPsr4('Drupal\\aculta_portal\\', $root . '/web/modules/custom/aculta_portal/src');
    $loader->addPsr4('Drupal\\aculta_events\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_events/src');
    $loader->addPsr4('Drupal\\aculta_document_viewer\\', $root . '/web/modules/custom/aculta_portal/modules/aculta_document_viewer/src');

    $container->register('aculta_portal.domain_purpose', \stdClass::class);
    $container->register('aculta_calendar.embed_hosts', \stdClass::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('aculta_event');
    $this->installSchema('file', ['file_usage']);

    $this->readableMime = new ReadableMime();
    $this->cardVariationManager = new CardVariationManager();
    $this->editorialPresenter = new EventEditorialPresenter();
  }

  /**
   * Tests MIME conversion, file-size localization and accessible labels.
   */
  public function testReadableMimeAndAccessibleLabels(): void {
    $this->assertSame('PDF', $this->readableMime->getHumanReadableFormat('application/pdf'));
    $this->assertSame('DOCX', $this->readableMime->getHumanReadableFormat('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
    $this->assertSame('XLSX', $this->readableMime->getHumanReadableFormat('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    $this->assertSame('CSV', $this->readableMime->getHumanReadableFormat('text/csv'));
    $this->assertSame('WebP', $this->readableMime->getHumanReadableFormat('image/webp'));

    $this->assertSame('500 B', $this->readableMime->formatFileSize(500));
    $this->assertSame('2,4 MB', $this->readableMime->formatFileSize((int) (2.4 * 1024 * 1024)));
    $this->assertSame('10 MB', $this->readableMime->formatFileSize((int) (10 * 1024 * 1024)));

    $label = $this->readableMime->getAccessibleDownloadLabel('Edital 2026', 'application/pdf', (int) (1.5 * 1024 * 1024));
    $this->assertStringContainsString('Edital 2026', $label);
    $this->assertStringContainsString('formato PDF', $label);
    $this->assertStringContainsString('1,5 MB', $label);
  }

  /**
   * Tests the closed variations list and render array decoration.
   */
  public function testCardVariationManagerEnforcement(): void {
    $allowed = $this->cardVariationManager->getAllowedVariations();
    $this->assertEqualsCanonicalizing(['compact', 'teaser', 'destaque', 'busca'], $allowed);

    // Test normalization with valid and fallback variations.
    $this->assertSame('compact', $this->cardVariationManager->normalize('compact'));
    $this->assertSame('destaque', $this->cardVariationManager->normalize('destaque'));
    $this->assertSame('teaser', $this->cardVariationManager->normalize('invalid_variation'));

    // Test render array decoration.
    $build = ['#type' => 'container', '#attributes' => []];
    $this->cardVariationManager->applyVariation($build, 'destaque');

    $this->assertSame('editorial-card', $build['#attributes']['data-portal-ui']);
    $this->assertArrayNotHasKey('class', $build['#attributes']);
    $this->assertSame('destaque', $build['#attributes']['data-card-variation']);
  }

  /**
   * Tests EventEditorialPresenter brand authorization guard and press mentions.
   */
  public function testEventSupportersBrandAuthorizationGuard(): void {
    $event = Event::create([
      'title' => 'Festival Cultural',
      'field_status' => 'publicado',
      'field_apoiadores' => "Empresa Parceira | Patrocínio Master | https://parceiro.exemplo.com",
    // NOT authorized.
      'field_apoiadores_autorizados' => FALSE,
    ]);
    $event->save();

    // Guard test: unauthorized supporters must return NULL.
    $rendered = $this->editorialPresenter->renderSupporters($event);
    $this->assertNull($rendered, 'Supporters must NOT be rendered if brand authorization is FALSE.');

    // Now authorize the brand usage.
    $event->set('field_apoiadores_autorizados', TRUE);
    $event->save();

    $rendered = $this->editorialPresenter->renderSupporters($event);
    $this->assertNotNull($rendered);
    $this->assertSame('event-supporters', $rendered['#attributes']['data-portal-ui']);
    $this->assertCount(1, $rendered['list']['#items']);
    $markup = (string) $rendered['list']['#items'][0]['#markup'];
    $this->assertStringContainsString('Empresa Parceira', $markup);
    $this->assertStringContainsString('Patrocínio Master', $markup);
    $this->assertStringContainsString('rel="noopener noreferrer"', $markup);
  }

  /**
   * Tests EventEditorialPresenter rendering of press clipping.
   */
  public function testEventPressQuotesRendering(): void {
    $event = Event::create([
      'title' => 'Mostra Teatral',
      'field_status' => 'publicado',
      'field_citacoes_imprensa' => "Jornal da Cidade | Espetáculo inovador e vibrante | 2026-10-15 | https://jornal.exemplo.com/materia",
    ]);
    $event->save();

    $rendered = $this->editorialPresenter->renderPressQuotes($event);
    $this->assertNotNull($rendered);
    $this->assertSame('event-press-quotes', $rendered['#attributes']['data-portal-ui']);
    $this->assertCount(1, $rendered['list']['#items']);

    $quoteMarkup = (string) $rendered['list']['#items'][0]['#markup'];
    $this->assertStringContainsString('Espetáculo inovador e vibrante', $quoteMarkup);
    $this->assertStringContainsString('Jornal da Cidade', $quoteMarkup);
    $this->assertStringContainsString('2026-10-15', $quoteMarkup);
  }

}
