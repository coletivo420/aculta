<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_mailer\Unit;

use Drupal\aculta_mailer\Entity\MailTemplate;
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
   * Em corpo HTML, o valor do usuário é escapado e não vira marcação.
   */
  public function testFillEscapesValuesInHtmlBodies(): void {
    $out = MailTemplateRenderer::fill('Olá {{ nome }}', ['nome' => '<img src=x onerror=alert(1)>'], TRUE);
    $this->assertSame('Olá &lt;img src=x onerror=alert(1)&gt;', $out);
  }

  /**
   * Aspas e comercial também são escapados em HTML.
   */
  public function testFillEscapesQuotesAndAmpersandInHtml(): void {
    $this->assertSame('A &amp; &quot;B&quot; &#039;C&#039;', MailTemplateRenderer::fill('{{ v }}', ['v' => 'A & "B" \'C\''], TRUE));
  }

  /**
   * O HTML fixo do modelo permanece intacto; só o valor é escapado.
   */
  public function testFixedHtmlIsNotEscaped(): void {
    $this->assertSame('<p><strong>x</strong></p>', MailTemplateRenderer::fill('<p><strong>{{ n }}</strong></p>', ['n' => 'x'], TRUE));
  }

  /**
   * Sem o modo HTML, o texto simples continua como antes (sem escape).
   */
  public function testPlainTextIsNotEscapedByDefault(): void {
    $this->assertSame('A & <b>', MailTemplateRenderer::fill('{{ v }}', ['v' => 'A & <b>']));
  }

  /**
   * Modelo novo é texto simples até ser salvo pelo editor HTML.
   */
  public function testTemplateDefaultsToPlainTextBody(): void {
    $template = new MailTemplate([], MailTemplate::ENTITY_TYPE);
    $this->assertFalse($template->isHtmlBody());
    $template->setBodyFormat(MailTemplate::BODY_FORMAT_HTML);
    $this->assertTrue($template->isHtmlBody());
  }

  /**
   * Placeholders que o tipo não declara são listados, sem repetição.
   */
  public function testUnknownPlaceholdersAreReported(): void {
    $found = MailTemplateRenderer::unknownPlaceholders('{{ name }} {{ senha }} {{ senha }} {{ token }}', ['name']);
    $this->assertSame(['senha', 'token'], $found);
  }

}
