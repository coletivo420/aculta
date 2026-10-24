<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Search\SearchPageController;
use Drupal\aculta_wiki\Controller\WikiController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Busca e prévia da Wiki420: limites, domínio e ausência de resultados.
 */
#[CoversClass(WikiController::class)]
#[Group('aculta_wiki')]
final class WikiControllerTest extends UnitTestCase {

  /**
   * Render array passed to the isolated renderer by the last preview.
   *
   * @var array<string, mixed>|null
   */
  private ?array $lastPreviewedBuild = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
    $urlGenerator->method('generateFromRoute')->willReturn('/generated-route');
    $container = new ContainerBuilder();
    $container->set('url_generator', $urlGenerator);
    $pathValidator = $this->createMock(PathValidatorInterface::class);
    $pathValidator->method('getUrlIfValidWithoutAccessCheck')->willReturn(NULL);
    $container->set('path.validator', $pathValidator);
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
   * Entity manager whose `domain` storage resolves (or not) the Wiki Domain.
   */
  private function entityManager(?DomainInterface $domain, ?EntityStorageInterface $nodeStorage = NULL, ?EntityAccessControlHandlerInterface $nodeAccess = NULL): EntityTypeManagerInterface {
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturn($domain);
    $viewStorage = $this->createMock(EntityStorageInterface::class);
    $viewStorage->method('load')->willReturn(NULL);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(
      static fn ($entityType) => match ($entityType) {
        'domain' => $domainStorage,
        'view' => $viewStorage,
        default => $nodeStorage,
      },
    );
    if ($nodeAccess !== NULL) {
      $entities->method('getAccessControlHandler')->with('node')->willReturn($nodeAccess);
    }

    return $entities;
  }

  /**
   * Real purpose manager with the Domain entity loaded through the storage.
   */
  private function domainPurpose(?DomainInterface $domain): DomainPurposeManager {
    return new DomainPurposeManager(
      $this->entityManager($domain),
      $this->createMock(DomainNegotiatorInterface::class),
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

  /**
   * Builds the controller with a stub translator and the given Domain.
   */
  private function controller(?DomainInterface $domain, ?EntityStorageInterface $nodeStorage = NULL, ?EntityAccessControlHandlerInterface $nodeAccess = NULL, ?AccountInterface $account = NULL, ?Connection $database = NULL): WikiController {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')
      ->willReturnCallback(static fn ($markup) => $markup->getUntranslatedString());

    $renderer = $this->createMock(RendererInterface::class);
    $renderer->method('renderInIsolation')->willReturnCallback(
      function (array &$build): string {
        $this->lastPreviewedBuild = $build;
        return '<p>prévia</p>';
      },
    );

    $controller = new WikiController(
      $this->entityManager($domain, $nodeStorage, $nodeAccess),
      $this->createMock(ViewExecutableFactory::class),
      $this->domainPurpose($domain),
      $database ?? $this->createMock(Connection::class),
      $this->createMock(DateFormatterInterface::class),
      $account ?? $this->createMock(AccountInterface::class),
      $renderer,
    );

    $property = new \ReflectionProperty(ControllerBase::class, 'stringTranslation');
    $property->setValue($controller, $translation);

    return $controller;
  }

  /**
   * Wiki Domain entity used by the tests.
   */
  private function domain(): DomainInterface {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('wiki420_aculta_org');
    return $domain;
  }

  /**
   * Termo vazio devolve só a casca da busca, sem consultar o banco.
   */
  public function testEmptyTermReturnsSearchShell(): void {
    $build = $this->controller(NULL)->search(Request::create('/wiki/busca', 'GET'));

    $this->assertSame('wiki-search-results', $build['#attributes']['data-portal-ui']);
    $this->assertSame('Buscar na Wiki420', (string) $build['title']['#value']);
    $this->assertSame(['url.query_args:q', 'domain', 'user.permissions', 'user.node_grants:view'], $build['#cache']['contexts']);
    $this->assertArrayNotHasKey('empty', $build);
    $this->assertArrayNotHasKey('results', $build);
  }

  /**
   * Array query parameters are malformed input and behave like an empty term.
   */
  public function testMalformedSearchTermReturnsSearchShell(): void {
    $build = $this->controller(NULL)->search(Request::create('/wiki/busca', 'GET', ['q' => ['unexpected']]));

    self::assertSame('wiki-search-results', $build['#attributes']['data-portal-ui']);
    self::assertArrayNotHasKey('empty', $build);
    self::assertArrayNotHasKey('results', $build);
  }

  /**
   * Search cache varies by node grants because the query applies node access.
   */
  public function testSearchCacheVariesByNodeGrants(): void {
    $build = $this->controller(NULL)->search(Request::create('/wiki/busca'));

    self::assertContains('user.node_grants:view', $build['#cache']['contexts']);
  }

  /**
   * Collaboration CTA uses bundle create access and preserves its cache data.
   */
  public function testHomeCollaborationUsesNodeCreateAccess(): void {
    $account = $this->createMock(AccountInterface::class);
    $access = AccessResult::allowed()->addCacheContexts(['user.permissions']);
    $nodeAccess = $this->createMock(EntityAccessControlHandlerInterface::class);
    $nodeAccess->expects(self::once())
      ->method('createAccess')
      ->with('wiki_entry', $account, [], TRUE)
      ->willReturn($access);

    $build = $this->controller(NULL, NULL, $nodeAccess, $account)->home();

    self::assertSame($access, $build['collaboration']['#access']);
    self::assertContains('user.node_grants:view', $build['#cache']['contexts']);
  }

  /**
   * Sem o Domain da Wiki, a busca informa indisponibilidade.
   */
  public function testSearchWithoutWikiDomainShowsUnavailable(): void {
    $build = $this->controller(NULL)->search(Request::create('/wiki/busca', 'GET', ['q' => 'maconha']));

    $this->assertSame('A busca da Wiki420 está indisponível no momento.', (string) $build['empty']['#plain_text']);
  }

  /**
   * Consulta publicada e limitada ao Domain, sem resultados visíveis.
   */
  public function testSearchWithoutMatchesShowsEmptyMessage(): void {
    $conditions = [];
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $account = $this->createMock(AccountInterface::class);
    $query->expects(self::once())
      ->method('addMetaData')
      ->with('account', $account)
      ->willReturnSelf();
    $query->method('condition')->willReturnCallback(
      static function ($field, $value = NULL, $operator = NULL) use (&$conditions, $query) {
        $conditions[] = [$field, $value, $operator];
        return $query;
      },
    );
    $query->method('range')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn([]);
    $group = $this->createMock(ConditionInterface::class);
    $group->method('condition')->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($group);

    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([])->willReturn([]);

    $build = $this->controller($this->domain(), $nodeStorage, account: $account)
      ->search(Request::create('/wiki/busca', 'GET', ['q' => 'maconha']));

    $this->assertContains(['type', 'wiki_entry', NULL], $conditions);
    $this->assertContains(['field_domain_source.target_id', 'wiki420_aculta_org', NULL], $conditions);
    $this->assertContains(['status', 1, NULL], $conditions);
    $this->assertSame('component', $build['empty']['#type']);
    $this->assertSame('aculta420:empty-state', $build['empty']['#component']);
    $this->assertSame('Nenhum verbete publicado corresponde a essa busca.', (string) $build['empty']['#slots']['message']['#plain_text']);
    $this->assertArrayNotHasKey('results', $build);
  }

  /**
   * The complete search caps long input before escaping/query construction.
   */
  public function testSearchTruncatesTermToMaximumLength(): void {
    $term = str_repeat('x', SearchPageController::MAX_CHARS + 20);
    $cappedTerm = mb_substr($term, 0, SearchPageController::MAX_CHARS);
    $account = $this->createMock(AccountInterface::class);
    $conditionGroup = $this->createMock(ConditionInterface::class);
    $conditionGroup->method('condition')->willReturnSelf();
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->method('addMetaData')->with('account', $account)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->with(0, 30)->willReturnSelf();
    $query->method('sort')->with('title')->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($conditionGroup);
    $query->method('execute')->willReturn([]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([])->willReturn([]);
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('escapeLike')->with($cappedTerm)->willReturn($cappedTerm);

    $build = $this->controller($this->domain(), $nodeStorage, account: $account, database: $database)
      ->search(Request::create('/wiki/busca', 'GET', ['q' => $term]));

    self::assertSame('component', $build['empty']['#type']);
    self::assertArrayNotHasKey('results', $build);
  }

  /**
   * Prévia pede pelo menos o mínimo de caracteres e não é cacheável.
   */
  public function testPreviewRejectsShortTerm(): void {
    $response = $this->controller($this->domain())
      ->preview(Request::create('/wiki/busca/previa', 'GET', ['q' => 'ab']));

    $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('<p>prévia</p>', $response->getContent());
    $this->assertArrayHasKey('hint', $this->lastPreviewedBuild);
    $this->assertArrayNotHasKey('empty', $this->lastPreviewedBuild);
  }

  /**
   * Array query parameters cannot reach the database through preview.
   */
  public function testPreviewTreatsMalformedTermAsShortInput(): void {
    $response = $this->controller($this->domain())
      ->preview(Request::create('/wiki/busca/previa', 'GET', ['q' => ['unexpected']]));

    self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    self::assertSame('<p>prévia</p>', $response->getContent());
    self::assertArrayHasKey('hint', $this->lastPreviewedBuild);
    self::assertArrayNotHasKey('empty', $this->lastPreviewedBuild);
  }

  /**
   * Preview caps the search, excludes denied Nodes, and links to full search.
   */
  public function testPreviewReturnsOnlyAuthorizedResultsAndTruncatesTerm(): void {
    $term = str_repeat('x', SearchPageController::MAX_CHARS + 20);
    $cappedTerm = mb_substr($term, 0, SearchPageController::MAX_CHARS);
    $account = $this->createMock(AccountInterface::class);
    $authorized = $this->createMock(NodeInterface::class);
    $authorized->method('access')->with('view')->willReturn(TRUE);
    $authorized->method('label')->willReturn('Verbete autorizado');
    $authorized->method('toUrl')->with('canonical')->willReturn(Url::fromRoute('entity.node.canonical', ['node' => '77']));
    $denied = $this->createMock(NodeInterface::class);
    $denied->method('access')->with('view')->willReturn(FALSE);

    $conditionGroup = $this->createMock(ConditionInterface::class);
    $conditionGroup->method('condition')->willReturnSelf();
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->with(TRUE)->willReturnSelf();
    $query->method('addMetaData')->with('account', $account)->willReturnSelf();
    $query->method('condition')->willReturnSelf();
    $query->method('range')->with(0, 8)->willReturnSelf();
    $query->method('sort')->with('title')->willReturnSelf();
    $query->method('orConditionGroup')->willReturn($conditionGroup);
    $query->method('execute')->willReturn([77, 78]);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturn($query);
    $nodeStorage->method('loadMultiple')->with([77, 78])->willReturn([77 => $authorized, 78 => $denied]);
    $database = $this->createMock(Connection::class);
    $database->expects(self::once())->method('escapeLike')->with($cappedTerm)->willReturn($cappedTerm);

    $response = $this->controller($this->domain(), $nodeStorage, account: $account, database: $database)
      ->preview(Request::create('/wiki/busca/previa', 'GET', ['q' => $term]));

    self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    self::assertSame(0, $this->lastPreviewedBuild['#cache']['max-age']);
    self::assertCount(1, $this->lastPreviewedBuild['list']['#items']);
    self::assertSame('Verbete autorizado', $this->lastPreviewedBuild['list']['#items'][0]['#title']);
    self::assertSame('entity.node.canonical', $this->lastPreviewedBuild['list']['#items'][0]['#url']->getRouteName());
    $fullSearchUrl = $this->lastPreviewedBuild['full']['link']['#url'];
    self::assertSame('aculta_portal.wiki_search', $fullSearchUrl->getRouteName());
    self::assertSame(['q' => $cappedTerm], $fullSearchUrl->getOption('query'));
  }

  /**
   * Prévia sem o Domain da Wiki informa indisponibilidade sem cache.
   */
  public function testPreviewWithoutWikiDomainShowsUnavailable(): void {
    $response = $this->controller(NULL)
      ->preview(Request::create('/wiki/busca/previa', 'GET', ['q' => 'maconha']));

    $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
    $this->assertSame('<p>prévia</p>', $response->getContent());
    $this->assertSame('A busca da Wiki420 está indisponível no momento.', (string) $this->lastPreviewedBuild['empty']['#plain_text']);
  }

}
