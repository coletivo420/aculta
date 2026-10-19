<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\H5PDrupal;

use H5PEventBase;


/**
 * Makes it easy to track events throughout the H5P system.
 *
 * @package    H5P
 * @copyright  2016 Joubel AS
 * @license    MIT
 */
class H5PEvent extends H5PEventBase {

  /**
   * Overrides H5PEventBase::save().
   *
   * Stores the event data in the database.
   */
  protected function save() {

    // Get data in array format without NULL values
    $data = $this->getDataArray();

    // Add user
    $data['user_id'] = H5PDrupal::getInstance()->getCurrentUserId();

    // Insert into DB
    $this->id = H5PDrupal::getInstance()->getDatabaseConnection()->insert('h5p_events')
      ->fields($data)
      ->execute();

    return $this->id;
  }

  /**
   * Overrides H5PEventBase::saveStats().
   *
   * Add current event data to statistics counter.
   */
  protected function saveStats() {
    $type = $this->type . ' ' . $this->sub_type;
    $database = H5PDrupal::getInstance()->getDatabaseConnection();

    // Verify if counter exists
    $current_num = $database->query("
        SELECT num
        FROM {h5p_counters}
        WHERE type = :type
        AND library_name = :library_name
        AND library_version = :library_version",
        [
          ':type' => $type,
          ':library_name' => $this->library_name,
          ':library_version' => $this->library_version
        ])->fetchField();

    if ($current_num === FALSE) {
      // Insert new counter
      $database->insert('h5p_counters')
        ->fields([
          'type' => $type,
          'library_name' => $this->library_name,
          'library_version' => $this->library_version,
          'num' => 1
        ])
        ->execute();
    }
    else {
      // Update counter with num+1
      $database->query("
        UPDATE {h5p_counters}
        SET num = num + 1
        WHERE type = :type
        AND library_name = :library_name
        AND library_version = :library_version",
        [
          ':type' => $type,
          ':library_name' => $this->library_name,
          ':library_version' => $this->library_version
        ]);
    }
  }
}
