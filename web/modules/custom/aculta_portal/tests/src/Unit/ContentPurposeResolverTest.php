<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit;

use Drupal\aculta_portal\Domain\ContentPurposeResolver;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainNegotiatorInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Testa a resolução de purpose de rota e de conteúdo do Portal.
 */
#[Group('aculta_portal')]
final class ContentPurposeResolverTest extends TestCase {

  /**
   * Bundles de tópico público do ACULTA Forum.
   */
  public static function forumBundles(): array {
    return [
      'ACULTA Forum topic' => ['aculta_forum_topic'],
    ];
  }

  /**
   * Só o bundle próprio pertence ao purpose FORUM.
   *
   * O bundle legado 'forum' saiu da regra (commit 4fa73cc0).
   */
  #[DataProvider('forumBundles')]
  public function testPublicTopicCanonicalRouteBelongsToForumPurpose(
    string $bundle,
  ): void {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $request = Request::create('/node/123');
    $request->attributes->set('_route', 'entity.node.canonical');
    $request->attributes->set('node', $node);
    $resolver = new ContentPurposeResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->domainPurposeManager(),
    );

    $this->assertSame('forum', $resolver->canonicalContentPurpose($request));
  }

  /**
   * Rota administrativa pertence a MAIN mesmo com conteúdo de outro purpose.
   *
   * `_admin_route` é marcado pelo Core (NodeAdminRouteSubscriber +
   * `node.settings:use_admin_theme`), não pelo Magazine.
   */
  public function testAdminRouteRequiresMainPurposeBeforeContentOwnership(): void {
    $route = new Route('/node/{node}/edit', [], [], [
      '_admin_route' => TRUE,
      '_aculta_domain_purpose' => 'magazine',
    ]);
    $request = Request::create('/node/90/edit');
    $request->attributes->set('node', $this->node('wiki_entry'));

    $this->assertSame(
      'main',
      $this->resolver()->requiredPurpose($route, 'entity.node.edit_form', $request),
    );
  }

  /**
   * Purpose explícito MAIN vence a resolução por conteúdo.
   */
  public function testExplicitMainPurposeRequiresMainHost(): void {
    $route = new Route('/node/{node}', [], [], [
      '_aculta_domain_purpose' => 'main',
    ]);
    $request = Request::create('/node/90');

    $this->assertSame(
      'main',
      $this->resolver()->requiredPurpose(
        $route,
        'entity.node.canonical',
        $request,
      ),
    );
  }

  /**
   * Sem precedência, o purpose declarado na rota é o exigido.
   */
  public function testDeclaredPurposeIsUsedWhenNoPrecedenceApplies(): void {
    $route = new Route('/node/{node}', [], [], [
      '_aculta_domain_purpose' => 'magazine',
    ]);
    $request = Request::create('/node/90');

    $this->assertSame(
      'magazine',
      $this->resolver()->requiredPurpose(
        $route,
        'entity.node.canonical',
        $request,
      ),
    );
  }

  /**
   * Leitura sem `_admin_route` e sem purpose não é redirecionada (B.7 §2).
   */
  public function testRouteWithoutPurposeReturnsNull(): void {
    $route = new Route('/node/{node}/latest', [], [], []);
    $request = Request::create('/node/90/latest');

    $this->assertNull(
      $this->resolver()->requiredPurpose(
        $route,
        'entity.node.latest_version',
        $request,
      ),
    );
  }

  /**
   * Criação de verbete Wiki exige o purpose WIKI.
   */
  public function testWikiNodeAddRequiresWikiPurpose(): void {
    $route = new Route('/node/add/{node_type}', [], [], []);
    $request = Request::create('/node/add/wiki_entry');
    $request->attributes->set('node_type', 'wiki_entry');

    $this->assertSame(
      'wiki',
      $this->resolver()->requiredPurpose($route, 'node.add', $request),
    );
  }

  /**
   * Operações de Node sobre verbete Wiki exigem o purpose WIKI.
   */
  public function testWikiNodeOperationRouteRequiresWikiPurpose(): void {
    $route = new Route('/node/{node}/edit', [], [], []);
    $request = Request::create('/node/10/edit');
    $request->attributes->set('node', $this->node('wiki_entry'));

    $this->assertSame(
      'wiki',
      $this->resolver()->requiredPurpose(
        $route,
        'entity.node.edit_form',
        $request,
      ),
    );
  }

  /**
   * Operação de Node de outro bundle cai no purpose declarado da rota.
   */
  public function testNodeOperationRouteOfOtherBundleFallsBack(): void {
    $route = new Route('/node/{node}/edit', [], [], [
      '_aculta_domain_purpose' => 'magazine',
    ]);
    $request = Request::create('/node/90/edit');
    $request->attributes->set('node', $this->node('revista'));

    $this->assertSame(
      'magazine',
      $this->resolver()->requiredPurpose(
        $route,
        'entity.node.edit_form',
        $request,
      ),
    );
  }

  /**
   * Grupo de curso LMS exige o purpose COURSES.
   */
  public function testLmsCourseGroupRequiresCoursesPurpose(): void {
    $route = new Route('/group/{group}/content', [], [], []);
    $request = Request::create('/group/1/content');
    $request->attributes->set('group', new class() {

      /**
       * Bundle do grupo.
       */
      public function bundle(): string {
        return 'lms_course';
      }

    });

    $this->assertSame(
      'courses',
      $this->resolver()->requiredPurpose(
        $route,
        'aculta_courses.group_content',
        $request,
      ),
    );
  }

  /**
   * Resolver com dependências mínimas.
   */
  private function resolver(): ContentPurposeResolver {
    return new ContentPurposeResolver(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->domainPurposeManager(),
    );
  }

  /**
   * Node mockado com o bundle informado.
   */
  private function node(string $bundle): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    return $node;
  }

  /**
   * DomainPurposeManager com negociação simulada.
   */
  private function domainPurposeManager(): DomainPurposeManager {
    $domain = $this->createMock(DomainInterface::class);
    $domain->method('id')->willReturn('forum_aculta_org');
    $negotiator = $this->createMock(DomainNegotiatorInterface::class);
    $negotiator->method('getActiveDomain')->willReturn($domain);
    return new DomainPurposeManager(
      $this->createMock(EntityTypeManagerInterface::class),
      $negotiator,
      $this->createMock(ConfigFactoryInterface::class),
      new RequestStack(),
    );
  }

}
