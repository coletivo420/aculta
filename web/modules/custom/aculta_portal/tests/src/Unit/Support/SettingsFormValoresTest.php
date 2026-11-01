<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_portal\Unit\Support;

use Drupal\aculta_portal\Support\Form\SettingsForm;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Parser dos valores padrão de Formas de apoio: inteiros positivos, sem repetição, na ordem informada.
 */
#[Group('aculta_portal')]
final class SettingsFormValoresTest extends UnitTestCase {

  public function testListaValidaMantemOrdemSemRepeticao(): void {
    $this->assertSame([5, 10, 20, 50, 100], SettingsForm::valoresDoTexto('5, 10, 20, 50, 100'));
    $this->assertSame([50, 5], SettingsForm::valoresDoTexto('50,5,50'));
  }

  public function testTextoInvalidoOuVazioNaoEhLista(): void {
    $this->assertNull(SettingsForm::valoresDoTexto(''));
    $this->assertNull(SettingsForm::valoresDoTexto(' , '));
    $this->assertNull(SettingsForm::valoresDoTexto('5, abc'));
    $this->assertNull(SettingsForm::valoresDoTexto('0, 10'));
    $this->assertNull(SettingsForm::valoresDoTexto('-5'));
    $this->assertNull(SettingsForm::valoresDoTexto('5,5.5'));
  }

}
