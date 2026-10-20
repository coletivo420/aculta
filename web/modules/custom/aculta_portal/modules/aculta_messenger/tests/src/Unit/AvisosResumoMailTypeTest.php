<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\Mail\AvisosResumoMailType;
use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Texto do resumo de avisos: linhas formatadas pelo tipo e texto padrão com a variável `resumo`.
 */
#[CoversClass(AvisosResumoMailType::class)]
#[Group('aculta_messenger')]
final class AvisosResumoMailTypeTest extends UnitTestCase {

  /**
   * Cada tópico sai com o rótulo do tipo, e o corpo padrão recebe as linhas.
   */
  public function testSummaryLinesAndDefaultBody(): void {
    $type = new AvisosResumoMailType($this->getStringTranslationStub());
    $linhas = AvisosResumoMailType::formatLinhas([
      ['tipo' => Aviso::TIPO_RESPOSTA, 'titulo' => 'Tópico A', 'quantidade' => 2],
      ['tipo' => Aviso::TIPO_ATUALIZACAO, 'titulo' => 'Tópico B', 'quantidade' => 1],
    ]);

    $this->assertSame(AvisosResumo::CHAVE_MAIL, $type->id());
    $this->assertSame('Resumo de avisos do Fórum', $type->defaultSubject());
    $this->assertSame([], MailTemplateRenderer::unknownPlaceholders($type->defaultBody(), array_keys($type->variables())));
    $body = MailTemplateRenderer::fill($type->defaultBody(), ['resumo' => $linhas]);
    $this->assertStringContainsString('Respostas novas em "Tópico A": 2', $body);
    $this->assertStringContainsString('Atualizações em "Tópico B": 1', $body);
    $this->assertStringContainsString('Minha conta > Privacidade', $body);
  }

}
