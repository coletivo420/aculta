<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Search\SearchPageController;
use Drupal\aculta_wiki\Presentation\WikiSearchFormBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Template\AttributeValueBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Busca Wiki: rota, limites, valor atual e região acessível da prévia.
 */
#[CoversClass(WikiSearchFormBuilder::class)]
#[Group('aculta_wiki')]
final class WikiSearchFormBuilderTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')
      ->willReturnCallback(static fn ($routeName) => match ($routeName) {
        'aculta_portal.wiki_search' => '/busca-wiki',
        'aculta_portal.wiki_search_preview' => '/busca-wiki/previa',
        default => '/' . $routeName,
      });
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Builds the form builder for the given query string.
   */
  private function builder(?string $query): WikiSearchFormBuilder {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn ($string) => (string) $string);

    $requestStack = new RequestStack();
    if ($query !== NULL) {
      $requestStack->push(Request::create('/busca-wiki', 'GET', ['q' => $query]));
    }

    return new WikiSearchFormBuilder($translation, $requestStack);
  }

  /**
   * O formulário aponta para as rotas públicas e mantém os limites de busca.
   */
  public function testBuildsGetFormOnCanonicalRoutes(): void {
    $build = $this->builder(NULL)->build();

    $this->assertSame('get', $build['#method']);
    $this->assertSame('/busca-wiki', $build['#action']);
    $this->assertSame(['role' => 'search'], $build['#attributes']);
    $this->assertSame(['url.query_args:q'], $build['#cache']['contexts']);
    $this->assertSame(['aculta_portal/search-preview'], $build['#attached']['library']);
    $queryInput = $build['q'] ?? NULL;
    self::assertIsArray($queryInput);
    self::assertSame('', $queryInput['#value']);
    self::assertSame('q', $queryInput['#name']);
    self::assertSame(SearchPageController::MAX_CHARS, $queryInput['#maxlength']);
    self::assertArrayNotHasKey('#title', $queryInput, 'O rótulo explícito é a única label do campo.');
    $queryAttributes = $queryInput['#attributes'] ?? NULL;
    self::assertIsArray($queryAttributes);
    self::assertSame('aculta-wiki-search-header-q', $queryAttributes['id']);
    // O módulo expõe só marcadores neutros; as classes visuais são do tema.
    self::assertSame('search-preview-input', $queryAttributes['data-portal-ui']);
    self::assertSame('/busca-wiki/previa', $queryAttributes['data-preview-url']);
    self::assertSame((string) SearchPageController::MIN_CHARS, $queryAttributes['data-min-chars']);
    $label = $build['q_label'] ?? NULL;
    self::assertIsArray($label);
    $labelAttributes = $label['#attributes'] ?? NULL;
    self::assertIsArray($labelAttributes);
    self::assertSame('aculta-wiki-search-header-q', $labelAttributes['for']);
    self::assertSame('label', $label['#tag']);
    self::assertSame('Pesquisar na Wiki420', $label['#value']);
    self::assertIsArray($labelAttributes['class']);
    self::assertContains('visually-hidden', $labelAttributes['class']);
    $this->assertSame('aculta420_v2:hint', $build['preview']['#slots']['content']['hint']['#component']);
    $attributes = $build['preview']['#props']['attributes'];
    $this->assertSame('aculta-wiki-search-header-preview', $attributes['id']->value());
    $this->assertSame('region', $attributes['role']->value());
    $this->assertSame('Prévia dos resultados da busca', $attributes['aria-label']->value());
    $this->assertSame('polite', $attributes['aria-live']->value());
    $this->assertTrue($attributes['hidden']->value());
  }

  /**
   * Header and route body forms receive separate IDs and preview targets.
   */
  public function testFormInstancesHaveUniqueIds(): void {
    $builder = $this->builder(NULL);
    $header = $builder->build('header');
    $page = $builder->build('page');

    $headerInput = $header['q'] ?? NULL;
    $pageInput = $page['q'] ?? NULL;
    self::assertIsArray($headerInput);
    self::assertIsArray($pageInput);
    $headerInputAttributes = $headerInput['#attributes'] ?? NULL;
    $pageInputAttributes = $pageInput['#attributes'] ?? NULL;
    self::assertIsArray($headerInputAttributes);
    self::assertIsArray($pageInputAttributes);
    self::assertSame('aculta-wiki-search-header-q', $headerInputAttributes['id'] ?? NULL);
    self::assertSame('aculta-wiki-search-page-q', $pageInputAttributes['id'] ?? NULL);

    $headerPreview = $header['preview'] ?? NULL;
    $pagePreview = $page['preview'] ?? NULL;
    self::assertIsArray($headerPreview);
    self::assertIsArray($pagePreview);
    $headerPreviewProps = $headerPreview['#props'] ?? NULL;
    $pagePreviewProps = $pagePreview['#props'] ?? NULL;
    self::assertIsArray($headerPreviewProps);
    self::assertIsArray($pagePreviewProps);
    $headerPreviewAttributes = $headerPreviewProps['attributes'] ?? NULL;
    $pagePreviewAttributes = $pagePreviewProps['attributes'] ?? NULL;
    self::assertInstanceOf(Attribute::class, $headerPreviewAttributes);
    self::assertInstanceOf(Attribute::class, $pagePreviewAttributes);
    $headerIdAttribute = $headerPreviewAttributes['id'] ?? NULL;
    $pageIdAttribute = $pagePreviewAttributes['id'] ?? NULL;
    self::assertInstanceOf(AttributeValueBase::class, $headerIdAttribute);
    self::assertInstanceOf(AttributeValueBase::class, $pageIdAttribute);
    $headerPreviewId = $headerIdAttribute->value();
    $pagePreviewId = $pageIdAttribute->value();
    self::assertSame('aculta-wiki-search-header-preview', $headerPreviewId);
    self::assertSame('aculta-wiki-search-page-preview', $pagePreviewId);
    self::assertSame(
      $headerPreviewId,
      $headerInputAttributes['data-preview-target'] ?? NULL,
    );
    self::assertSame(
      $pagePreviewId,
      $pageInputAttributes['data-preview-target'] ?? NULL,
    );
  }

  /**
   * O valor atual é repetido no cabeçalho, sem espaços nas pontas.
   */
  public function testRepeatsCurrentQueryTrimmed(): void {
    $build = $this->builder('  maconha  ')->build();

    $this->assertSame('maconha', $build['q']['#value']);
  }

  /**
   * O valor repetido no cabeçalho é limitado a 100 caracteres.
   */
  public function testTruncatesCurrentQuery(): void {
    $build = $this->builder(str_repeat('a', 140))->build();

    $this->assertSame(str_repeat('a', SearchPageController::MAX_CHARS), $build['q']['#value']);
  }

  /**
   * Query não textual (ex.: `q[]=x`) não vira valor do campo.
   */
  public function testIgnoresNonStringQuery(): void {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')
      ->willReturnCallback(static fn ($string) => (string) $string);

    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/busca-wiki', 'GET', ['q' => ['x']]));

    $build = (new WikiSearchFormBuilder($translation, $requestStack))->build();

    $this->assertSame('', $build['q']['#value']);
  }

}
