<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Poll;

/**
 * Value object representing real-time results of a Forum poll.
 */
final class PollResults {

  /**
   * @param int $pollId
   * @param string $title
   * @param string $pollType
   * @param string $status
   * @param int $totalVoters
   *   Total unique accounts who submitted a ballot.
   * @param int $totalVotes
   *   Total option selections recorded across all ballots.
   * @param list<array{id: int, label: string, votes: int, percentage: float}> $options
   * @param bool $canVote
   * @param bool $hasVoted
   * @param list<int> $userSelectedOptionIds
   * @param bool $resultsVisible
   */
  public function __construct(
    public readonly int $pollId,
    public readonly string $title,
    public readonly string $pollType,
    public readonly string $status,
    public readonly int $totalVoters,
    public readonly int $totalVotes,
    public readonly array $options,
    public readonly bool $canVote,
    public readonly bool $hasVoted,
    public readonly array $userSelectedOptionIds,
    public readonly bool $resultsVisible,
    public readonly ?int $closeAt = NULL,
  ) {}

}
