<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Wiki\WikiProvider;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Classifica a publicação de verbetes em primeira publicação e revisão.
 */
#[CoversClass(WikiProvider::class)]
#[Group('aculta_statistics')]
final class WikiProviderTest extends TestCase {

  /**
   * Primeira publicação: verbo publicado sem revisão publicada anterior.
   */
  public function testFirstPublicationIsCounted(): void {
    $conditions = [];
    $node = $this->node(7, revisionId: 10, originalRevisionId: NULL, published: TRUE);
    $provider = $this->provider([], $conditions);

    self::assertSame(WikiProvider::PUBLISHED, $provider->metricForInsert($node));
    self::assertContains(['vid', 10, '<>'], $conditions);
  }

  /**
   * Revisão publicada de verbete já publicado conta como atualização.
   */
  public function testPublishedRevisionOfPublishedEntryIsCountedAsUpdate(): void {
    $node = $this->node(7, revisionId: 11, originalRevisionId: 10, published: TRUE);
    $provider = $this->provider([10]);

    self::assertSame(WikiProvider::UPDATED, $provider->metricForUpdate($node));
  }

  /**
   * Rascunho não publica e não emite métrica.
   */
  public function testDraftIsNotCounted(): void {
    $node = $this->node(7, revisionId: 10, originalRevisionId: 9, published: FALSE);
    $provider = $this->provider([]);

    self::assertNull($provider->metricForInsert($node));
    self::assertNull($provider->metricForUpdate($node));
  }

  /**
   * Conteúdo sincronizado ou importado não emite métrica.
   */
  public function testSynchronizedContentIsNotCounted(): void {
    $insert = $this->node(7, revisionId: 10, originalRevisionId: NULL, published: TRUE, syncing: TRUE);
    $update = $this->node(7, revisionId: 11, originalRevisionId: 10, published: TRUE, syncing: TRUE);
    $provider = $this->provider([]);

    self::assertNull($provider->metricForInsert($insert));
    self::assertNull($provider->metricForUpdate($update));
  }

  /**
   * Outro tipo de conteúdo não entra no catálogo da Wiki.
   */
  public function testOtherBundleIsNotCounted(): void {
    $node = $this->node(7, revisionId: 10, published: TRUE, bundle: 'article');
    $provider = $this->provider([]);

    self::assertNull($provider->metricForInsert($node));
  }

  /**
   * Gravação sem revisão nova não é publicação, mesmo se o nó está publicado.
   */
  public function testUpdateWithoutNewRevisionIsNotCounted(): void {
    $node = $this->node(7, revisionId: 10, originalRevisionId: 10, published: TRUE);
    $provider = $this->provider([9]);

    self::assertNull($provider->metricForUpdate($node));
  }

  /**
   * Publicação de rascunho existente conta como primeira publicação.
   */
  public function testFirstPublicationOfExistingDraftIsCounted(): void {
    $node = $this->node(7, revisionId: 11, originalRevisionId: 10, published: TRUE);
    $provider = $this->provider([]);

    self::assertSame(WikiProvider::PUBLISHED, $provider->metricForUpdate($node));
  }

  /**
   * Sem revisão nova e sem publicação, a consulta de revisões não é executada.
   */
  public function testConditionsIncludePublishedStatusAndExcludeCurrentRevision(): void {
    $conditions = [];
    $node = $this->node(7, revisionId: 10, published: TRUE);
    $this->provider([], $conditions)->metricForInsert($node);

    self::assertContains(['nid', 7, NULL], $conditions);
    self::assertContains(['status', 1, NULL], $conditions);
    self::assertContains(['vid', 10, '<>'], $conditions);
  }

  /**
   * Monta o provider com uma consulta de revisões controlada.
   *
   * @param list<int> $publishedRevisions
   *   IDs de revisões publicadas anteriores.
   * @param array<int, array{0: string, 1: mixed, 2: ?string}>|null $conditions
   *   Recebe as condições aplicadas à consulta.
   */
  private function provider(array $publishedRevisions, ?array &$conditions = NULL): WikiProvider {
    $conditions = [];

    $query = $this->createMock(QueryInterface::class);
    $query->method('allRevisions')->willReturnSelf();
    $query->method('accessCheck')->willReturnSelf();
    $query->method('condition')->willReturnCallback(
      function (string $field, mixed $value = NULL, ?string $operator = NULL) use ($query, &$conditions): QueryInterface {
        $conditions[] = [$field, $value, $operator];
        return $query;
      },
    );
    $query->method('execute')->willReturn($publishedRevisions);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('node')->willReturn($storage);

    return new WikiProvider($entityTypeManager);
  }

  /**
   * Monta um verbete com o estado pedido.
   */
  private function node(
    int $id,
    int $revisionId,
    ?int $originalRevisionId = NULL,
    bool $published = FALSE,
    bool $syncing = FALSE,
    string $bundle = WikiProvider::BUNDLE,
  ): NodeInterface {
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType->method('isRevisionable')->willReturn(TRUE);

    $node = $this->createMock(NodeInterface::class);
    $node->method('id')->willReturn($id);
    $node->method('bundle')->willReturn($bundle);
    $node->method('isPublished')->willReturn($published);
    $node->method('isSyncing')->willReturn($syncing);
    $node->method('getRevisionId')->willReturn($revisionId);
    $node->method('getEntityType')->willReturn($entityType);

    $original = $this->createMock(NodeInterface::class);
    $original->method('getRevisionId')->willReturn($originalRevisionId);
    $node->method('getOriginal')->willReturn($original);

    return $node;
  }

}
