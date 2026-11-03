<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Integration\Lms;

use Drupal\aculta_lms\Event\ActivityDeadlineAssignedEvent;
use Drupal\aculta_lms\Notice\LearningEligibilityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityInterface;
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
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\ActivityInterface;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_messenger\Aviso\EligibleNoticePage;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\aculta_lms\Deadline\ClassActivityDeadlineManager;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderIdentityInterface;
use Drupal\aculta_messenger\Aviso\AvisoDigestSelectiveDiscardInterface;
use Drupal\aculta_messenger\Service\NoticeListFilter;
use Drupal\aculta_messenger\Service\NoticeRelatedGrouper;
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
    private readonly LockBackendInterface $lock,
    private readonly LearningEligibilityInterface $eligibility,
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
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
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
    return $this->resolveWithSources($message, $account, $this->loadSourcesForMessages([$message]));
  }

  /**
   * Resolves a notice using source entities freshly loaded for this batch.
   *
   * @param \Drupal\message\MessageInterface $message
   *   Candidate Messenger notice.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account requesting the notice.
   * @param array<string, array<string, \Drupal\Core\Entity\EntityInterface>> $sources
   *   Source entities indexed by entity type and UUID.
   *
   * @return array|null
   *   Current display data, or NULL when source access is no longer valid.
   */
  private function resolveWithSources(MessageInterface $message, AccountInterface $account, array $sources): ?array {
    if (!$account->isAuthenticated() || (int) $message->getOwnerId() !== (int) $account->id()
      || $message->bundle() !== self::TEMPLATE) {
      return NULL;
    }
    $type = (string) $message->get(self::EVENT_FIELD)->value;
    $sourceUuid = (string) $message->get(self::SOURCE_FIELD)->value;
    $entityType = $this->sourceEntityType($type);
    $source = $entityType === NULL ? NULL : ($sources[$entityType][$sourceUuid] ?? NULL);
    if (!$source instanceof EntityInterface) {
      return NULL;
    }
    return match ($type) {
      'enrollment' => $source instanceof GroupRelationshipInterface ? $this->resolveEnrollment($sourceUuid, $account, $source) : NULL,
      'certificate' => $source instanceof CourseCertificate ? $this->resolveCertificate($sourceUuid, $account, $source) : NULL,
      'completion' => $this->resolveCompletion($sourceUuid, $account, $source),
      'evaluation' => $this->resolveEvaluation($sourceUuid, $account, $source),
      'submission' => $this->resolveSubmission($sourceUuid, $account, $source),
      'course_release' => $this->resolveContentRelease($sourceUuid, $account, $source),
      'lesson_release' => $this->resolveContentRelease($sourceUuid, $account, $source),
      'activity_release' => $this->resolveContentRelease($sourceUuid, $account, $source),
      'feedback' => $source instanceof CommentInterface ? $this->resolveEducatorFeedback($sourceUuid, $account, $source) : NULL,
      'deadline' => $source instanceof GroupRelationshipInterface ? $this->resolveActivityDeadline($sourceUuid, $account, (string) $message->get(self::EVENT_VERSION_FIELD)->value, FALSE, $source) : NULL,
      'deadline_reminder' => $source instanceof GroupRelationshipInterface ? $this->resolveActivityDeadline($sourceUuid, $account, (string) $message->get(self::EVENT_VERSION_FIELD)->value, TRUE, $source) : NULL,
      default => NULL,
    };
  }

  /**
   * Batch-loads current source entities for a set of LMS notice Messages.
   *
   * UUID lookup identifies canonical records. Resetting the matched entity IDs
   * before loading them together makes each page, counter and digest use fresh
   * source state without issuing one UUID query per notice.
   *
   * @param \Drupal\message\MessageInterface[] $messages
   *   Candidate LMS notices.
   *
   * @return array<string, array<string, \Drupal\Core\Entity\EntityInterface>>
   *   Entities keyed by type and UUID.
   */
  private function loadSourcesForMessages(array $messages): array {
    $uuidsByType = [];
    foreach ($messages as $message) {
      if (!$message instanceof MessageInterface || $message->bundle() !== self::TEMPLATE) {
        continue;
      }
      $type = (string) $message->get(self::EVENT_FIELD)->value;
      $entityType = $this->sourceEntityType($type);
      $uuid = (string) $message->get(self::SOURCE_FIELD)->value;
      if ($entityType !== NULL && $uuid !== '') {
        $uuidsByType[$entityType][$uuid] = $uuid;
      }
    }

    $sources = [];
    foreach ($uuidsByType as $entityType => $uuids) {
      $storage = $this->entityTypeManager->getStorage($entityType);
      $matches = $storage->loadByProperties(['uuid' => array_values($uuids)]);
      $ids = [];
      foreach ($matches as $entity) {
        if ($entity instanceof EntityInterface && isset($uuids[$entity->uuid()]) && $entity->id() !== NULL) {
          $ids[] = $entity->id();
        }
      }
      if ($ids === []) {
        continue;
      }
      $storage->resetCache($ids);
      foreach ($storage->loadMultiple($ids) as $entity) {
        if ($entity instanceof EntityInterface && isset($uuids[$entity->uuid()])) {
          $sources[$entityType][$entity->uuid()] = $entity;
        }
      }
    }
    return $sources;
  }

  /**
   * Gets the source entity type for a persisted LMS notice event.
   */
  private function sourceEntityType(string $eventType): ?string {
    return match ($eventType) {
      'enrollment', 'deadline', 'deadline_reminder' => 'group_relationship',
      'certificate' => 'aculta_lms_certificate',
      'completion' => 'aculta_lms_progress',
      'evaluation', 'submission' => 'aculta_lms_answer',
      'course_release' => 'group',
      'lesson_release' => 'aculta_lms_lesson',
      'activity_release' => 'aculta_lms_activity',
      'feedback' => 'comment',
      default => NULL,
    };
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
    $sources = [];
    $page = EligibleNoticePage::collect(
      function (int $scanOffset, int $batchSize) use ($storage, $account, $filters): array {
        $query = $storage->getQuery()->accessCheck(FALSE)
          ->condition('template', self::TEMPLATE)
          ->condition('uid', (int) $account->id());
        NoticeListFilter::applyReadCondition($query, self::READ_FIELD, $filters);
        return $query->sort('created', 'DESC')->sort('id', 'DESC')
          ->range($scanOffset, $batchSize)->execute();
      },
      function (array $ids) use ($storage, &$sources): array {
        $messages = array_values($storage->loadMultiple($ids));
        $sources = $this->loadSourcesForMessages($messages);
        return $messages;
      },
      function (mixed $message) use ($account, &$sources): ?array {
        return $message instanceof MessageInterface ? $this->resolveWithSources($message, $account, $sources) : NULL;
      },
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
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return 0;
    }
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::READ_FIELD, 0)
      ->execute();
    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    $sources = $this->loadSourcesForMessages($messages);
    $count = 0;
    foreach ($messages as $message) {
      if ($message instanceof MessageInterface && $this->resolveWithSources($message, $account, $sources) !== NULL) {
        $count++;
      }
    }
    return $count;
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
    $account = $this->entityTypeManager->getStorage('user')->loadUnchanged($uid);
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
    $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($ids);
    $sources = $this->loadSourcesForMessages($messages);
    $rows = [];
    foreach ($messages as $message) {
      if (!$message instanceof MessageInterface) {
        continue;
      }
      $notice = $this->resolveWithSources($message, $account, $sources);
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
  private function resolveEnrollment(string $sourceUuid, AccountInterface $account, GroupRelationshipInterface $membership): ?array {
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
  private function resolveCertificate(string $sourceUuid, AccountInterface $account, CourseCertificate $certificate): ?array {
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
  private function resolveCompletion(string $sourceUuid, AccountInterface $account, EntityInterface $status): ?array {
    if ($status->getEntityTypeId() !== 'aculta_lms_progress'
      || (int) $status->get('uid')->value !== (int) $account->id()
      || !(bool) $status->get('finished')->value
      || (string) $status->get('status')->value !== 'passed') {
      return NULL;
    }
    $course = $this->entityTypeManager->getStorage('group')->load((int) $status->get('course_id')->value);
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    if (!$this->eligibility->hasCurrentEnrollment($course, $account)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'aculta_lms.student_dashboard');
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
   * Resolves an evaluated answer without exposing its response or score. */
  private function resolveEvaluation(string $sourceUuid, AccountInterface $account, EntityInterface $answer): ?array {
    if ($answer->getEntityTypeId() !== 'aculta_lms_answer'
      || (string) $answer->get('state')->value !== 'graded'
      || (int) $answer->get('uid')->value !== (int) $account->id()) {
      return NULL;
    }
    $course = $this->eligibility->courseForAnswer($answer);
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()
      || !$this->eligibility->hasCurrentEnrollment($course, $account)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'aculta_lms.student_dashboard');
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
  private function resolveSubmission(string $sourceUuid, AccountInterface $account, EntityInterface $answer): ?array {
    if ($answer->getEntityTypeId() !== 'aculta_lms_answer'
      || (string) $answer->get('state')->value !== 'submitted') {
      return NULL;
    }
    $course = $this->eligibility->courseForAnswer($answer);
    if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()
      || !$course->hasPermission('grade students', $account)) {
      return NULL;
    }
    $student = $this->entityTypeManager->getStorage('user')->loadUnchanged((int) $answer->get('uid')->value);
    if (!$student instanceof UserInterface || !$student->isActive() || !$this->eligibility->hasCurrentEnrollment($course, $student)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'aculta_lms.educator_dashboard');
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
  private function resolveContentRelease(string $sourceUuid, AccountInterface $account, EntityInterface $content): ?array {
    if (!$this->eligibility->isContentAvailable($content)) {
      return NULL;
    }
    foreach ($this->eligibility->coursesForContent($content) as $course) {
      if (!$course instanceof GroupInterface || $course->bundle() !== 'lms_course' || !$course->isPublished()
        || !$course->access('view', $account, TRUE)->isAllowed() || !$this->eligibility->hasCurrentEnrollment($course, $account)) {
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
  private function resolveEducatorFeedback(string $commentUuid, AccountInterface $account, CommentInterface $comment): ?array {
    if ($comment->bundle() !== 'lms_answer_comment' || (int) $comment->get('status')->value !== CommentInterface::PUBLISHED) {
      return NULL;
    }
    if (!$comment instanceof CommentInterface || !$comment->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $answer = $comment->getCommentedEntity();
    if (!$answer instanceof EntityInterface || $answer->getEntityTypeId() !== 'aculta_lms_answer'
      || (int) $answer->get('uid')->value !== (int) $account->id()) {
      return NULL;
    }
    $course = $this->eligibility->courseForAnswer($answer);
    $educator = $comment->getOwner();
    if (!$course instanceof GroupInterface || !$educator || !$course->hasPermission('grade students', $educator)
      || !$this->eligibility->hasCurrentEnrollment($course, $account)) {
      return NULL;
    }
    $url = $this->domainPurposeManager->routeUrl('courses', 'aculta_lms.student_dashboard');
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
  private function resolveActivityDeadline(string $deadlineUuid, AccountInterface $account, string $eventVersion, bool $reminder, GroupRelationshipInterface $deadline): ?array {
    if ($deadline->getPluginId() !== 'lms_class_activity_due') {
      return NULL;
    }
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
      || !$this->eligibility->memberCanTakeCourseInClass($class, $account)) {
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

}
