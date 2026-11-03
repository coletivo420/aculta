<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Event;

/**
 * Published Group courses and available ACULTA lessons or activities.
 */
final readonly class CourseContentReleasedEvent {

  public const NAME = self::class;

  /**
   * Maps supported entity types to the persisted Messenger event type.
   *
   * @var array<string, string>
   */
  public const NOTICE_TYPES = [
    'group' => 'course_release',
    'aculta_lms_lesson' => 'lesson_release',
    'aculta_lms_activity' => 'activity_release',
  ];

  public function __construct(
    public string $contentEntityType,
    public string $contentUuid,
    public string $publicationVersion,
  ) {
    if (!isset(self::NOTICE_TYPES[$contentEntityType]) || trim($contentUuid) === ''
      || !preg_match('/^[a-f0-9]{32}$/', $publicationVersion)) {
      throw new \InvalidArgumentException('A content release event requires a supported entity and opaque version.');
    }
  }

  /**
   * Returns the Messenger event type for this content entity.
   */
  public function noticeType(): string {
    return self::NOTICE_TYPES[$this->contentEntityType];
  }

}
