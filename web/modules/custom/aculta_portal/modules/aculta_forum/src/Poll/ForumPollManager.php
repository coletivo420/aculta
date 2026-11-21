<?php

declare(strict_types=1);

namespace Drupal\aculta_forum\Poll;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages lifecycle, votes and calculations for native ACULTA Forum polls.
 */
final class ForumPollManager {

  public const TABLE_POLL = 'aculta_forum_poll';
  public const TABLE_OPTION = 'aculta_forum_poll_option';
  public const TABLE_VOTE = 'aculta_forum_poll_vote';

  public function __construct(
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
    #[Autowire(service: 'logger.channel.aculta_forum')]
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Creates a native poll attached to a topic node.
   *
   * @return int
   *   Created poll ID.
   */
  public function createPoll(NodeInterface $topic, PollDefinition $definition): int {
    $nid = (int) $topic->id();
    if ($nid <= 0) {
      throw new \InvalidArgumentException('Topic node must be saved before attaching a poll.');
    }

    $existing = $this->getPollByTopicId($nid);
    if ($existing !== NULL) {
      $existingId = is_scalar($existing['id'] ?? NULL) ? (string) $existing['id'] : '';
      throw new \LogicException("Topic {$nid} already has a native poll (#{$existingId}).");
    }

    $transaction = $this->database->startTransaction();
    try {
      $pollId = (int) $this->database->insert(self::TABLE_POLL)
        ->fields([
          'nid' => $nid,
          'title' => $definition->title,
          'poll_type' => $definition->pollType,
          'min_choices' => $definition->minChoices,
          'max_choices' => $definition->maxChoices,
          'results_visibility' => $definition->resultsVisibility,
          'status' => 'open',
          'close_at' => $definition->closeAt,
          'created' => $this->time->getRequestTime(),
        ])
        ->execute();

      $weight = 0;
      foreach ($definition->options as $label) {
        $trimmed = trim($label);
        if ($trimmed === '') {
          continue;
        }
        $this->database->insert(self::TABLE_OPTION)
          ->fields([
            'poll_id' => $pollId,
            'label' => $trimmed,
            'weight' => $weight++,
          ])
          ->execute();
      }

      $this->logger->info('Poll #@id created for topic @nid: @title', [
        '@id' => (string) $pollId,
        '@nid' => (string) $nid,
        '@title' => $definition->title,
      ]);

      return $pollId;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      $this->logger->error('Failed to create poll for topic @nid: @message', [
        '@nid' => (string) $nid,
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

  /**
   * Retrieves poll metadata by topic node ID.
   *
   * @return array<string, mixed>|null
   */
  public function getPollByTopicId(int $nid): ?array {
    $statement = $this->database->select(self::TABLE_POLL, 'p')
      ->fields('p')
      ->condition('nid', $nid)
      ->execute();

    if ($statement === NULL) {
      return NULL;
    }

    $row = $statement->fetchAssoc();
    /** @var array<string, mixed>|null $result */
    $result = is_array($row) ? $row : NULL;
    return $result;
  }

  /**
   * Retrieves poll metadata by poll ID.
   *
   * @return array<string, mixed>|null
   */
  public function getPoll(int $pollId): ?array {
    $statement = $this->database->select(self::TABLE_POLL, 'p')
      ->fields('p')
      ->condition('id', $pollId)
      ->execute();

    if ($statement === NULL) {
      return NULL;
    }

    $row = $statement->fetchAssoc();
    /** @var array<string, mixed>|null $result */
    $result = is_array($row) ? $row : NULL;
    return $result;
  }

  /**
   * Retrieves all options for a poll, ordered by weight.
   *
   * @return list<array{id: int, poll_id: int, label: string, weight: int}>
   */
  public function getOptions(int $pollId): array {
    $statement = $this->database->select(self::TABLE_OPTION, 'o')
      ->fields('o')
      ->condition('poll_id', $pollId)
      ->orderBy('weight', 'ASC')
      ->execute();

    if ($statement === NULL) {
      return [];
    }

    $results = [];
    /** @var list<object{id: int|string, poll_id: int|string, label: string, weight: int|string}> $rows */
    $rows = $statement->fetchAll();
    foreach ($rows as $row) {
      $results[] = [
        'id' => (int) $row->id,
        'poll_id' => (int) $row->poll_id,
        'label' => (string) $row->label,
        'weight' => (int) $row->weight,
      ];
    }
    return $results;
  }

  /**
   * Checks whether an account has already voted in this poll.
   */
  public function hasUserVoted(int $pollId, int $uid): bool {
    if ($uid <= 0) {
      return FALSE;
    }
    $statement = $this->database->select(self::TABLE_VOTE, 'v')
      ->condition('poll_id', $pollId)
      ->condition('uid', $uid)
      ->countQuery()
      ->execute();

    if ($statement === NULL) {
      return FALSE;
    }

    $val = $statement->fetchField();
    return is_numeric($val) && (int) $val > 0;
  }

  /**
   * Retrieves the option IDs selected by an account.
   *
   * @return list<int>
   */
  public function getUserVotes(int $pollId, int $uid): array {
    if ($uid <= 0) {
      return [];
    }
    $statement = $this->database->select(self::TABLE_VOTE, 'v')
      ->fields('v', ['option_id'])
      ->condition('poll_id', $pollId)
      ->condition('uid', $uid)
      ->execute();

    if ($statement === NULL) {
      return [];
    }

    $ids = [];
    /** @var list<object{option_id: int|string}> $rows */
    $rows = $statement->fetchAll();
    foreach ($rows as $row) {
      $ids[] = (int) $row->option_id;
    }
    return $ids;
  }

  /**
   * Casts a ballot in a poll.
   *
   * @param list<int> $selectedOptionIds
   */
  public function castVote(int $pollId, AccountInterface $account, array $selectedOptionIds): void {
    $uid = (int) $account->id();
    if ($uid <= 0) {
      throw new \InvalidArgumentException('Only authenticated members can vote in Forum polls.');
    }

    $poll = $this->getPoll($pollId);
    if ($poll === NULL) {
      throw new \InvalidArgumentException("Poll {$pollId} not found.");
    }

    $currentTime = $this->time->getRequestTime();
    $status = is_scalar($poll['status'] ?? NULL) ? (string) $poll['status'] : '';
    if ($status !== 'open') {
      throw new \LogicException('This poll is closed.');
    }

    $closeAt = $poll['close_at'] ?? NULL;
    if ($closeAt !== NULL && is_numeric($closeAt) && (int) $closeAt <= $currentTime) {
      $this->closePoll($pollId, NULL);
      throw new \LogicException('This poll has reached its closing deadline.');
    }

    $lockKey = "aculta_forum_poll_vote:{$pollId}:{$uid}";
    if (!$this->lock->acquire($lockKey, 5.0)) {
      throw new \RuntimeException('Concurrent vote detected. Please wait.');
    }

    try {
      if ($this->hasUserVoted($pollId, $uid)) {
        throw new \LogicException('You have already voted in this poll.');
      }

      $validOptions = $this->getOptions($pollId);
      $validOptionIds = array_column($validOptions, 'id');
      $cleanSelection = array_values(array_unique(array_filter(array_map('intval', $selectedOptionIds))));

      if (empty($cleanSelection)) {
        throw new \InvalidArgumentException('Please select at least one option.');
      }

      foreach ($cleanSelection as $optId) {
        if (!in_array($optId, $validOptionIds, TRUE)) {
          throw new \InvalidArgumentException("Invalid option {$optId} for poll {$pollId}.");
        }
      }

      $pollType = is_scalar($poll['poll_type'] ?? NULL) ? (string) $poll['poll_type'] : 'single';
      $countSelected = count($cleanSelection);

      if ($pollType === 'single' && $countSelected !== 1) {
        throw new \InvalidArgumentException('Single choice polls require exactly one option selected.');
      }
      if ($pollType === 'multiple') {
        $min = is_numeric($poll['min_choices'] ?? NULL) ? (int) $poll['min_choices'] : 1;
        $max = is_numeric($poll['max_choices'] ?? NULL) ? (int) $poll['max_choices'] : 1;
        if ($countSelected < $min || $countSelected > $max) {
          throw new \InvalidArgumentException("Multiple choice poll requires between {$min} and {$max} choices.");
        }
      }

      $transaction = $this->database->startTransaction();
      try {
        foreach ($cleanSelection as $optId) {
          $this->database->insert(self::TABLE_VOTE)
            ->fields([
              'poll_id' => $pollId,
              'option_id' => $optId,
              'uid' => $uid,
              'created' => $currentTime,
            ])
            ->execute();
        }
        $this->logger->notice('Ballot recorded for poll #@id by uid @uid (@count options)', [
          '@id' => (string) $pollId,
          '@uid' => (string) $uid,
          '@count' => (string) $countSelected,
        ]);
      }
      catch (\Throwable $e) {
        $transaction->rollBack();
        throw $e;
      }
    }
    finally {
      $this->lock->release($lockKey);
    }
  }

  /**
   * Closes a poll manually or automatically.
   */
  public function closePoll(int $pollId, ?AccountInterface $closedBy = NULL): void {
    $poll = $this->getPoll($pollId);
    $status = is_scalar($poll['status'] ?? NULL) ? (string) $poll['status'] : '';
    if ($poll === NULL || $status === 'closed') {
      return;
    }

    $this->database->update(self::TABLE_POLL)
      ->fields([
        'status' => 'closed',
        'closed_by' => $closedBy ? (int) $closedBy->id() : NULL,
        'closed_at' => $this->time->getRequestTime(),
      ])
      ->condition('id', $pollId)
      ->execute();

    $this->logger->info('Poll #@id closed by uid @uid', [
      '@id' => (string) $pollId,
      '@uid' => $closedBy ? (string) $closedBy->id() : 'system',
    ]);
  }

  /**
   * Computes poll results respecting user access and visibility policy.
   */
  public function getResults(int $pollId, AccountInterface $viewer): ?PollResults {
    $poll = $this->getPoll($pollId);
    if ($poll === NULL) {
      return NULL;
    }

    $uid = (int) $viewer->id();
    $hasVoted = $this->hasUserVoted($pollId, $uid);
    $userVotes = $this->getUserVotes($pollId, $uid);
    $status = is_scalar($poll['status'] ?? NULL) ? (string) $poll['status'] : 'open';
    $visibility = is_scalar($poll['results_visibility'] ?? NULL) ? (string) $poll['results_visibility'] : 'after_vote';
    $isOpen = ($status === 'open');

    // Determine results visibility.
    $resultsVisible = match ($visibility) {
      'always' => TRUE,
      'after_vote' => $hasVoted || !$isOpen,
      'after_close' => !$isOpen,
      default => $hasVoted,
    };

    // Calculate votes.
    $options = $this->getOptions($pollId);

    // Total unique voters.
    $votersStmt = $this->database->query(
      'SELECT COUNT(DISTINCT uid) FROM {' . self::TABLE_VOTE . '} WHERE poll_id = :pid',
      [':pid' => $pollId],
    );
    $votersVal = $votersStmt !== NULL ? $votersStmt->fetchField() : 0;
    $totalVoters = is_numeric($votersVal) ? (int) $votersVal : 0;

    // Total selections.
    $votesStmt = $this->database->select(self::TABLE_VOTE, 'v')
      ->condition('poll_id', $pollId)
      ->countQuery()
      ->execute();
    $votesVal = $votesStmt !== NULL ? $votesStmt->fetchField() : 0;
    $totalVotes = is_numeric($votesVal) ? (int) $votesVal : 0;

    $optionVotesQuery = $this->database->query(
      'SELECT option_id, COUNT(*) as vote_count FROM {' . self::TABLE_VOTE . '} WHERE poll_id = :pid GROUP BY option_id',
      [':pid' => $pollId],
    );
    $votesByOption = [];
    if ($optionVotesQuery !== NULL) {
      /** @var list<object{option_id: int|string, vote_count: int|string}> $optRows */
      $optRows = $optionVotesQuery->fetchAll();
      foreach ($optRows as $row) {
        $votesByOption[(int) $row->option_id] = (int) $row->vote_count;
      }
    }

    $compiledOptions = [];
    foreach ($options as $opt) {
      $optId = $opt['id'];
      $votes = $votesByOption[$optId] ?? 0;
      $denom = $totalVoters > 0 ? $totalVoters : 1;
      $percentage = $totalVoters > 0 ? round(($votes / $denom) * 100, 1) : 0.0;

      $compiledOptions[] = [
        'id' => $optId,
        'label' => $opt['label'],
        'votes' => $votes,
        'percentage' => $percentage,
      ];
    }

    $canVote = $isOpen && !$hasVoted && $viewer->isAuthenticated();

    return new PollResults(
      pollId: $pollId,
      title: is_scalar($poll['title'] ?? NULL) ? (string) $poll['title'] : '',
      pollType: is_scalar($poll['poll_type'] ?? NULL) ? (string) $poll['poll_type'] : 'single',
      status: $status,
      totalVoters: $totalVoters,
      totalVotes: $totalVotes,
      options: $compiledOptions,
      canVote: $canVote,
      hasVoted: $hasVoted,
      userSelectedOptionIds: $userVotes,
      resultsVisible: $resultsVisible,
    );
  }

}
