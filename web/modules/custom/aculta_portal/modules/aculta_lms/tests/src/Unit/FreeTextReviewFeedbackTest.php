<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Plugin\ActivityAnswer\FreeTextReviewFeedback;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Feedback formativo de respostas dissertativas baseado em frases-chave.
 */
#[CoversClass(FreeTextReviewFeedback::class)]
#[Group('aculta_lms')]
final class FreeTextReviewFeedbackTest extends UnitTestCase {

  /**
   * Confirma que as frases encontradas geram feedback formativo sem nota final.
   */
  public function testPhraseMatchesAreFormativeAndDoNotAssignFinalGrade(): void {
    $plugin = new FreeTextReviewFeedback();
    $configuration = $plugin->decodeConfiguration(json_encode([
      'minimum_characters' => 8,
      'phrase_feedback' => [
        [
          'phrase' => 'direitos humanos|direito humano',
          'feedback_present' => 'Bom ponto.',
          'feedback_absent' => 'Revise direitos.',
        ],
        [
          'phrase' => 'política pública',
          'feedback_present' => 'Considere políticas.',
          'feedback_absent' => 'Fale sobre políticas.',
        ],
      ],
    ], JSON_THROW_ON_ERROR));

    self::assertSame('free_text', $plugin->activityType());
    self::assertSame(50.0, $plugin->formativeScore('Acesso aos direitos humanos.', $configuration));
    self::assertSame('A resposta precisa ter pelo menos 8 caracteres; no momento tem 1.', $plugin->validate(' x ', $configuration));
    self::assertArrayHasKey('matched', $plugin->formativeFeedback('Acesso aos direitos humanos.', $configuration));
    self::assertArrayHasKey('unmatched', $plugin->formativeFeedback('Acesso aos direitos humanos.', $configuration));
  }

  /**
   * Confirma que uma lista de frases vazia não gera nota nem feedback automático.
   */
  public function testEmptyPhraseListProducesNoAutomaticGradeOrFeedback(): void {
    $plugin = new FreeTextReviewFeedback();
    $configuration = $plugin->decodeConfiguration('{"phrase_feedback":[]}');

    self::assertSame(100.0, $plugin->formativeScore('qualquer texto', $configuration));
    self::assertSame([], $plugin->formativeFeedback('qualquer texto', $configuration));
    self::assertNull($plugin->validate('texto', $configuration));
  }

  /**
   * Confirma que configurações de plugin malformadas são rejeitadas.
   */
  public function testRejectsMalformedPluginConfiguration(): void {
    $this->expectException(\InvalidArgumentException::class);
    (new FreeTextReviewFeedback())->decodeConfiguration('[]');
  }

}
