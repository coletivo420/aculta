<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Poll;

/**
 * Encapsulates the configuration and options for creating a native Forum poll.
 */
final class PollDefinition {

  /**
   * @param string $title
   *   The question/title of the poll.
   * @param list<string> $options
   *   Options array (2 to 20 options).
   * @param string $pollType
   *   'single' or 'multiple'.
   * @param int $minChoices
   *   Minimum required choices for multiple choice.
   * @param int $maxChoices
   *   Maximum allowed choices for multiple choice.
   * @param string $resultsVisibility
   *   'always', 'after_vote', or 'after_close'.
   * @param int|null $closeAt
   *   Optional timestamp when the poll automatically closes.
   */
  public function __construct(
    public readonly string $title,
    public readonly array $options,
    public readonly string $pollType = 'single',
    public readonly int $minChoices = 1,
    public readonly int $maxChoices = 1,
    public readonly string $resultsVisibility = 'after_vote',
    public readonly ?int $closeAt = NULL,
  ) {
    if (trim($this->title) === '') {
      throw new \InvalidArgumentException('Poll title cannot be empty.');
    }
    $filteredOptions = array_values(array_filter(array_map('trim', $this->options)));
    if (count($filteredOptions) < 2 || count($filteredOptions) > 20) {
      throw new \InvalidArgumentException('A poll must have between 2 and 20 non-empty options.');
    }
    if (!in_array($this->pollType, ['single', 'multiple'], TRUE)) {
      throw new \InvalidArgumentException("Invalid poll type: {$this->pollType}. Must be 'single' or 'multiple'.");
    }
    if (!in_array($this->resultsVisibility, ['always', 'after_vote', 'after_close'], TRUE)) {
      throw new \InvalidArgumentException("Invalid results visibility: {$this->resultsVisibility}.");
    }
    if ($this->pollType === 'multiple') {
      if ($this->minChoices < 1 || $this->minChoices > count($filteredOptions)) {
        throw new \InvalidArgumentException('Invalid minChoices for multiple choice poll.');
      }
      if ($this->maxChoices < $this->minChoices || $this->maxChoices > count($filteredOptions)) {
        throw new \InvalidArgumentException('Invalid maxChoices for multiple choice poll.');
      }
    }
  }

}
