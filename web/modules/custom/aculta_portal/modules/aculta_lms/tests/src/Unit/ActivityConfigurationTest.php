<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\aculta_lms\Learning\ActivityConfiguration;
use Drupal\Tests\UnitTestCase;

/**
 * Tests activity configuration normalization.
 *
 * @coversDefaultClass \Drupal\aculta_lms\Learning\ActivityConfiguration
 */
final class ActivityConfigurationTest extends UnitTestCase {

  /**
   * @covers ::fromFormValues
   */
  public function testTrueFalseConfigurationUsesBooleanCorrectAnswer(): void {
    self::assertSame(
      ['question' => 'A planta é verde?', 'correct' => TRUE],
      ActivityConfiguration::fromFormValues(
        'true_false',
        ['question' => ' A planta é verde? ', 'correct_boolean' => 'true'],
      ),
    );
  }

  /**
   * @covers ::fromFormValues
   */
  public function testSelectConfigurationPreservesChoicesAndCorrectSet(): void {
    self::assertSame(
      ['question' => 'Escolha as respostas.', 'options' => ['A' => 'A', 'B' => 'B'], 'correct' => ['B']],
      ActivityConfiguration::fromFormValues('select', [
        'question' => 'Escolha as respostas.',
        'options' => "A\nB",
        'correct_values' => 'B',
      ]),
    );
  }

  /**
   * @covers ::fromFormValues
   */
  public function testFillBlanksSupportsAlternativeAcceptedAnswers(): void {
    self::assertSame(
      ['question' => 'Complete a frase.', 'correct' => [['cannabis', 'maconha'], ['Brasil']]],
      ActivityConfiguration::fromFormValues('fill_blanks', [
        'question' => 'Complete a frase.',
        'blank_answers' => "cannabis|maconha\nBrasil",
      ]),
    );
  }

  /**
   * @covers ::fromFormValues
   */
  public function testFreeTextConfigurationValidatesCharacterLimit(): void {
    self::assertSame(
      ['question' => 'Explique.', 'minimum_characters' => 120],
      ActivityConfiguration::fromFormValues('free_text', ['question' => 'Explique.', 'minimum_characters' => '120']),
    );
  }

  /**
   * @covers ::fromFormValues
   */
  public function testFileUploadStoresOnlyTheQuestion(): void {
    self::assertSame(
      ['question' => 'Envie uma imagem.'],
      ActivityConfiguration::fromFormValues('file_upload', ['question' => 'Envie uma imagem.']),
    );
  }

  /**
   * @covers ::fromFormValues
   */
  public function testInvalidConfigurationIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);
    ActivityConfiguration::fromFormValues('select', [
      'question' => 'Escolha.',
      'options' => "A\nA",
      'correct_values' => 'B',
    ]);
  }

}
