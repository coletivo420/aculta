<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_patcher\Unit;

use Drupal\aculta_patcher\PatchDiffValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validação de formato e de caminhos do unified diff, sem Drupal nem processo.
 */
#[CoversClass(PatchDiffValidator::class)]
#[Group('aculta_patcher')]
class PatchDiffValidatorTest extends UnitTestCase {

  /**
   * Diff mínimo válido, dentro do pacote. */
  private const VALID = "--- a/src/Foo.php\n+++ b/src/Foo.php\n@@ -1 +1 @@\n-a\n+b\n";

  /**
   * Diff válido não lança exceção.
   */
  public function testValidDiffIsAccepted(): void {
    (new PatchDiffValidator())->assertValid('p1', self::VALID);
    $this->addToAssertionCount(1);
  }

  /**
   * Criação de arquivo com /dev/null é aceita.
   */
  public function testDevNullCreationIsAccepted(): void {
    $diff = "--- /dev/null\n+++ b/src/New.php\n@@ -0,0 +1 @@\n+x\n";
    (new PatchDiffValidator())->assertValid('p1', $diff);
    $this->addToAssertionCount(1);
  }

  /**
   * Entradas que não formam um unified diff utilizável.
   *
   * @return array<string, array{string}>
   *   Casos inválidos por nome.
   */
  public static function invalidDiffProvider(): array {
    return [
      'sem cabeçalho ---' => ["+++ b/a.php\n@@ -1 +1 @@\n-a\n+b\n"],
      'sem cabeçalho +++' => ["--- a/a.php\n@@ -1 +1 @@\n-a\n+b\n"],
      'sem hunk @@' => ["--- a/a.php\n+++ b/a.php\n"],
      'texto puro' => ['isto não é um diff'],
      'vazio' => [''],
      'excede 256 KiB' => ["--- a/a.php\n+++ b/a.php\n@@ -1 +1 @@\n" . str_repeat('+x' . str_repeat('a', 100) . "\n", 3000)],
    ];
  }

  /**
   * Diffs sem a forma mínima são recusados.
   */
  #[DataProvider('invalidDiffProvider')]
  public function testMalformedDiffIsRejected(string $diff): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('não é um unified diff válido');
    (new PatchDiffValidator())->assertValid('p1', $diff);
  }

  /**
   * Caminhos que escapam do pacote são recusados.
   *
   * @return array<string, array{string}>
   *   Caminhos perigosos por nome.
   */
  public static function escapingDiffProvider(): array {
    return [
      'absoluto unix' => ["--- /etc/passwd\n+++ b/x\n@@ -1 +1 @@\n-a\n+b\n"],
      'absoluto no destino' => ["--- a/x\n+++ /etc/passwd\n@@ -1 +1 @@\n-a\n+b\n"],
      'traversal no destino' => ["--- a/x\n+++ b/../../../etc/hosts\n@@ -1 +1 @@\n-a\n+b\n"],
      'traversal no origem' => ["--- a/../../x\n+++ b/x\n@@ -1 +1 @@\n-a\n+b\n"],
      'letra de unidade' => ["--- C:\\Windows\\x\n+++ b/x\n@@ -1 +1 @@\n-a\n+b\n"],
    ];
  }

  /**
   * Caminhos perigosos são recusados com a mensagem de fronteira.
   */
  #[DataProvider('escapingDiffProvider')]
  public function testEscapingPathIsRejected(string $diff): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('aponta para um caminho fora do pacote');
    (new PatchDiffValidator())->assertValid('p1', $diff);
  }

  /**
   * Um `..` dentro do nome (não como segmento) não é traversal.
   */
  public function testDotsInsideFileNameAreNotTraversal(): void {
    $diff = "--- a/src/file..name.php\n+++ b/src/file..name.php\n@@ -1 +1 @@\n-a\n+b\n";
    $this->assertFalse((new PatchDiffValidator())->escapesPackage($diff));
  }

  /**
   * Um diff válido de tamanho máximo (256 KiB) passa.
   */
  public function testSizeLimitIsInclusive(): void {
    $header = "--- a/a.php\n+++ b/a.php\n@@ -1 +1 @@\n";
    $padding = PatchDiffValidator::MAX_BYTES - strlen($header);
    $diff = $header . str_repeat('+', $padding);
    $this->assertSame(PatchDiffValidator::MAX_BYTES, strlen($diff));
    (new PatchDiffValidator())->assertValid('p1', $diff);
    $this->addToAssertionCount(1);
  }

}
