<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Unit;

use Drupal\aculta_messenger\Aviso\AvisosResumo;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\Mail\AvisosResumoMailType;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Texto do resumo de avisos: assunto, linhas por tópico e rodapé.
 */
#[CoversClass(AvisosResumoMailType::class)]
#[Group('aculta_messenger')]
final class AvisosResumoMailTypeTest extends UnitTestCase {

  /**
   * O tipo usa a chave de envio do resumo e lista cada tópico com o rótulo do tipo.
   */
  public function testBodyListsEachTopicWithItsLabel(): void {
    $type = new AvisosResumoMailType($this->getStringTranslationStub());
    $context = ['linhas' => [
      ['tipo' => Aviso::TIPO_RESPOSTA, 'titulo' => 'Tópico A', 'quantidade' => 2],
      ['tipo' => Aviso::TIPO_ATUALIZACAO, 'titulo' => 'Tópico B', 'quantidade' => 1],
    ]];

    $this->assertSame(AvisosResumo::CHAVE_MAIL, $type->id());
    $this->assertSame('Resumo de avisos do Fórum', $type->subject($context));
    $body = $type->body($context);
    $this->assertStringContainsString('Respostas novas em "Tópico A": 2', $body);
    $this->assertStringContainsString('Atualizações em "Tópico B": 1', $body);
    $this->assertStringContainsString('Minha conta > Privacidade', $body);
  }

}
