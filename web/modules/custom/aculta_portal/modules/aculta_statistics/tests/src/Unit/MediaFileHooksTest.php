<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_statistics\Unit;

use Drupal\aculta_statistics\Catalog\MetricCatalog;
use Drupal\aculta_statistics\Hook\MediaFileHooks;
use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\aculta_statistics\Storage\AggregateStorageInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Aberturas de arquivo: conta a abertura permitida por arquivo, sem contar pedaços nem acesso negado.
 */
#[CoversClass(MediaFileHooks::class)]
#[Group('aculta_statistics')]
final class MediaFileHooksTest extends TestCase {

  /**
   * Abertura permitida (sem Range) grava uma vez, com o identificador do arquivo.
   */
  public function testOpeningAllowedFileIsRecorded(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record')->with('media.files.requests', '7', 1);

    $this->hook($storage, $this->file(7, TRUE), NULL)->fileDownload('private://revista.pdf');
  }

  /**
   * Pedaço intermediário do visualizador (Range a partir de um byte) não conta.
   */
  public function testRangeChunkIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->hook($storage, $this->file(7, TRUE), 'bytes=4096-8191')->fileDownload('private://revista.pdf');
  }

  /**
   * Abertura inicial com Range `bytes=0-` conta.
   */
  public function testInitialRangeIsCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->once())->method('record');

    $this->hook($storage, $this->file(7, TRUE), 'bytes=0-')->fileDownload('private://revista.pdf');
  }

  /**
   * Usuário sem permissão de download não gera contagem.
   */
  public function testDeniedAccessIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->hook($storage, $this->file(7, FALSE), NULL)->fileDownload('private://revista.pdf');
  }

  /**
   * Arquivo sem entidade correspondente não gera contagem.
   */
  public function testUnknownFileIsNotCounted(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $storage->expects($this->never())->method('record');

    $this->hook($storage, NULL, NULL)->fileDownload('private://fantasma.pdf');
  }

  /**
   * Hook sem cabeçalho extra: devolve NULL, sem alterar os cabeçalhos de outros módulos.
   */
  public function testHookReturnsNoHeaders(): void {
    $storage = $this->createMock(AggregateStorageInterface::class);
    $this->assertNull($this->hook($storage, $this->file(7, TRUE), NULL)->fileDownload('private://revista.pdf'));
  }

  /**
   * Monta o hook com um arquivo (ou nenhum) e um cabeçalho Range opcional.
   */
  private function hook(AggregateStorageInterface $storage, ?FileInterface $file, ?string $range): MediaFileHooks {
    $fileStorage = $this->createMock(EntityStorageInterface::class);
    $fileStorage->method('loadByProperties')->willReturn($file === NULL ? [] : [$file]);
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->willReturn($fileStorage);

    $request = Request::create('/system/files/revista.pdf');
    if ($range !== NULL) {
      $request->headers->set('Range', $range);
    }
    $stack = new RequestStack();
    $stack->push($request);

    return new MediaFileHooks(new StatisticsRecorder(new MetricCatalog(), $storage), $entityTypes, $stack);
  }

  /**
   * Arquivo de teste com identificador e resultado de acesso ao download.
   */
  private function file(int $id, bool $downloadAllowed): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('id')->willReturn($id);
    $file->method('access')->willReturn($downloadAllowed);
    return $file;
  }

}
