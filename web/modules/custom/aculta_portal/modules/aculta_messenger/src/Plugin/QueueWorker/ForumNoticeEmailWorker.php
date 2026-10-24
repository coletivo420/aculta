<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Plugin\QueueWorker;

use Drupal\aculta_mailer\Api\AcultaMailerInterface;
use Drupal\aculta_mailer\Delivery\DeliveryError;
use Drupal\aculta_mailer\Message\MailMessage;
use Drupal\aculta_messenger\Entity\Aviso;
use Drupal\aculta_messenger\Aviso\AvisoPreferencias;
use Drupal\aculta_messenger\Aviso\AvisoPreferenciasInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeAccessPolicyInterface;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutbox;
use Drupal\aculta_messenger\Aviso\ForumNoticeEmailOutboxInterface;
use Drupal\aculta_messenger\Mail\ForumNoticeMailType;
use Drupal\aculta_messenger\Service\MessengerEmailLock;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_forum\Entity\ForumPost;
use Drupal\comment\CommentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flag\FlagServiceInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\aculta_portal\Account\EmailConfirmationPolicy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delivers direct Forum emails after revalidating current access and preference. */
#[QueueWorker(
  id: ForumNoticeEmailOutbox::QUEUE,
  title: new TranslatableMarkup('ACULTA Forum direct notice email'),
  cron: ['time' => 30],
)]
final class ForumNoticeEmailWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const MAX_ATTEMPTS = 3;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ForumNoticeEmailOutboxInterface $outbox,
    private readonly AvisoPreferenciasInterface $preferences,
    private readonly ForumNoticeAccessPolicyInterface $accessPolicy,
    private readonly FlagServiceInterface $flagService,
    private readonly EmailConfirmationPolicy $emailPolicy,
    private readonly DomainPurposeManager $domainPurposes,
    private readonly AcultaMailerInterface $mailer,
    private readonly LockBackendInterface $lock,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('aculta_messenger.forum_notice_email_outbox'),
      $container->get('aculta_messenger.aviso_preferencias'),
      $container->get('aculta_messenger.forum_notice_access_policy'),
      $container->get('flag'),
      $container->get('aculta_portal.email_confirmation_policy'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('aculta_mailer.mailer'),
      $container->get('lock.persistent'),
    );
  }

  /**
   * Processes one idempotent direct email request. */
  public function processItem($data): void {
    $id = is_array($data) ? filter_var($data['event_id'] ?? NULL, FILTER_VALIDATE_INT) : FALSE;
    if ($id === FALSE || $id < 1) {
      return;
    }
    $lockId = 'aculta_messenger:forum_direct_email:' . $id;
    if (!$this->lock->acquire($lockId, MessengerEmailLock::TIMEOUT)) {
      throw new \RuntimeException('Forum direct notice email is already being processed.');
    }
    try {
      $event = $this->outbox->claim((int) $id);
      if ($event === NULL) {
        return;
      }
      $this->deliver($event);
    }
    finally {
      $this->lock->release($lockId);
    }
  }

  /**
   * Revalidates recipient, current subscription, source publication and access. */
  private function deliver(array $event): void {
    $uid = (int) $event['uid'];
    $recipient = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$recipient instanceof UserInterface || !$recipient->isActive()
      || !filter_var((string) $recipient->getEmail(), FILTER_VALIDATE_EMAIL)
      || !$this->emailPolicy->isConfirmed($recipient)) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_CANCELLED);
      return;
    }
    $eventType = (string) $event['event_type'];
    $category = $eventType === 'topic_revision' ? Aviso::TIPO_ATUALIZACAO : Aviso::TIPO_RESPOSTA;
    if ($this->preferences->emailMode($uid, $category) !== AvisoPreferencias::EMAIL_IMMEDIATE) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_CANCELLED);
      return;
    }

    $topic = $this->entityTypeManager->getStorage('node')->load((int) $event['nid']);
    if (!$topic instanceof NodeInterface || !$this->accessPolicy->canViewTopic($topic, $recipient)
      || !$this->isCurrentFollower($topic, $uid)) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_CANCELLED);
      return;
    }
    $source = $this->resolveSource($event, $recipient, $topic);
    if ($source === NULL) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_CANCELLED);
      return;
    }
    $url = $this->domainPurposes->routeUrl('forum', 'entity.node.canonical', ['node' => $topic->id()]);
    if ($url === NULL) {
      $this->retryOrFail((int) $event['id'], (int) $event['email_attempts']);
      return;
    }
    $summary = $category === Aviso::TIPO_ATUALIZACAO
      ? 'Um tópico que você segue foi atualizado.'
      : 'Uma resposta foi publicada em um tópico que você segue.';
    try {
      $result = $this->mailer->deliver(new MailMessage(
        ForumNoticeMailType::ID,
        (string) $recipient->getEmail(),
        'pt-br',
        ['summary' => $summary, 'url' => $url->toString()],
        (string) $event['email_correlation'],
      ));
    }
    catch (\Throwable) {
      $this->retryOrFail((int) $event['id'], (int) $event['email_attempts']);
      return;
    }
    if ($result->sent) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_SENT);
      return;
    }
    if ($result->cancelled) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_CANCELLED);
      return;
    }
    if ($result->error === DeliveryError::Permanent) {
      $this->outbox->setStatus((int) $event['id'], ForumNoticeEmailOutbox::STATUS_FAILED);
      return;
    }
    $this->retryOrFail((int) $event['id'], (int) $event['email_attempts']);
  }

  /**
   * Resolves and authorizes the source event for its recipient. */
  private function resolveSource(array $event, UserInterface $recipient, NodeInterface $topic): CommentInterface|ForumPost|NodeInterface|null {
    $eventId = (int) $event['event_id'];
    $uid = (int) $recipient->id();
    switch ((string) $event['event_type']) {
      case 'comment':
        $comment = $this->entityTypeManager->getStorage('comment')->load($eventId);
        $commentedEntity = $comment instanceof CommentInterface ? $comment->getCommentedEntity() : NULL;
        if (!$comment instanceof CommentInterface || (int) $comment->getOwnerId() === $uid
          || !$commentedEntity instanceof NodeInterface
          || (int) $commentedEntity->id() !== (int) $topic->id()
          || !$this->accessPolicy->canViewComment($comment, $recipient)) {
          return NULL;
        }
        return $comment;

      case 'forum_post_revision':
        $post = $this->entityTypeManager->getStorage('aculta_forum_post')->loadRevision($eventId);
        if (!$post instanceof ForumPost || (int) $post->getOwnerId() === $uid
          || !$post->hasField('topic') || (int) $post->get('topic')->target_id !== (int) $topic->id()
          || !$this->accessPolicy->canViewPost($post, $recipient)) {
          return NULL;
        }
        return $post;

      case 'topic_revision':
        $revision = $this->entityTypeManager->getStorage('node')->loadRevision($eventId);
        if (!$revision instanceof NodeInterface || (int) $revision->id() !== (int) $topic->id()
          || (method_exists($revision, 'getRevisionUserId') && (int) $revision->getRevisionUserId() === $uid)
          || !$this->accessPolicy->canViewTopic($revision, $recipient)) {
          return NULL;
        }
        return $revision;
    }
    return NULL;
  }

  /**
   * Checks that the user still follows the topic when the email is delivered. */
  private function isCurrentFollower(NodeInterface $topic, int $uid): bool {
    $flag = $this->flagService->getFlagById('seguir_topico');
    if ($flag === NULL) {
      return FALSE;
    }
    foreach ($this->flagService->getEntityFlaggings($flag, $topic) as $flagging) {
      if ((int) $flagging->getOwnerId() === $uid) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Retries transient errors at most three times. */
  private function retryOrFail(int $id, int $attempts): void {
    $terminal = $attempts >= self::MAX_ATTEMPTS;
    $this->outbox->setStatus(
      $id,
      $terminal ? ForumNoticeEmailOutbox::STATUS_FAILED : ForumNoticeEmailOutbox::STATUS_PENDING,
    );
    if (!$terminal) {
      throw new \RuntimeException('Forum direct notice email delivery failed.');
    }
  }

}
