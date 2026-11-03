<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\RecentUploads;
use Drupal\file\FileInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Cobre a regra de dono e de pasta dos envios recentes do Studio.
 */
#[CoversClass(RecentUploads::class)]
#[Group('aculta_file_uploader')]
final class RecentUploadsTest extends UnitTestCase {

  /**
   * Cria um File de teste com dono e URI definidos.
   */
  private function file(string $uri, int $owner): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $file->method('getFileUri')->willReturn($uri);
    $file->method('getOwnerId')->willReturn($owner);
    return $file;
  }

  /**
   * O próprio usuário, na pasta da integração, é dono do envio.
   */
  public function testOwnerInsideIntegrationFolderIsAllowed(): void {
    $recent = new RecentUploads($this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class));
    self::assertTrue($recent->isOwnedBy($this->file('private://studio/envio/wiki/a.png', 7), 7, 'wiki'));
  }

  /**
   * Outro usuário não usa o envio, mesmo na pasta certa.
   */
  public function testOtherUserIsRefused(): void {
    $recent = new RecentUploads($this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class));
    self::assertFalse($recent->isOwnedBy($this->file('private://studio/envio/wiki/a.png', 7), 8, 'wiki'));
  }

  /**
   * Envio de uma integração não vale para outra.
   */
  public function testFileFromAnotherIntegrationIsRefused(): void {
    $recent = new RecentUploads($this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class));
    self::assertFalse($recent->isOwnedBy($this->file('private://studio/envio/forum/a.png', 7), 7, 'wiki'));
  }

  /**
   * Administrador vê e usa envios de outro usuário, dentro da pasta da integração.
   */
  public function testAdministratorMayUseAnotherUsersUploadInsideTheFolder(): void {
    $recent = new RecentUploads($this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class));
    self::assertTrue($recent->isOwnedBy($this->file('private://studio/envio/wiki/a.png', 7), 0, 'wiki', TRUE));
    self::assertFalse($recent->isOwnedBy($this->file('private://studio/envio/forum/a.png', 7), 0, 'wiki', TRUE));
  }

  /**
   * Caminho com ".." não escapa da pasta da integração.
   */
  public function testPathTraversalIsRefused(): void {
    $recent = new RecentUploads($this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class));
    self::assertFalse($recent->isOwnedBy($this->file('private://studio/envio/wiki/../forum/a.png', 7), 7, 'wiki'));
  }

  /**
   * Nome de integração fora de letras minúsculas é recusado antes de compor caminho.
   */
  public function testInvalidIntegrationNameIsRefused(): void {
    $recent = new RecentUploads($this->createMock(\Drupal\Core\Entity\EntityTypeManagerInterface::class));
    self::assertFalse($recent->isOwnedBy($this->file('private://studio/envio/wiki/a.png', 7), 7, 'wiki/../x'));
    self::assertSame([], $recent->forUser(7, '../wiki'));
  }

}
