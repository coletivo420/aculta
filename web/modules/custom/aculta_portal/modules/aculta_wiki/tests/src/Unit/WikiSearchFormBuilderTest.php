<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Search\SearchPageController;
use Drupal\aculta_wiki\Presentation\WikiSearchFormBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Formulário de busca da Wiki420: rota, limites e valor atual.
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
        'aculta_portal.wiki_search' => '/wiki/busca',
        'aculta_portal.wiki_search_preview' => '/wiki/busca/previa',
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
      $requestStack->push(Request::create('/wiki/busca', 'GET', ['q' => $query]));
    }

    return new WikiSearchFormBuilder($translation, $requestStack);
  }

  /**
   * O formulário aponta para as rotas públicas e mantém os limites de busca.
   */
  public function testBuildsGetFormOnCanonicalRoutes(): void {
    $build = $this->builder(NULL)->build();

    $this->assertSame('get', $build['#method']);
    $this->assertSame('/wiki/busca', $build['#action']);
    $this->assertSame(['role' => 'search'], $build['#attributes']);
    $this->assertSame(['url.query_args:q'], $build['#cache']['contexts']);
    $this->assertSame(['aculta_portal/search-preview'], $build['#attached']['library']);
    $this->assertSame('', $build['q']['#value']);
    $this->assertSame('q', $build['q']['#name']);
    $this->assertSame(SearchPageController::MAX_CHARS, $build['q']['#maxlength']);
    $this->assertSame('/wiki/busca/previa', $build['q']['#attributes']['data-preview-url']);
    $this->assertSame((string) SearchPageController::MIN_CHARS, $build['q']['#attributes']['data-min-chars']);
    $this->assertSame('aculta-wiki-search-preview', $build['preview']['#attributes']['id']);
    $this->assertTrue($build['preview']['#attributes']['hidden']);
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
    $requestStack->push(Request::create('/wiki/busca', 'GET', ['q' => ['x']]));

    $build = (new WikiSearchFormBuilder($translation, $requestStack))->build();

    $this->assertSame('', $build['q']['#value']);
  }

}
