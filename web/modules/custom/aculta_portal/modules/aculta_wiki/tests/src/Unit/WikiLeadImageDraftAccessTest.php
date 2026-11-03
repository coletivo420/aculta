<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_wiki\Hook\WikiLeadImageDownloadAccess;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regressão: editor de verbete em rascunho vê a prévia; visitante não. Passa o id como int (strict_types).
 */
#[Group('aculta_wiki')]
#[CoversClass(WikiLeadImageDownloadAccess::class)]
final class WikiLeadImageDraftAccessTest extends UnitTestCase {

  /**
   * Editor com acesso de atualização ao verbete em rascunho: a prévia é liberada (NULL).
   */
  public function testEditorOfDraftGetsPreview(): void {
    self::assertNull($this->decide(TRUE));
  }

  /**
   * Quem não pode editar o verbete: negado (-1).
   */
  public function testVisitorIsDeniedDraftPreview(): void {
    self::assertSame(-1, $this->decide(FALSE));
  }

  /**
   * Monta o hook com mídia não publicada (rascunho) e um verbete cuja atualização depende de $canUpdate.
   */
  private function decide(bool $canUpdate): ?int {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn('783');
    $files = $this->createMock(FileRepositoryInterface::class);
    $files->method('loadByUri')->willReturn($file);

    $mediaQuery = $this->queryReturning(['277']);
    $mediaQuery->method('condition')->willReturnSelf();
    $noPublished = $this->queryReturning([]);
    $noPublished->method('condition')->willReturnSelf();
    $nodeQuery = $this->queryReturning(['604']);
    $nodeQuery->method('condition')->willReturnSelf();

    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->method('getQuery')->willReturnOnConsecutiveCalls($noPublished, $mediaQuery);
    $node = $this->createMock(NodeInterface::class);
    $node->method('access')->willReturn($canUpdate);
    $nodeStorage = $this->createMock(EntityStorageInterface::class);
    $nodeStorage->method('getQuery')->willReturnOnConsecutiveCalls($nodeQuery, $nodeQuery);
    $nodeStorage->method('loadMultiple')->willReturn([$node]);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->willReturnCallback(fn(string $type) => $type === 'media' ? $mediaStorage : $nodeStorage);
    $account = $this->createMock(AccountInterface::class);

    $hook = new WikiLeadImageDownloadAccess($files, $etm, $account);
    return $hook->fileDownload('private://wiki/imagens/valida_0.png');
  }

  /**
   * Query fake que devolve os ids informados.
   *
   * @param string[] $ids
   */
  private function queryReturning(array $ids): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('accessCheck')->willReturnSelf();
    $query->method('range')->willReturnSelf();
    $query->method('sort')->willReturnSelf();
    $query->method('execute')->willReturn($ids);
    return $query;
  }

}
