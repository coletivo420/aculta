<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Template\MailTemplateRenderer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Placeholders de modelo: só variáveis declaradas são substituídas; o resto é detectado.
 */
#[CoversClass(MailTemplateRenderer::class)]
#[Group('aculta_mailer')]
final class MailTemplateRendererTest extends UnitTestCase {

  /**
   * Substitui variáveis escalares e troca ausentes por vazio, sem executar código.
   */
  public function testFillSubstitutesScalarsAndBlanksMissing(): void {
    $text = 'Olá {{ name }}, veja {{link}}. {{ ausente }}!';
    $context = ['name' => 'Maria', 'link' => 'https://x.invalid'];
    $this->assertSame('Olá Maria, veja https://x.invalid. !', MailTemplateRenderer::fill($text, $context));
  }

  /**
   * Arrays e objetos no contexto nunca viram texto.
   */
  public function testFillIgnoresNonScalarValues(): void {
    $this->assertSame(' fim', MailTemplateRenderer::fill('{{ linhas }} fim', ['linhas' => ['a']]));
  }

  /**
   * Placeholders que o tipo não declara são listados, sem repetição.
   */
  public function testUnknownPlaceholdersAreReported(): void {
    $found = MailTemplateRenderer::unknownPlaceholders('{{ name }} {{ senha }} {{ senha }} {{ token }}', ['name']);
    $this->assertSame(['senha', 'token'], $found);
  }

}
