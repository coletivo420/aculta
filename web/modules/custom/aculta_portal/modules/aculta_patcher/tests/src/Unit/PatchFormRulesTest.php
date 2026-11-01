<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_patcher\Unit;

use Drupal\aculta_patcher\PatchFormRules;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regras de cadastro do patch: pacote, versão exata e diff, antes de salvar.
 */
#[CoversClass(PatchFormRules::class)]
#[Group('aculta_patcher')]
class PatchFormRulesTest extends UnitTestCase {

  /**
   * Diff válido dentro do pacote.
   */
  private const VALID = "--- a/src/A.php\n+++ b/src/A.php\n@@ -1 +1 @@\n-a\n+b\n";

  /**
   * Diff com caminho absoluto.
   */
  private const ABSOLUTE = "--- /etc/passwd\n+++ b/x\n@@ -1 +1 @@\n-a\n+b\n";

  /**
   * Diff com travessia de diretório.
   */
  private const TRAVERSAL = "--- a/x\n+++ b/../../x\n@@ -1 +1 @@\n-a\n+b\n";

  /**
   * Registro válido não gera erro.
   */
  public function testValidRecordHasNoErrors(): void {
    $this->assertSame([], (new PatchFormRules())->errors('h5p/h5p-editor', '1.25', self::VALID));
  }

  /**
   * Pacote, faixa de versão e diff inválidos são recusados por campo.
   *
   * @return array<string, array{string, string, string, string, string}>
   *   Casos com o campo esperado e o código de erro.
   */
  public static function invalidProvider(): array {
    return [
      'pacote sem barra' => ['semBarra', '1.0.0', self::VALID, 'package', 'package_invalid'],
      'pacote com maiúscula' => ['Vendor/Pkg', '1.0.0', self::VALID, 'package', 'package_invalid'],
      'versão com faixa' => ['vendor/pkg', '^1.0', self::VALID, 'version', 'version_not_exact'],
      'versão com maior-igual' => ['vendor/pkg', '>=1.0', self::VALID, 'version', 'version_not_exact'],
      'diff sem cabeçalho' => ['vendor/pkg', '1.0.0', "@@ -1 +1 @@\n-a\n+b\n", 'patch', 'patch_not_unified'],
      'diff sem hunk' => ['vendor/pkg', '1.0.0', "--- a/x\n+++ b/x\n", 'patch', 'patch_not_unified'],
      'caminho absoluto' => ['vendor/pkg', '1.0.0', self::ABSOLUTE, 'patch', 'patch_path_escapes'],
      'traversal' => ['vendor/pkg', '1.0.0', self::TRAVERSAL, 'patch', 'patch_path_escapes'],
    ];
  }

  /**
   * Cada entrada inválida gera o código esperado no campo esperado.
   */
  #[DataProvider('invalidProvider')]
  public function testInvalidRecordReportsFieldAndCode(string $package, string $version, string $diff, string $field, string $code): void {
    $errors = (new PatchFormRules())->errors($package, $version, $diff);
    $this->assertSame($code, $errors[$field] ?? NULL);
  }

  /**
   * Diff acima de 256 KiB é recusado antes das outras checagens.
   */
  public function testOversizeDiffIsReportedAsTooLarge(): void {
    $diff = str_repeat('x', PatchFormRules::MAX_BYTES + 1);
    $this->assertSame('patch_too_large', (new PatchFormRules())->errors('vendor/pkg', '1.0.0', $diff)['patch']);
  }

}
