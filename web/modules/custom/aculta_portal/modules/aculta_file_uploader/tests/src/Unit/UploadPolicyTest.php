<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_file_uploader\Unit;

use Drupal\aculta_file_uploader\UploadPolicy;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifica o catálogo central e as restrições escopadas.
 */
#[Group('aculta_file_uploader')]
final class UploadPolicyTest extends UnitTestCase {

  /**
   * Política padrão usa o catálogo central e o limite de 10 MB.
   */
  public function testDefaultValidatorsUseCentralPolicy(): void {
    $validators = (new UploadPolicy())->validators();
    $this->assertSame(UploadPolicy::EXTENSIONS, $validators['FileExtension']['extensions']);
    $this->assertSame(UploadPolicy::MAX_BYTES, $validators['FileSizeLimit']['fileLimit']);
  }

  /**
   * Subconjunto do catálogo é aceito e normalizado.
   */
  public function testScopedSubsetIsAccepted(): void {
    $validators = (new UploadPolicy())->validators(2097152, 'png  jpg');
    $this->assertSame('png jpg', $validators['FileExtension']['extensions']);
    $this->assertSame(2097152, $validators['FileSizeLimit']['fileLimit']);
  }

  /**
   * Extensão fora do catálogo central é recusada.
   */
  public function testExtensionOutsideCatalogIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new UploadPolicy())->validators(NULL, 'png svg');
  }

  /**
   * Limite não positivo é recusado.
   */
  public function testNonPositiveLimitIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new UploadPolicy())->validators(0);
  }

}
