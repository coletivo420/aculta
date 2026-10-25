<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms;

use Drupal\aculta_lms\Event\CourseContentReleasedEvent;
use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\comment\CommentInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\lms\Entity\LessonInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\lms\Entity\LessonStatusInterface;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestSelectiveDiscardInterface;
use Drupal\aculta_messenger\Event\MessengerNoticeEvent;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_messenger\Service\NoticeRelatedGrouper;
use Drupal\aculta_messenger\Service\MessengerNoticePublisherInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Psr\Log\LoggerInterface;

/**
 * Persists and presents LMS notices using Messenger's Message entities.
 */
final class LearningNoticeManager implements LearningNoticeManagerInterface, AvisoDigestProviderIdentityInterface, AvisoDigestSelectiveDiscardInterface {

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_lms_notice';

  private const PERSONAL_NOTICE_LIMIT = 10;

  private const EVENT_FIELD = 'field_aculta_lms_event_type';

  private const SOURCE_FIELD = 'field_aculta_lms_source_uuid';
  private const EVENT_VERSION_FIELD = 'field_aculta_lms_event_version';

  /**
   * {@inheritdoc}
   */
  public function digestProviderId(): string {
    return 'lms';
  }

  private const READ_FIELD = 'field_aculta_lms_read';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const DIGEST_FIELD = 'field_aculta_lms_digest_sent';

  private const DIGEST_TYPE = 'aprendizagem';

  /**
   * Event types that participate in the daily digest.
   *
   * @var string[]
   */
  private const DIGEST_EVENTS = [
    'enrollment',
    'certificate',
    'completion',
    'evaluation',
    'submission',
    'course_release',
    'lesson_release',
    'activity_release',
    'feedback',
    'deadline',
    'deadline_reminder',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly ClassActivityDeadlineManager $deadlines,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly QueueFactory $queueFactory,
    private readonly LoggerInterface $logger,
    private readonly MessengerNoticePublisherInterface $noticePublisher,
    private readonly LockBackendInterface $lock,
  ) {}

  /**
   * Creates one durable notice per source and recipient; enqueue email once.
   */
  public function record(string $sourceUuid, string $type, int $uid, bool $sendEmail, string $eventVersion = '1', bool $includeInDigest = TRUE): ?MessageInterface {
    if (!in_array($type, [
      'enrollment',
      'certificate',
      'completion',
      'evaluation',
      'submission',
      'course_release',
      'lesson_release',
      'activity_release',
      'feedback',
      'deadline',
      'deadline_reminder',
    ], TRUE)) {
      return NULL;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return NULL;
    }
    $lockId = 'aculta_messenger:lms_notice:' . hash('sha256', implode("\0", [
      (string) $uid,
      $type,
      $sourceUuid,
      $eventVersion,
    ]));
    if (!$this->lock->acquire($lockId, 30.0)) {
      throw new \RuntimeException('LMS notice is currently being recorded.');
    }
    try {
      $storage = $this->entityTypeManager->getStorage('message');
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $uid)
        ->condition(self::EVENT_FIELD, $type)
        ->condition(self::SOURCE_FIELD, $sourceUuid)
        ->condition(self::EVENT_VERSION_FIELD, $eventVersion)
        ->range(0, 1)
        ->execute();
      $message = $ids === [] ? NULL : $storage->load(reset($ids));
      $enqueueEmail = FALSE;
      if (!$message instanceof MessageInterface) {
        $message = $storage->create([
          'template' => self::TEMPLATE,
          'uid' => $uid,
          self::EVENT_FIELD => $type,
          self::SOURCE_FIELD => $sourceUuid,
          self::EVENT_VERSION_FIELD => $eventVersion,
          self::READ_FIELD => 0,
          self::DIGEST_FIELD => in_array($type, self::DIGEST_EVENTS, TRUE) && $includeInDigest ? 0 : 1,
          self::DELIVERY_FIELD => $sendEmail ? 'pending' : 'sent',
          self::ATTEMPTS_FIELD => 0,
        ]);
        $message->save();
        $enqueueEmail = $sendEmail;
      }
      elseif ($sendEmail
        && $message->get(self::DELIVERY_FIELD)->value === 'failed'
        && (int) $message->get(self::ATTEMPTS_FIELD)->value === 0) {
        // An enqueue failure before any worker attempt may be replayed safely.
        $message->set(self::DELIVERY_FIELD, 'pending')->save();
        $enqueueEmail = TRUE;
      }
      if ($enqueueEmail) {
        try {
          $itemId = $this->queueFactory->get('aculta_lms_messenger_email')->createItem([
            'message_id' => (int) $message->id(),
          ]);
          if ($itemId === FALSE) {
            throw new \RuntimeException('Queue API refused an LMS notice email item.');
          }
        }
        catch (\Throwable $exception) {
          $message->set(self::DELIVERY_FIELD, 'failed')->save();
          $this->logger->error('Could not queue LMS notification message @id.', ['@id' => $message->id()]);
          throw $exception;
        }
      }
      return $message;
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * Resolves current source access and URL; stale/revoked access returns NULL.
   */
  public function resolve(MessageInterface $message, AccountInterface $account): ?array {
    if (!$account->isAuthenticated() || (int) $message->getOwnerId() !== (int) $account->id()
      || $message->bundle() !== self::TEMPLATE) {
      return NULL;
    }
    $type = (string) $message->get(self::EVENT_FIELD)->value;
    $sourceUuid = (string) $message->get(self::SOURCE_FIELD)->value;
    return match ($type) {
      'enrollment' => $this->resolveEnrollment($sourceUuid, $account),
      'certificate' => $this->resolveCertificate($sourceUuid, $account),
      'completion' => $this->resolveCompletion($sourceUuid, $account),
      'evaluation' => $this->resolveEvaluation($sourceUuid, $account),
      'submission' => $this->resolveSubmission($sourceUuid, $account),
      'course_release' => $this->resolveContentRelease($sourceUuid, 'group', $account),
      'lesson_release' => $this->resolveContentRelease($sourceUuid, 'lms_lesson', $account),
      'activity_release' => $this->resolveContentRelease($sourceUuid, 'lms_activity', $account),
      'feedback' => $this->resolveEducatorFeedback($sourceUuid, $account),
      'deadline' => $this->resolveActivityDeadline($sourceUuid, $account, (string) $message->get(self::EVENT_VERSION_FIELD)->value),
      'deadline_reminder' => $this->resolveActivityDeadline($sourceUuid, $account, (string) $message->get(self::EVENT_VERSION_FIELD)->value, TRUE),
      default => NULL,
    };
  }

  /**
   * Records one pending answer for current course graders.
   */
  public function publishSubmissionNoticesForEducators(string $answerUuid, string $eventVersion): void {
    $answers = $this->entityTypeManager->getStorage('lms_answer')->loadByProperties(['uuid' => $answerUuid]);
    $answer = reset($answers);
    if (!$answer instanceof AnswerInterface || $answer->isEvaluated() || $answer->getData() === []) {
      return;
    }
    $course = $this->courseForAnswer($answer);
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course') {
      return;
    }
    $student = $this->entityTypeManager->getStorage('user')->load((int) $answer->getUserId());
    if (!$student instanceof UserInterface || !$student->isActive() || !$this->hasCurrentEnrollment($course, $student)) {
      return;
    }
    foreach (GroupMembership::loadByGroup($course) as $membership) {
      if (!$membership->hasPermission('grade students')) {
        continue;
      }
      $educator = $membership->getEntity();
      if (!$educator instanceof UserInterface || !$educator->isActive()
        || (int) $educator->id() === (int) $answer->getUserId()
        || !$course->hasPermission('grade students', $educator)) {
        continue;
      }
      $this->publishNotice('submission', 'lms_answer', $answerUuid, (int) $educator->id(), $eventVersion);
    }
  }

  /**
   * Publishes course-content release events for currently enrolled learners.
   */
  public function publishContentReleaseNoticesForLearners(string $contentUuid, string $contentEntityType, string $eventVersion): void {
    if (!isset(CourseContentReleasedEvent::NOTICE_TYPES[$contentEntityType])) {
      return;
    }
    $contentEntities = $this->entityTypeManager->getStorage($contentEntityType)->loadByProperties(['uuid' => $contentUuid]);
    $content = reset($contentEntities);
    if (!$content instanceof EntityPublishedInterface || !$content->isPublished()) {
      return;
    }
    $courses = $this->coursesForContent($content);
    foreach ($courses as $course) {
      if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course' || !$course->isPublished()) {
        continue;
      }
      foreach ($course->getRelationships('lms_classes') as $relationship) {
        if (!$relationship instanceof GroupRelationshipInterface) {
          continue;
        }
        $class = $relationship->getEntity();
        if (!$class instanceof GroupInterface || $class->bundle() !== 'lms_class' || !$class->isPublished()) {
          continue;
        }
        foreach (GroupMembership::loadByGroup($class) as $membership) {
          if ($membership->getPluginId() !== 'group_membership') {
            continue;
          }
          $learner = $membership->getEntity();
          if (!$learner instanceof UserInterface || !$learner->isActive()
            || !$course->hasPermission('take course', $learner)
            || !$course->access('view', $learner, TRUE)->isAllowed()) {
            continue;
          }
          $this->publishNotice(
            CourseContentReleasedEvent::NOTICE_TYPES[$contentEntityType],
            $contentEntityType,
            $contentUuid,
            (int) $learner->id(),
            $eventVersion,
          );
        }
      }
    }
  }

  /**
   * Publishes educator-feedback events for the answer owner.
   */
  public function publishEducatorFeedbackNoticeForLearner(string $commentUuid, string $eventVersion): void {
    $comments = $this->entityTypeManager->getStorage('comment')->loadByProperties([
      'uuid' => $commentUuid,
      'comment_type' => 'lms_answer_comment',
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment = reset($comments);
    if (!$comment instanceof CommentInterface) {
      return;
    }
    $answer = $comment->getCommentedEntity();
    if (!$answer instanceof AnswerInterface) {
      return;
    }
    $course = $this->courseForAnswer($answer);
    $learner = $this->entityTypeManager->getStorage('user')->load((int) $answer->getUserId());
    $educator = $comment->getOwner();
    if (!$course instanceof CourseInterface || !$learner instanceof UserInterface || !$learner->isActive()
      || !$educator || (int) $educator->id() === (int) $learner->id()
      || !$course->hasPermission('grade students', $educator)
      || !$this->hasCurrentEnrollment($course, $learner)
      || !$comment->access('view', $learner, TRUE)->isAllowed()
      || !$answer->access('see_details', $learner, TRUE)->isAllowed()) {
      return;
    }
    $this->publishNotice('feedback', 'comment', $commentUuid, (int) $learner->id(), $eventVersion);
  }

  /**
   * Records a new or changed activity deadline for current class learners.
   */
  public function publishActivityDeadlineNoticesForLearners(string $deadlineUuid, string $eventVersion): void {
    $this->recordActivityDeadlineNotice($deadlineUuid, $eventVersion, 'deadline');
  }

  /**
   * Records a due-date reminder when the deadline remains in the future.
   */
  public function publishActivityDeadlineReminderForLearners(string $deadlineUuid, string $eventVersion): void {
    $this->recordActivityDeadlineNotice($deadlineUuid, $eventVersion, 'deadline_reminder');
  }

  /**
   * Creates a class deadline notice for current learners.
   */
  private function recordActivityDeadlineNotice(string $deadlineUuid, string $eventVersion, string $type): void {
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'uuid' => $deadlineUuid,
      'plugin_id' => 'lms_class_activity_due',
    ]);
    $deadline = reset($relationships);
    if (!$deadline instanceof GroupRelationshipInterface || $deadline->get('field_activity_due_at')->isEmpty()) {
      return;
    }
    $currentVersion = ActivityDeadlineAssignedEvent::versionFor(
      (string) $deadline->uuid(),
      (string) $deadline->get('field_activity_due_at')->value,
      (int) $deadline->getChangedTime(),
    );
    $timestamp = strtotime((string) $deadline->get('field_activity_due_at')->value . ' UTC');
    if (!hash_equals($currentVersion, $eventVersion) || $timestamp === FALSE || $timestamp <= time()) {
      return;
    }
    $class = $deadline->getGroup();
    $activity = $deadline->getEntity();
    $course = $class instanceof GroupInterface ? $this->deadlines->courseForClass($class) : NULL;
    if (!$class instanceof GroupInterface || !$class->isPublished() || !$activity instanceof ActivityInterface
      || !$activity instanceof EntityPublishedInterface || !$activity->isPublished()
      || !$course instanceof CourseInterface || !$course->isPublished()
      || !$this->deadlines->activityBelongsToCourse($activity, $course)) {
      return;
    }
    foreach (GroupMembership::loadByGroup($class) as $membership) {
      if ($membership->getPluginId() !== 'group_membership') {
        continue;
      }
      $learner = $membership->getEntity();
      if (!$learner instanceof UserInterface || !$learner->isActive()
        || !$course->hasPermission('take course', $learner)
        || !$course->access('view', $learner, TRUE)->isAllowed()) {
        continue;
      }
      $this->publishNotice($type, 'group_relationship', $deadlineUuid, (int) $learner->id(), $eventVersion);
    }
  }

  /**
   * Publishes one eligible LMS recipient through the shared Messenger API.
   */
  private function publishNotice(string $type, string $sourceEntityType, string $sourceUuid, int $uid, string $version): void {
    $eventType = 'lms.' . $type;
    $this->noticePublisher->publish(new MessengerNoticeEvent(
      $eventType,
      'aculta_lms',
      $sourceEntityType,
      $sourceUuid,
      $version,
      $uid,
      'account',
      new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
      implode(':', [$eventType, $sourceUuid, $version, $uid]),
      ['notice_type' => $type, 'mail_policy' => 'user_preference'],
    ));
  }

  /**
   * Builds accessible, current notices for the Messenger avisos list.
   */
  public function addPersonalNotices(array &$items, AccountInterface $account, array $filters = []): void {
    $this->addPersonalNoticesPage($items, $account, $filters, self::PERSONAL_NOTICE_LIMIT, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function addPersonalNoticesPage(array &$items, AccountInterface $account, array $filters, int $limit, int $offset): bool {
    if (!$account->isAuthenticated()) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $page = EligibleNoticePage::collect(
      function (int $scanOffset, int $batchSize) use ($storage, $account, $filters): array {
        $query = $storage->getQuery()->accessCheck(FALSE)
          ->condition('template', self::TEMPLATE)
          ->condition('uid', (int) $account->id());
        NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
        return $query->sort('created', 'DESC')->sort('id', 'DESC')
          ->range($scanOffset, $batchSize)->execute();
      },
      static fn(array $ids): array => array_values($storage->loadMultiple($ids)),
      fn(mixed $message): ?array => $message instanceof MessageInterface ? $this->resolve($message, $account) : NULL,
      $limit,
      $offset,
    );
    foreach ($page['items'] as $entry) {
      $message = $entry['entity'];
      $notice = $entry['resolved'];
      $title = $notice['title'];
      $heading = [
        '#type' => 'container',
        'title' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'notice-summary-title']),
          '#value' => $title,
        ],
        'excerpt' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute(['data-portal-ui' => 'notice-summary-excerpt']),
          '#value' => $notice['summary'],
        ],
        'state' => [
          '#theme' => 'aculta_portal_element',
          '#tag' => 'span',
          '#element_attributes' => new Attribute([
            'data-portal-ui' => (bool) $message->get(self::READ_FIELD)->value ? 'read-label' : 'read-label read-label-unread',
          ]),
          '#value' => (bool) $message->get(self::READ_FIELD)->value ? $this->t('Lido') : $this->t('Não lido'),
        ],
      ];
      $items[] = [
        '#type' => 'container',
        '#attributes' => [
          'data-portal-ui' => 'notice-summary notice-summary-persistent',
          'data-aculta-notice-origin' => 'aprendizagem',
          'data-aculta-notice-recency' => (string) $message->getCreatedTime(),
          'data-aculta-notice-read' => (bool) $message->get(self::READ_FIELD)->value ? 'read' : 'unread',
          'data-aculta-notice-message-id' => (string) $message->id(),
          ...NoticeRelatedGrouper::sourceAttribute('aprendizagem', (string) $message->get(self::SOURCE_FIELD)->value),
        ],
        'link' => PortalLinkElement::build($heading, Url::fromRoute('aculta_messenger.lms_notice_open', [
          'message' => $message->id(),
        ]), ['data-portal-ui' => 'notice-summary-link']),
      ];
    }
    return $page['has_next'];
  }

  /**
   * Counts only unread notices whose source access remains valid.
   */
  public function unreadCount(int $uid): int {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::READ_FIELD, 0)
      ->execute();
    $count = 0;
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface && $this->resolve($message, $account) !== NULL) {
        $count++;
      }
    }
    return $count;
  }

  /**
   * Marks one owned record read after its source was revalidated.
   */
  public function markRead(MessageInterface $message): void {
    if (!(bool) $message->get(self::READ_FIELD)->value) {
      $message->set(self::READ_FIELD, 1)->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setRead(MessageInterface $message, AccountInterface $account, bool $read): bool {
    if ($this->resolve($message, $account) === NULL) {
      return FALSE;
    }
    if ((bool) $message->get(self::READ_FIELD)->value !== $read) {
      $message->set(self::READ_FIELD, $read ? 1 : 0)->save();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function pendingRecipientIds(): array {
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition(self::EVENT_FIELD, self::DIGEST_EVENTS, 'IN')
      ->condition(self::DIGEST_FIELD, 0)
      ->execute();
    $uids = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface) {
        $uids[(int) $message->getOwnerId()] = TRUE;
      }
    }
    return array_keys($uids);
  }

  /**
   * {@inheritdoc}
   */
  public function digestRowsFor(int $uid): array {
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      $this->discardDigest($uid);
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::EVENT_FIELD, self::DIGEST_EVENTS, 'IN')
      ->condition(self::DIGEST_FIELD, 0)
      ->execute();
    $rows = [];
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface) {
        continue;
      }
      $notice = $this->resolve($message, $account);
      if ($notice === NULL) {
        $message->set(self::DIGEST_FIELD, 1)->save();
        continue;
      }
      $rows[] = [
        'tipo' => self::DIGEST_TYPE,
        'titulo' => (string) $notice['digest_summary'],
        'quantidade' => 1,
        'delivery_id' => (int) $message->id(),
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid, array $deliveryIds): void {
    $this->setDigestSent($uid, $deliveryIds);
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigestItems(int $uid, array $deliveryIds): void {
    $this->setDigestSent($uid, $deliveryIds);
  }

  /**
   * Marks pending course completion summaries as sent or discarded.
   */
  private function setDigestSent(int $uid, ?array $deliveryIds = NULL): void {
    if ($deliveryIds === NULL) {
      $ids = $this->entityTypeManager->getStorage('message')->getQuery()
        ->accessCheck(FALSE)
        ->condition('template', self::TEMPLATE)
        ->condition('uid', $uid)
        ->condition(self::EVENT_FIELD, self::DIGEST_EVENTS, 'IN')
        ->condition(self::DIGEST_FIELD, 0)
        ->execute();
    }
    else {
      $ids = $deliveryIds;
    }
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface && (int) $message->getOwnerId() === $uid && $message->bundle() === self::TEMPLATE) {
        $message->set(self::DIGEST_FIELD, 1)->save();
      }
    }
  }

  /**
   * Loads and authorizes an enrollment event's course and class.
   */
  private function resolveEnrollment(string $sourceUuid, AccountInterface $account): ?array {
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties(['uuid' => $sourceUuid]);
    $membership = reset($relationships);
    $member = $membership instanceof GroupMembership ? $membership->getEntity() : NULL;
    if (!$membership instanceof GroupMembership || $membership->getPluginId() !== 'group_membership'
      || !$member instanceof UserInterface || !$member->isActive()
      || (int) $member->id() !== (int) $account->id()) {
      return NULL;
    }
    $class = $membership->getGroup();
    if ($class === NULL || $class->bundle() !== 'lms_class'
      || !$class->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $parents = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $class->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($parents as $parent) {
      $course = $parent instanceof GroupRelationshipInterface ? $parent->getGroup() : NULL;
      if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
        || !$course->hasPermission('take course', $account)
        || !$course->access('view', $account, TRUE)->isAllowed()) {
        continue;
      }
      $url = $this->domainPurposeManager->routeUrl('courses', 'lms.course.start', ['group' => $course->id()]);
      if ($url === NULL) {
        return NULL;
      }
      return [
        'title' => $this->t('Matrícula confirmada'),
        'summary' => $this->t('Você está matriculado na turma @class do curso @course.', [
          '@class' => $class->label(),
          '@course' => $course->label(),
        ]),
        'email_summary' => $this->t('Sua matrícula na turma @class do curso @course foi confirmada.', [
          '@class' => $class->label(),
          '@course' => $course->label(),
        ]),
        'url' => $url,
      ];
    }
    return NULL;
  }

  /**
   * Loads the owner's certificate snapshot and public verifier URL.
   */
  private function resolveCertificate(string $sourceUuid, AccountInterface $account): ?array {
    $certificates = $this->entityTypeManager->getStorage('aculta_lms_certificate')->loadByProperties(['uuid' => $sourceUuid]);
    $certificate = reset($certificates);
    if (!$certificate instanceof CourseCertificate || (int) $certificate->get('uid')->value !== (int) $account->id()) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('main', 'aculta_lms.certificate_verify', [
      'codigo' => $certificate->get('verification_code')->value,
    ]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Seu certificado está disponível'),
      'summary' => $this->t('O certificado do curso @course foi emitido.', ['@course' => $certificate->get('course_title')->value]),
      'email_summary' => $this->t('O certificado do curso @course está disponível para verificação.', ['@course' => $certificate->get('course_title')->value]),
      'url' => $url,
    ];
  }

  /**
   * Loads a passed course status and checks its current learner membership.
   */
  private function resolveCompletion(string $sourceUuid, AccountInterface $account): ?array {
    $statuses = $this->entityTypeManager->getStorage('lms_course_status')->loadByProperties(['uuid' => $sourceUuid]);
    $status = reset($statuses);
    if (!$status instanceof CourseStatusInterface || $status->getUserId() !== (string) $account->id()
      || !$status->isFinished() || $status->getStatus() !== CourseStatusInterface::STATUS_PASSED) {
      return NULL;
    }
    // LMS deletes Course entities asynchronously, so a status can briefly
    // retain a reference whose typed getter throws while that cleanup runs.
    $course = $status->get(CourseStatusInterface::COURSE_FIELD)->entity;
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    if (!$this->hasCurrentEnrollment($course, $account)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'lms.course.start', ['group' => $course->id()]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Curso concluído'),
      'summary' => $this->t('Você concluiu o curso @course.', ['@course' => $course->label()]),
      'email_summary' => $this->t('Você concluiu o curso @course.', ['@course' => $course->label()]),
      'digest_summary' => $this->t('Conclusão confirmada: @course', ['@course' => $course->label()]),
      'url' => $url,
    ];
  }

  /**
   * Resolves an answer's current course without typed getters on stale refs.
   *
   * LMS deletes status entities asynchronously. A still-persisted answer can
   * temporarily reference a missing LessonStatus or CourseStatus; notices
   * should disappear during that interval instead of raising a type error
   * while their list or counter is being built.
   */
  private function courseForAnswer(AnswerInterface $answer): ?CourseInterface {
    $lessonStatus = $answer->get('lesson_status')->entity;
    if (!$lessonStatus instanceof LessonStatusInterface) {
      return NULL;
    }
    $courseStatus = $lessonStatus->get('course_status')->entity;
    if (!$courseStatus instanceof CourseStatusInterface) {
      return NULL;
    }
    $course = $courseStatus->get(CourseStatusInterface::COURSE_FIELD)->entity;
    return $course instanceof CourseInterface && $course->bundle() === 'lms_course'
      ? $course
      : NULL;
  }

  /**
   * Resolves an evaluated answer without exposing its response or score. */
  private function resolveEvaluation(string $sourceUuid, AccountInterface $account): ?array {
    $answers = $this->entityTypeManager->getStorage('lms_answer')->loadByProperties(['uuid' => $sourceUuid]);
    $answer = reset($answers);
    if (!$answer instanceof AnswerInterface || !$answer->isEvaluated()
      || $answer->getUserId() !== (string) $account->id()
      || !$answer->access('see_details', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $course = $this->courseForAnswer($answer);
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()
      || !$this->hasCurrentEnrollment($course, $account)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'lms.answer.details', [
      'lms_answer' => $answer->id(),
      'js' => 'nojs',
    ]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Avaliação disponível'),
      'summary' => $this->t('Uma atividade do curso @course recebeu avaliação.', [
        '@course' => $course->label(),
      ]),
      'email_summary' => $this->t('Uma atividade do curso @course recebeu avaliação.', [
        '@course' => $course->label(),
      ]),
      'digest_summary' => $this->t('Avaliação disponível no curso @course.', [
        '@course' => $course->label(),
      ]),
      'url' => $url,
    ];
  }

  /**
   * Resolves a pending response only for a current course grader.
   */
  private function resolveSubmission(string $sourceUuid, AccountInterface $account): ?array {
    $answers = $this->entityTypeManager->getStorage('lms_answer')->loadByProperties(['uuid' => $sourceUuid]);
    $answer = reset($answers);
    if (!$answer instanceof AnswerInterface || $answer->isEvaluated() || $answer->getData() === []
      || !$answer->access('see_details', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $course = $this->courseForAnswer($answer);
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()
      || !$course->hasPermission('grade students', $account)) {
      return NULL;
    }
    $student = $this->entityTypeManager->getStorage('user')->load((int) $answer->getUserId());
    if (!$student instanceof UserInterface || !$student->isActive() || !$this->hasCurrentEnrollment($course, $student)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'lms.answer.details', [
      'lms_answer' => $answer->id(),
      'js' => 'nojs',
    ]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Resposta aguardando correção'),
      'summary' => $this->t('Uma nova resposta aguarda correção no curso @course.', [
        '@course' => $course->label(),
      ]),
      'email_summary' => $this->t('Uma nova resposta aguarda correção no curso @course.', [
        '@course' => $course->label(),
      ]),
      'digest_summary' => $this->t('Resposta para correção: @course', [
        '@course' => $course->label(),
      ]),
      'url' => $url,
    ];
  }

  /**
   * Resolves a published content release only while the learner stays enrolled.
   */
  private function resolveContentRelease(string $sourceUuid, string $entityType, AccountInterface $account): ?array {
    $entities = $this->entityTypeManager->getStorage($entityType)->loadByProperties(['uuid' => $sourceUuid]);
    $content = reset($entities);
    if (!$content instanceof EntityPublishedInterface || !$content->isPublished()) {
      return NULL;
    }
    foreach ($this->coursesForContent($content) as $course) {
      if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course' || !$course->isPublished()
        || !$course->access('view', $account, TRUE)->isAllowed() || !$this->hasCurrentEnrollment($course, $account)) {
        continue;
      }
      $url = $this->domainPurposeManager->routeUrl('courses', 'lms.course.start', ['group' => $course->id()]);
      if ($url === NULL) {
        return NULL;
      }
      return [
        'title' => $this->t('Novo conteúdo disponível'),
        'summary' => $this->t('Há conteúdo novo em um curso no qual você está matriculado.'),
        'digest_summary' => $this->t('Conteúdo liberado: @content', ['@content' => $content->label()]),
        'url' => $url,
      ];
    }
    return NULL;
  }

  /**
   * Resolves current educator feedback without exposing its body in the notice. */
  private function resolveEducatorFeedback(string $commentUuid, AccountInterface $account): ?array {
    $comments = $this->entityTypeManager->getStorage('comment')->loadByProperties([
      'uuid' => $commentUuid,
      'comment_type' => 'lms_answer_comment',
      'status' => CommentInterface::PUBLISHED,
    ]);
    $comment = reset($comments);
    if (!$comment instanceof CommentInterface || !$comment->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $answer = $comment->getCommentedEntity();
    if (!$answer instanceof AnswerInterface || (int) $answer->getUserId() !== (int) $account->id()
      || !$answer->access('see_details', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $course = $this->courseForAnswer($answer);
    $educator = $comment->getOwner();
    if (!$course instanceof CourseInterface || !$educator || !$course->hasPermission('grade students', $educator)
      || !$this->hasCurrentEnrollment($course, $account)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'lms.answer.details', [
      'lms_answer' => $answer->id(),
      'js' => 'nojs',
    ]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $this->t('Retorno do educador'),
      'summary' => $this->t('Seu educador publicou um retorno para uma atividade.'),
      'digest_summary' => $this->t('Retorno disponível em uma atividade do curso.'),
      'url' => $url,
    ];
  }

  /**
   * Resolves a current class deadline only for its active class learners. */
  private function resolveActivityDeadline(string $deadlineUuid, AccountInterface $account, string $eventVersion, bool $reminder = FALSE): ?array {
    $relationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'uuid' => $deadlineUuid,
      'plugin_id' => 'lms_class_activity_due',
    ]);
    $deadline = reset($relationships);
    if (!$deadline instanceof GroupRelationshipInterface || $deadline->get('field_activity_due_at')->isEmpty()) {
      return NULL;
    }
    $currentVersion = ActivityDeadlineAssignedEvent::versionFor(
      (string) $deadline->uuid(),
      (string) $deadline->get('field_activity_due_at')->value,
      (int) $deadline->getChangedTime(),
    );
    if (!hash_equals($currentVersion, $eventVersion)) {
      return NULL;
    }
    $class = $deadline->getGroup();
    $activity = $deadline->getEntity();
    $course = $class instanceof GroupInterface ? $this->deadlines->courseForClass($class) : NULL;
    if (!$class instanceof GroupInterface || !$class->isPublished() || !$activity instanceof ActivityInterface
      || !$activity instanceof EntityPublishedInterface || !$activity->isPublished()
      || !$course instanceof CourseInterface || !$course->isPublished()
      || !$course->access('view', $account, TRUE)->isAllowed()
      || !$this->deadlines->activityBelongsToCourse($activity, $course)
      || !$this->memberCanTakeCourseInClass($class, $account)) {
      return NULL;
    }
    $date = DrupalDateTime::createFromFormat(
      DateTimeItemInterface::DATETIME_STORAGE_FORMAT,
      (string) $deadline->get('field_activity_due_at')->value,
      new \DateTimeZone('UTC'),
    );
    if (!$date instanceof DrupalDateTime || $date->hasErrors()) {
      return NULL;
    }
    $user = $account instanceof UserInterface ? $account : NULL;
    $timestamp = $date->getTimestamp();
    $formattedDate = $this->dateFormatter->format($timestamp, 'long', NULL, $user?->getTimeZone());
    $url = $this->domainPurposeManager->routeUrl('courses', 'lms.course.start', ['group' => $course->id()]);
    if ($url === NULL) {
      return NULL;
    }
    return [
      'title' => $reminder ? $this->t('Lembrete de prazo') : $this->t('Prazo de atividade'),
      'summary' => $reminder
        ? $this->t('Uma atividade da turma vence em breve, até @date.', ['@date' => $formattedDate])
        : $this->t('Uma atividade da turma tem prazo até @date.', ['@date' => $formattedDate]),
      'digest_summary' => $reminder
        ? $this->t('Lembrete de prazo: @date', ['@date' => $formattedDate])
        : $this->t('Prazo de atividade: @date', ['@date' => $formattedDate]),
      'url' => $url,
    ];
  }

  /**
   * Checks an active take-course membership in the specific class. */
  private function memberCanTakeCourseInClass(GroupInterface $class, AccountInterface $account): bool {
    $course = $this->deadlines->courseForClass($class);
    if (!$course instanceof CourseInterface || !$course->hasPermission('take course', $account)) {
      return FALSE;
    }
    foreach (GroupMembership::loadByGroup($class) as $membership) {
      $member = $membership->getEntity();
      if ($member instanceof UserInterface && $member->isActive()
        && (int) $member->id() === (int) $account->id()
        && $membership->getPluginId() === 'group_membership') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Finds published courses that currently reference the released content.
   *
   * @return \Drupal\group\Entity\GroupInterface[]
   *   Course groups that contain the lesson or activity.
   */
  private function coursesForContent(EntityPublishedInterface $content): array {
    if ($content instanceof GroupInterface && $content->bundle() === 'lms_course') {
      return [$content];
    }
    $lessonIds = [];
    if ($content instanceof LessonInterface) {
      $lessonIds[] = (int) $content->id();
    }
    elseif ($content instanceof ActivityInterface) {
      $lessonIds = array_map('intval', array_keys($this->entityTypeManager->getStorage('lms_lesson')->getQuery()
        ->accessCheck(FALSE)
        ->condition(LessonInterface::ACTIVITIES . '.target_id', (int) $content->id())
        ->execute()));
    }
    if ($lessonIds === []) {
      return [];
    }
    $courseIds = $this->entityTypeManager->getStorage('group')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'lms_course')
      ->condition('lessons.target_id', $lessonIds, 'IN')
      ->execute();
    return array_values(array_filter(
      $this->entityTypeManager->getStorage('group')->loadMultiple($courseIds),
      static fn ($course): bool => $course instanceof GroupInterface,
    ));
  }

  /**
   * Checks active take-course permission in any class linked to the course. */
  private function hasCurrentEnrollment(CourseInterface $course, AccountInterface $account): bool {
    if (!$course->hasPermission('take course', $account)) {
      return FALSE;
    }
    $classRelationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $course->id(),
      'plugin_id' => 'lms_classes',
    ]);
    foreach ($classRelationships as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($class === NULL || $class->bundle() !== 'lms_class') {
        continue;
      }
      foreach (GroupMembership::loadByGroup($class) as $membership) {
        $member = $membership->getEntity();
        if ($membership->getPluginId() === 'group_membership'
          && $member instanceof UserInterface && $member->isActive()
          && (int) $member->id() === (int) $account->id()) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
