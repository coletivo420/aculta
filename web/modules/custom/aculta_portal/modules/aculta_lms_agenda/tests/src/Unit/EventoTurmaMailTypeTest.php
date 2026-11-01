<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms_agenda\Unit;

use Drupal\aculta_lms_agenda\Mail\EventoTurmaMailType;
use Drupal\aculta_lms_agenda\Plugin\QueueWorker\EventoTurmaNotificationWorker;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Group event email variables, default text, and message context.
 */
#[Group('aculta_lms_agenda')]
class EventoTurmaMailTypeTest extends UnitTestCase {

  /**
   * Tradução que devolve o texto original.
   */
  private function tipo(): EventoTurmaMailType {
    $traducao = $this->createMock(TranslationInterface::class);
    $traducao->method('translate')->willReturnArgument(0);
    return new EventoTurmaMailType($traducao);
  }

  /**
   * Identificador snake_case e as três variáveis do modelo.
   */
  public function testIdAndVariables(): void {
    $tipo = $this->tipo();
    $this->assertSame('aculta_evento_turma', $tipo->id());
    $this->assertSame(['titulo', 'turma', 'url'], array_keys($tipo->variables()));
  }

  /**
   * O texto padrão usa só variáveis declaradas.
   */
  public function testTextoPadraoUsaSoVariaveisDeclaradas(): void {
    $tipo = $this->tipo();
    $usadas = [];
    preg_match_all('/\{\{ (\w+) \}\}/', $tipo->defaultSubject() . ' ' . $tipo->defaultBody(), $usadas);
    foreach (array_unique($usadas[1]) as $variavel) {
      $this->assertArrayHasKey($variavel, $tipo->variables(), "placeholder {$variavel} não declarado");
    }
  }

  /**
   * O contexto enviado tem exatamente as chaves do tipo.
   */
  public function testContextoTemAsChavesDoTipo(): void {
    $contexto = EventoTurmaNotificationWorker::contexto('Oficina', 'Turma A', 'https://exemplo.invalid/eventos/oficina');
    $this->assertSame(array_keys($this->tipo()->variables()), array_keys($contexto));
  }

}
