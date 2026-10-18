<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Wraps a node insertion demo event for event listeners.
 */
class FinishedEvent extends Event {

  const FINISHED_EVENT = 'h5p.finished';

  /**
   * @var array
   */
  protected $quizData;

  /**
   * Constructs a FinishedEvent object.
   *
   * @param array $quiz_data
   *   Database connection service.
   */
  public function __construct(array $quiz_data) {
    $this->quizData = $quiz_data;
  }

  /**
   * @return array
   *   Quiz data.
   */
  public function getQuizFields() {
    return $this->quizData;
  }

}
