<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_wiki\Controller\WikiController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\views\ViewExecutableFactory;
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
   * Entity manager whose `domain` storage resolves (or not) the Wiki Domain.
   */
  private function entityManager(?DomainInterface $domain, ?EntityStorageInterface $nodeStorage = NULL): EntityTypeManagerInterface {
    $domainStorage = $this->createMock(EntityStorageInterface::class);
    $domainStorage->method('load')->willReturn($domain);

    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->willReturnCallback(
      static fn ($entityType) => $entityType === 'domain' ? $domainStorage : $nodeStorage,
    );

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
  private function controller(?DomainInterface $domain, ?EntityStorageInterface $nodeStorage = NULL): WikiController {
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
      $this->entityManager($domain, $nodeStorage),
      $this->createMock(ViewExecutableFactory::class),
      $this->domainPurpose($domain),
      $this->createMock(Connection::class),
      $this->createMock(DateFormatterInterface::class),
      $this->createMock(AccountInterface::class),
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
    $this->assertSame(['url.query_args:q', 'domain', 'user.permissions'], $build['#cache']['contexts']);
    $this->assertArrayNotHasKey('empty', $build);
    $this->assertArrayNotHasKey('results', $build);
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

    $build = $this->controller($this->domain(), $nodeStorage)->search(Request::create('/wiki/busca', 'GET', ['q' => 'maconha']));

    $this->assertContains(['type', 'wiki_entry', NULL], $conditions);
    $this->assertContains(['field_domain_source.target_id', 'wiki420_aculta_org', NULL], $conditions);
    $this->assertContains(['status', 1, NULL], $conditions);
    $this->assertSame('Nenhum verbete publicado corresponde a essa busca.', (string) $build['empty']['#plain_text']);
    $this->assertArrayNotHasKey('results', $build);
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
