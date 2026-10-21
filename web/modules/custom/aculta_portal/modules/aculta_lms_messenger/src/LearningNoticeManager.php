<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_messenger;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupMembership;
use Drupal\group\Entity\GroupRelationshipInterface;
use Drupal\lms\Entity\Bundle\CourseInterface;
use Drupal\lms\Entity\CourseStatusInterface;
use Drupal\message\MessageInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\aculta_messenger\Aviso\AvisoDigestProviderInterface;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Psr\Log\LoggerInterface;

/**
 * Persists and presents LMS notices using Messenger's Message entities.
 */
final class LearningNoticeManager implements LearningNoticeManagerInterface, AvisoDigestProviderInterface {

  use StringTranslationTrait;

  public const TEMPLATE = 'aculta_msg_lms_notice';

  private const EVENT_FIELD = 'field_aculta_lms_event_type';

  private const SOURCE_FIELD = 'field_aculta_lms_source_uuid';

  private const READ_FIELD = 'field_aculta_lms_read';

  private const DELIVERY_FIELD = 'field_aculta_msg_delivery';

  private const ATTEMPTS_FIELD = 'field_aculta_messenger_attempts';

  private const DIGEST_FIELD = 'field_aculta_lms_digest_sent';

  private const DIGEST_TYPE = 'aprendizagem';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly QueueFactoryInterface $queueFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Creates one durable notice per source and recipient; enqueue email once.
   */
  public function record(string $sourceUuid, string $type, int $uid, bool $sendEmail): ?MessageInterface {
    if (!in_array($type, ['enrollment', 'certificate', 'completion'], TRUE)) {
      return NULL;
    }
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface || !$account->isActive()) {
      return NULL;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::EVENT_FIELD, $type)
      ->condition(self::SOURCE_FIELD, $sourceUuid)
      ->range(0, 1)
      ->execute();
    $message = $ids === [] ? NULL : $storage->load(reset($ids));
    if (!$message instanceof MessageInterface) {
      $message = $storage->create([
        'template' => self::TEMPLATE,
        'uid' => $uid,
        self::EVENT_FIELD => $type,
        self::SOURCE_FIELD => $sourceUuid,
        self::READ_FIELD => 0,
        self::DIGEST_FIELD => $type === 'completion' ? 0 : 1,
        self::DELIVERY_FIELD => $sendEmail ? 'pending' : 'sent',
        self::ATTEMPTS_FIELD => 0,
      ]);
      $message->save();
    }
    if ($sendEmail && $message->get(self::DELIVERY_FIELD)->value !== 'sent') {
      $itemId = $this->queueFactory->get('aculta_lms_messenger_email')->createItem([
        'message_id' => (int) $message->id(),
      ]);
      if ($itemId === FALSE) {
        $this->logger->error('Could not queue LMS notification message @id.', ['@id' => $message->id()]);
      }
    }
    return $message;
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
      default => NULL,
    };
  }

  /**
   * Builds accessible, current notices for the Messenger avisos list.
   */
  public function addPersonalNotices(array &$items, AccountInterface $account): void {
    if (!$account->isAuthenticated()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('message');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', (int) $account->id())
      ->sort('created', 'DESC')
      ->range(0, 10)
      ->execute();
    foreach ($storage->loadMultiple($ids) as $message) {
      if (!$message instanceof MessageInterface) {
        continue;
      }
      $notice = $this->resolve($message, $account);
      if ($notice === NULL) {
        continue;
      }
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
        '#attributes' => ['data-portal-ui' => 'notice-summary notice-summary-persistent'],
        'link' => PortalLinkElement::build($heading, Url::fromRoute('aculta_lms_messenger.notice_open', [
          'message' => $message->id(),
        ]), ['data-portal-ui' => 'notice-summary-link']),
      ];
    }
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
  public function pendingRecipientIds(): array {
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition(self::EVENT_FIELD, 'completion')
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
      ->condition(self::EVENT_FIELD, 'completion')
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
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function markDigestSent(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * {@inheritdoc}
   */
  public function discardDigest(int $uid): void {
    $this->setDigestSent($uid);
  }

  /**
   * Marks pending course completion summaries as sent or discarded.
   */
  private function setDigestSent(int $uid): void {
    $ids = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', self::TEMPLATE)
      ->condition('uid', $uid)
      ->condition(self::EVENT_FIELD, 'completion')
      ->condition(self::DIGEST_FIELD, 0)
      ->execute();
    foreach ($this->entityTypeManager->getStorage('message')->loadMultiple($ids) as $message) {
      if ($message instanceof MessageInterface) {
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
      || !$member instanceof UserInterface || (int) $member->id() !== (int) $account->id()
      || !$membership->hasPermission('take course')) {
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
    $course = $status->getCourse();
    if (!$course instanceof CourseInterface || $course->bundle() !== 'lms_course'
      || !$course->access('view', $account, TRUE)->isAllowed()) {
      return NULL;
    }
    $classRelationships = $this->entityTypeManager->getStorage('group_relationship')->loadByProperties([
      'entity_id' => $course->id(),
      'plugin_id' => 'lms_classes',
    ]);
    $hasCurrentEnrollment = FALSE;
    foreach ($classRelationships as $relationship) {
      $class = $relationship instanceof GroupRelationshipInterface ? $relationship->getEntity() : NULL;
      if ($class === NULL || $class->bundle() !== 'lms_class') {
        continue;
      }
      foreach (GroupMembership::loadByGroup($class) as $membership) {
        $member = $membership->getEntity();
        if ($member instanceof UserInterface && (int) $member->id() === (int) $account->id()
          && $membership->hasPermission('take course')) {
          $hasCurrentEnrollment = TRUE;
          break 2;
        }
      }
    }
    if (!$hasCurrentEnrollment) {
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

}
