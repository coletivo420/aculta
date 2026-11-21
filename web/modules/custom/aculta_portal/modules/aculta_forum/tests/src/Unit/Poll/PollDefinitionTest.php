<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit\Poll;

use Drupal\aculta_forum\Poll\PollDefinition;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\aculta_forum\Poll\PollDefinition
 * @group aculta_forum
 */
final class PollDefinitionTest extends UnitTestCase {

  public function testValidSingleChoicePoll(): void {
    $poll = new PollDefinition(
      title: 'Qual sua cepa favorita?',
      options: ['Sativa', 'Indica', 'Híbrida'],
    );

    $this->assertSame('Qual sua cepa favorita?', $poll->title);
    $this->assertSame('single', $poll->pollType);
    $this->assertCount(3, $poll->options);
    $this->assertSame('after_vote', $poll->resultsVisibility);
  }

  public function testEmptyTitleThrowsException(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Poll title cannot be empty.');
    new PollDefinition(title: '   ', options: ['Opção 1', 'Opção 2']);
  }

  public function testLessThanTwoOptionsThrowsException(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('between 2 and 20 non-empty options');
    new PollDefinition(title: 'Pergunta?', options: ['Apenas uma']);
  }

  public function testValidMultipleChoicePoll(): void {
    $poll = new PollDefinition(
      title: 'Quais métodos você utiliza?',
      options: ['Vaporizador', 'Slick', 'Baseado', 'Comestível'],
      pollType: 'multiple',
      minChoices: 1,
      maxChoices: 3,
    );

    $this->assertSame('multiple', $poll->pollType);
    $this->assertSame(1, $poll->minChoices);
    $this->assertSame(3, $poll->maxChoices);
  }

  public function testInvalidChoiceLimitsThrowsException(): void {
    $this->expectException(\InvalidArgumentException::class);
    new PollDefinition(
      title: 'Pergunta?',
      options: ['A', 'B', 'C'],
      pollType: 'multiple',
      minChoices: 4,
      maxChoices: 2,
    );
  }

}
