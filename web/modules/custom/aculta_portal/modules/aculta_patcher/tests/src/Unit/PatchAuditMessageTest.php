<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_patcher\Unit;

use Drupal\aculta_patcher\PatchAuditMessage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Trilha de auditoria: quem, o quê e em que estado, sem o conteúdo do diff.
 */
#[CoversClass(PatchAuditMessage::class)]
#[Group('aculta_patcher')]
class PatchAuditMessageTest extends UnitTestCase {

  /**
   * A mensagem traz id, pacote, versão, estado e usuário.
   */
  public function testMessageCarriesWhoWhatAndState(): void {
    $message = PatchAuditMessage::format('alterado', 'h5p_restricted_upload', 'h5p/h5p-editor', '1.25', TRUE, 7);
    $this->assertSame('Patch h5p_restricted_upload alterado: pacote h5p/h5p-editor 1.25, ativo, por usuário 7.', $message);
  }

  /**
   * Patch inativo é registrado como inativo.
   */
  public function testInactivePatchIsReported(): void {
    $message = PatchAuditMessage::format('excluído', 'p1', 'vendor/pkg', '2.0.0', FALSE, 0);
    $this->assertStringContainsString(', inativo, por usuário 0.', $message);
  }

  /**
   * Ação fora da lista é recusada para preservar o rótulo da auditoria.
   */
  public function testUnknownActionIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    PatchAuditMessage::format('apagado', 'p1', 'vendor/pkg', '1.0', TRUE, 1);
  }

  /**
   * O diff não entra na mensagem: a auditoria não copia conteúdo de código.
   */
  public function testMessageHasNoDiffContent(): void {
    $message = PatchAuditMessage::format('criado', 'p1', 'vendor/pkg', '1.0', TRUE, 1);
    $this->assertStringNotContainsString('@@', $message);
    $this->assertStringNotContainsString('+++', $message);
  }

}
