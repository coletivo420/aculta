<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_lms\Unit;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\Answer;
use Drupal\aculta_lms\Plugin\ActivityAnswer\FreeTextReviewFeedback;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies formative feedback never becomes the final open-answer grade.
 */
#[Group('aculta_lms')]
final class FreeTextReviewFeedbackTest extends UnitTestCase {

  public function testMatchedPhraseIsFormativeAndRequiresEducatorReview(): void {
    $answer = $this->buildAnswer('A política deve respeitar os direitos humanos.', 'direitos humanos');
    $plugin = new FreeTextReviewFeedback([], 'free_text_review_feedback', []);

    self::assertSame(1.0, $plugin->getScore($answer));
    self::assertFalse($plugin->evaluatedOnSave($answer));
  }

  public function testUnmatchedPhraseOnlyAffectsFormativeScore(): void {
    $answer = $this->buildAnswer('Resposta sem a expressão configurada.', 'direitos humanos');
    $plugin = new FreeTextReviewFeedback([], 'free_text_review_feedback', []);

    self::assertSame(0.0, $plugin->getScore($answer));
    self::assertFalse($plugin->evaluatedOnSave($answer));
  }

  private function buildAnswer(string $text, string $configured_phrase): Answer {
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

    $activity = $this->createMock(ActivityInterface::class);
    $activity->method('get')->with('phrase_feedback')->willReturn($phrases);
    $answer = $this->createMock(Answer::class);
    $answer->method('getActivity')->willReturn($activity);
    $answer->method('getData')->willReturn(['answer' => $text]);

    return $answer;
  }

}
