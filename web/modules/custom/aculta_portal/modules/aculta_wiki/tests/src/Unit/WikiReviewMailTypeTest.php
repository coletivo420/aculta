<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_wiki\Unit;

use Drupal\aculta_mailer\Type\MailTypeInterface;
use Drupal\aculta_wiki\Type\WikiReviewMailType;
use Drupal\Core\StringTranslation\TranslationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Modelo de e-mail do aviso de revisão: identificador, variáveis e autoria.
 */
#[CoversClass(WikiReviewMailType::class)]
#[Group('aculta_wiki')]
final class WikiReviewMailTypeTest extends TestCase {

  /**
   * Identificador aceito pela fila do mailer e variáveis declaradas.
   */
  public function testExposesIdAndVariables(): void {
    $type = new WikiReviewMailType($this->translation());

    $this->assertInstanceOf(MailTypeInterface::class, $type);
    $this->assertSame('aculta_wiki_review', $type->id());
    $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{2,63}$/', $type->id());
    $this->assertSame(['verbete', 'estado', 'link'], array_keys($type->variables()));
  }

  /**
   * Assunto e corpo usam as variáveis declaradas.
   */
  public function testTextUsesDeclaredVariables(): void {
    $type = new WikiReviewMailType($this->translation());

    $this->assertStringContainsString('{{ verbete }}', $type->defaultSubject());
    foreach (['{{ verbete }}', '{{ estado }}', '{{ link }}'] as $placeholder) {
      $this->assertStringContainsString($placeholder, $type->defaultBody());
    }
  }

  /**
   * O aviso não cita autoria nem cria tópico: só título, estado e link.
   */
  public function testTextDoesNotMentionAuthorship(): void {
    $type = new WikiReviewMailType($this->translation());
    $text = $type->defaultSubject() . "\n" . $type->defaultBody();

    foreach (['autor', 'autora', 'user', 'uid'] as $word) {
      $this->assertStringNotContainsStringIgnoringCase($word, $text);
    }
  }

  /**
   * Tradução identidade, para o teste sem container.
   */
  private function translation(): TranslationInterface {
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn (string $text): object => new class($text) {

      public function __construct(private readonly string $text) {}

      /**
       * Texto traduzido.
       */
      public function __toString(): string {
        return $this->text;
      }

    });
    return $translation;
  }

}
