<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Carries a completed H5P activity result to event listeners.
 */
class aculta_H5PFinishedEvent extends Event {

  const FINISHED_EVENT = 'h5p.finished';

  /**
   * @var array
   */
  protected $quizData;

  /**
   * Constructs an aculta_H5PFinishedEvent object.
   *
   * @param array $quiz_data
   *   H5P completion data.
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
