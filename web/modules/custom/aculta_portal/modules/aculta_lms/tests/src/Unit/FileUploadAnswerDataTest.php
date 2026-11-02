<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Plugin\ActivityAnswer\FileUpload;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Confere o formato de data['file_upload'] gravado pelo managed_file.
 */
#[CoversClass(FileUpload::class)]
#[Group('aculta_lms')]
final class FileUploadAnswerDataTest extends UnitTestCase {

  /**
   * O Form API grava ['fids' => [fid]]; o plugin portado precisa ler isso.
   */
  public function testReadsManagedFileFidsFormat(): void {
    self::assertSame(123, $this->pluginWith(123)->getFile($this->answer(['fids' => [123]]))->id());
  }

  /**
   * Respostas antigas com [fid] continuam legíveis.
   */
  public function testReadsLegacyNumericFormat(): void {
    self::assertSame(123, $this->pluginWith(123)->getFile($this->answer([123]))->id());
  }

  /**
   * Dado ausente, vazio ou malformado devolve NULL em vez de erro.
   *
   * @dataProvider invalidData
   */
  public function testMalformedDataReturnsNull(mixed $value): void {
    self::assertNull($this->pluginWith(NULL)->getFile($this->answer($value)));
  }

  /**
   * Valores inválidos para o teste de dado malformado.
   *
   * @return array<string, array{mixed}>
   */
  public static function invalidData(): array {
    return [
      'ausente' => [NULL],
      'string' => ['123'],
      'vazio fids' => [['fids' => []]],
      'fid zero' => [['fids' => [0]]],
      'texto' => [['fids' => ['abc']]],
    ];
  }

  /**
   * Answer com o dado informado.
   */
  private function answer(mixed $file_upload): AnswerInterface {
    $answer = $this->createMock(AnswerInterface::class);
    $data = $file_upload === NULL ? [] : ['file_upload' => $file_upload];
    $answer->method('getData')->willReturn($data);
    return $answer;
  }

  /**
   * Plugin com storage de File devolvendo o arquivo de fid $fid, ou NULL.
   */
  private function pluginWith(?int $fid): FileUpload {
    $file = $this->createMock(FileInterface::class);
    if ($fid !== NULL) {
      $file->method('id')->willReturn($fid);
    }
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(
      static fn ($id) => $fid !== NULL && (int) $id === $fid ? $file : NULL,
    );
    $entityTypes = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypes->method('getStorage')->with('file')->willReturn($storage);

    $plugin = new FileUpload([], 'file_upload', ['id' => 'file_upload', 'label' => 'File upload']);
    $property = new \ReflectionProperty($plugin, 'entityTypeManager');
    $property->setValue($plugin, $entityTypes);
    return $plugin;
  }

}
