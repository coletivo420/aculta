<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\Answer;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\aculta_lms\Plugin\ActivityAnswer\FreeTextReviewFeedback;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies formative feedback never becomes the final open-answer grade.
 */
#[Group('aculta_lms')]
final class FreeTextReviewFeedbackTest extends UnitTestCase {

  /**
   * Formative phrase matches never count as an official grade.
   */
  public function testMatchedPhraseIsFormativeAndRequiresEducatorReview(): void {
    $answer = $this->buildAnswer('A política deve respeitar os direitos humanos.', 'direitos humanos');
    $plugin = new FreeTextReviewFeedback([], 'free_text_review_feedback', []);

    self::assertSame(1.0, $plugin->getScore($answer));
    self::assertFalse($plugin->evaluatedOnSave($answer));
  }

  /**
   * Missing phrase matches keep the formative score at zero.
   */
  public function testUnmatchedPhraseOnlyAffectsFormativeScore(): void {
    $answer = $this->buildAnswer('Resposta sem a expressão configurada.', 'direitos humanos');
    $plugin = new FreeTextReviewFeedback([], 'free_text_review_feedback', []);

    self::assertSame(0.0, $plugin->getScore($answer));
    self::assertFalse($plugin->evaluatedOnSave($answer));
  }

  /**
   * Missing activity references fail closed in score and evaluation display.
   */
  public function testRemovedActivityDoesNotCallTypedGetter(): void {
    $answer = $this->buildAnswer('Resposta existente.', 'direitos humanos', FALSE);
    $answer->expects(self::never())->method('getActivity');
    $plugin = new FreeTextReviewFeedback([], 'free_text_review_feedback', []);
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translate')->willReturnCallback(static fn(string $string): string => $string);
    $translation->method('translateString')->willReturn('Atividade não disponível');
    $plugin->setStringTranslation($translation);

    self::assertSame(0.0, $plugin->getScore($answer));
    self::assertSame(
      'Atividade não disponível',
      (string) $plugin->evaluationDisplay($answer)['activity']['#plain_text'],
    );
  }

  /**
   * Keeps a plain response form when the referenced activity is gone.
   */
  public function testAnsweringFormSkipsFeedbackWhenActivityWasRemoved(): void {
    $answer = $this->buildAnswer('Resposta existente.', 'direitos humanos', FALSE);
    $answer->expects(self::never())->method('getActivity');
    $plugin = new FreeTextReviewFeedback([], 'free_text_review_feedback', []);
    $translation = $this->createMock(TranslationInterface::class);
    $plugin->setStringTranslation($translation);
    $form = ['actions' => ['submit' => []]];

    $plugin->answeringForm($form, $this->createMock(FormStateInterface::class), $answer);

    self::assertSame('textarea', $form['answer']['#type']);
    self::assertSame(0, $form['answer']['#minimum_characters']);
    self::assertArrayNotHasKey('answer_feedback_wrapper', $form);
  }

  /**
   * Builds a response for a configured feedback activity.
   */
  private function buildAnswer(string $text, string $configured_phrase, bool $include_activity = TRUE): Answer {
    $phrase_value = $this->createMock(FieldItemInterface::class);
    $phrase_value->method('getString')->willReturn($configured_phrase);
    $phrase = $this->createMock(FieldItemInterface::class);
    $phrase->method('get')->with('phrase')->willReturn($phrase_value);
    $phrases = new class($phrase) extends FieldItemList {

      public function __construct(FieldItemInterface $phrase) {
        parent::__construct(DataDefinition::create('field_item_list'));
        $this->list = [$phrase];
      }

    };

    $activity = NULL;
    if ($include_activity) {
      $activity = $this->createMock(ActivityInterface::class);
      $activity->method('get')->with('phrase_feedback')->willReturn($phrases);
    }
    $answer = $this->createMock(Answer::class);
    $answer->method('get')->with(AnswerInterface::ACTIVITY_FIELD)->willReturn(new class($activity) extends FieldItemList {

      public function __construct(private readonly ?ActivityInterface $activity) {}

      /**
       * Returns the synthetic activity reference for the test.
       */
      public function __get($property_name) {
        if ($property_name === 'entity') {
          return $this->activity;
        }
        return parent::__get($property_name);
      }

    });
    $answer->method('getData')->willReturn(['answer' => $text]);

    return $answer;
  }

}
