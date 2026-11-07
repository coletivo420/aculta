<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_paths\Unit\Validation;

use Drupal\aculta_paths\Validation\EnderecoValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validação de slug e de endereço de subdomínio.
 */
#[Group('aculta_paths')]
final class EnderecoValidatorTest extends UnitTestCase {

  /**
   * Fornece valores considerados válidos.
   *
   * @return array<string, array<int, mixed>>
   *   Casos indexados por descrição.
   */
  public static function validos(): array {
    return [
      'slug simples' => ['loja', FALSE],
      'slug com hífen' => ['loja-sativa', FALSE],
      'slug com número' => ['estudio420', FALSE],
      'host' => ['wiki420.exemplo.org', TRUE],
      'host com hífen' => ['wiki-420.exemplo.org', TRUE],
    ];
  }

  /**
   * Confirma que os valores válidos passam sem motivo de recusa.
   *
   * @param string $valor
   *   Slug ou host validado.
   * @param bool $host
   *   Se o valor é um host.
   */
  #[DataProvider('validos')]
  public function testValoresCertosPassam(string $valor, bool $host): void {
    $this->assertNull(EnderecoValidator::motivo($valor, $host));
  }

  /**
   * Fornece valores inválidos com o motivo esperado.
   *
   * @return array<string, array<int, mixed>>
   *   Casos indexados por descrição.
   */
  public static function invalidos(): array {
    return [
      'slug com espaço' => ['loja sativa', FALSE, 'caracteres', '«espaço»'],
      'slug com ponto' => ['loja.sativa', FALSE, 'caracteres', '«.»'],
      'slug com acento' => ['ação', FALSE, 'caracteres', '«ç»'],
      'slug com barra' => ['/loja', FALSE, 'caracteres', '«/»'],
      'slug começa com hífen' => ['-loja', FALSE, 'hifen_slug', NULL],
      'slug com hífen duplo' => ['loja--sativa', FALSE, 'hifen_slug', NULL],
      'host com https' => ['https://wiki.exemplo.org', TRUE, 'caracteres', '«:»'],
      'host com barra final' => ['wiki.exemplo.org/', TRUE, 'caracteres', '«/»'],
      'host com porta' => ['wiki.exemplo.org:8080', TRUE, 'caracteres', '«:»'],
      'host sem ponto' => ['wiki', TRUE, 'sem_ponto', NULL],
      'host com ponto sobrando' => ['wiki..org', TRUE, 'ponto_sobrando', NULL],
      'host com trecho com hífen' => ['-wiki.org', TRUE, 'trecho_hifen', NULL],
      'host com tld numérico' => ['wiki.org1', TRUE, 'final', NULL],
    ];
  }

  /**
   * Confirma que os valores inválidos são recusados com o motivo esperado.
   *
   * @param string $valor
   *   Slug ou host validado.
   * @param bool $host
   *   Se o valor é um host.
   * @param string $codigo
   *   Código de motivo esperado.
   * @param string|null $caractere
   *   Caractere esperado na lista de argumentos, ou NULL.
   */
  #[DataProvider('invalidos')]
  public function testValoresErradosSaoRecusadosComMotivo(string $valor, bool $host, string $codigo, ?string $caractere): void {
    $motivo = EnderecoValidator::motivo($valor, $host);
    $this->assertNotNull($motivo);
    $this->assertSame($codigo, $motivo['codigo']);
    if ($caractere !== NULL) {
      $this->assertStringContainsString($caractere, $motivo['args']['lista']);
    }
  }

  /**
   * Confirma que um valor vazio é recusado.
   */
  public function testVazioEhRecusado(): void {
    $this->assertSame('vazio', EnderecoValidator::motivo('', FALSE)['codigo']);
  }

}
