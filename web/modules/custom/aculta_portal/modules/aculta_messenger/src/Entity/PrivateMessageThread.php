<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the Private Message Thread entity.
 *
 * @ContentEntityType(
 *   id = "private_message_thread",
 *   label = @Translation("Private Message Thread"),
 *   handlers = {
 *     "view_builder" = "Drupal\aculta_messenger\Entity\Builder\PrivateMessageThreadViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\aculta_messenger\Entity\Access\PrivateMessageThreadAccessControlHandler",
 *     "form" = {
 *       "delete" = "Drupal\aculta_messenger\Form\PrivateMessageThreadDeleteForm",
 *       "clear_personal_history" = "Drupal\aculta_messenger\Form\PrivateMessageThreadClearPersonalHistoryForm",
 *     },
 *   },
 *   base_table = "private_message_threads",
 *   admin_permission = "administer private messages",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/private-messages/{private_message_thread}",
 *     "delete-form" = "/private-messages/{private_message_thread}/delete",
 *   },
 *   field_ui_base_route = "aculta_messenger.pm_thread_settings_page",
 * )
 */
class PrivateMessageThread extends ContentEntityBase implements PrivateMessageThreadInterface {

  /**
   * {@inheritdoc}
   */
  public function addMember(AccountInterface $account): PrivateMessageThreadInterface {
    if (!$this->isMember($account->id())) {
      $this->get('members')->appendItem($account->id());
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addMemberById(int|string $id): PrivateMessageThreadInterface {
    if (!$this->isMember($id)) {
      $this->get('members')->appendItem($id);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembers(): array {
    /** @var array<\Drupal\user\UserInterface> $members */
    $members = $this->get('members')->referencedEntities();
    return $members;
  }

  /**
   * {@inheritdoc}
   */
  public function getMemberIds(): array {
    $members = [];
    /** @var array<array<string, int|string>> $member_values */
    $member_values = $this->get('members')->getValue();
    foreach ($member_values as $member_item) {
      $members[] = (int) $member_item['target_id'];
    }
    return $members;
  }

  /**
   * {@inheritdoc}
   */
  public function getMembersId(): array {
    return $this->getMemberIds();
  }

  /**
   * {@inheritdoc}
   */
  public function isMember(int|string $id): bool {
    return in_array((int) $id, $this->getMemberIds(), TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function addMessage(PrivateMessageInterface $privateMessage): PrivateMessageThreadInterface {
    $this->get('private_messages')->appendItem($privateMessage->id());
    // Allow other modules to react on a new message in thread.
    // @todo Inject when entity dependency serialization core issues resolved.
    \Drupal::moduleHandler()->invokeAll(
      'private_message_new_message',
      [$privateMessage, $this]
    );
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addMessageById(int|string $id): PrivateMessageThreadInterface {
    $this->get('private_messages')->appendItem($id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages(bool $includeBlocked = FALSE): array {
    /** @var array<\Drupal\aculta_messenger\Entity\PrivateMessageInterface> $referenced */
    $referenced = $this->get('private_messages')->referencedEntities();
    /** @var array<\Drupal\aculta_messenger\Entity\PrivateMessageInterface> $messages */
    $messages = array_values($referenced);
    if ($includeBlocked) {
      return $messages;
    }

    $bannedUsers = \Drupal::service('aculta_messenger.pm.ban_manager')->getBannedUsers(\Drupal::currentUser()->id());
    /** @var array<\Drupal\aculta_messenger\Entity\PrivateMessageInterface> $visible */
    $visible = array_filter($messages, function ($message, $index) use ($bannedUsers) {
      // The first message is always displayed, regardless of the ban status.
      // This is to ensure that the thread display is not broken.
      return $index === 0 || !in_array($message->getOwnerId(), $bannedUsers);
    }, ARRAY_FILTER_USE_BOTH);
    return $visible;
  }

  /**
   * {@inheritdoc}
   */
  public function filterNewestMessages() {

    $messages = $this->getMessages();
    if (count($messages) > \Drupal::config('private_message_thread.settings')->get('message_count')) {
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface<\Drupal\Core\Entity\EntityInterface> $list */
      $list = $this->get('private_messages');
      /** @var int $message_count */
      $message_count = \Drupal::config('private_message_thread.settings')->get('message_count');
      $filtered_messages = array_slice($messages, -1 * $message_count);
      /** @var \Drupal\aculta_messenger\Entity\PrivateMessageInterface $first_message */
      $first_message = array_shift($filtered_messages);
      $first_key = $first_message->id();
      foreach ($list->referencedEntities() as $list_item) {
        if ($list_item->id() < $first_key) {
          $list->removeItem(0);
        }
        else {
          break;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNewestMessageCreationTimestamp(): int {
    $messages = $this->getMessages();
    $last_timestamp = 0;
    foreach ($messages as $message) {
      /** @var int|string|null $created */
      $created = $message->get('created')->value;
      $creation_date = (int) $created;
      $last_timestamp = max($creation_date, $last_timestamp);
    }

    return $last_timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function addHistoryRecord(AccountInterface $account): void {
    \Drupal::database()->insert('aculta_pm_thread_history')
      ->fields([
        'uid' => $account->id(),
        'thread_id' => $this->id(),
      ])->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastAccessTimestamp(AccountInterface $account): int {
    $query = \Drupal::database()->select('aculta_pm_thread_history', 'aculta_pm_thread_history')
      ->condition('uid', $account->id())
      ->condition('thread_id', $this->id())
      ->fields('aculta_pm_thread_history', ['access_timestamp']);
    /** @var \Drupal\Core\Database\StatementInterface<mixed> $statement */
    $statement = $query->execute();
    /** @var int|string|null $timestamp */
    $timestamp = $statement->fetchField();
    return (int) $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastAccessTime(AccountInterface $account): PrivateMessageThreadInterface {
    \Drupal::database()->update('aculta_pm_thread_history')
      ->condition('uid', $account->id())
      ->condition('thread_id', $this->id())
      ->fields(['access_timestamp' => \Drupal::time()->getRequestTime()])
      ->execute();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastDeleteTimestamp(AccountInterface $account): int {
    $query = \Drupal::database()->select('aculta_pm_thread_history', 'aculta_pm_thread_history')
      ->condition('uid', $account->id())
      ->condition('thread_id', $this->id())
      ->fields('aculta_pm_thread_history', ['delete_timestamp']);
    /** @var \Drupal\Core\Database\StatementInterface<mixed> $statement */
    $statement = $query->execute();
    /** @var int|string|null $timestamp */
    $timestamp = $statement->fetchField();
    return (int) $timestamp;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastDeleteTime(AccountInterface $account): PrivateMessageThreadInterface {
    \Drupal::database()->update('aculta_pm_thread_history')
      ->condition('uid', $account->id())
      ->condition('thread_id', $this->id())
      ->fields(['delete_timestamp' => \Drupal::time()->getRequestTime()])
      ->execute();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    // @todo This should be investigated.
    $this->clearCacheTags();
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities): void {
    parent::preDelete($storage, $entities);

    // Delete the thread from the database, as well as all reference entities.
    foreach ($entities as $entity) {
      assert($entity instanceof PrivateMessageThreadInterface);
      $messages = $entity->getMessages(TRUE);
      foreach ($messages as $message) {
        $message->delete();
      }
      \Drupal::database()->delete('aculta_pm_thread_history')
        ->condition('thread_id', $entity->id())
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);
    foreach ($entities as $entity) {
      assert($entity instanceof PrivateMessageThreadInterface);
      $entity->clearCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearAccountHistory(?AccountInterface $account = NULL): void {
    if (!$account) {
      $account = \Drupal::currentUser();
    }
    // Update thread deleted time for account.
    $this->updateLastDeleteTime($account);

    // Get timestamp when last message was created.
    $last_creation_timestamp = $this->getNewestMessageCreationTimestamp();

    // Query thread history table to get deleted timestamp.
    $query = \Drupal::database()->select('aculta_pm_thread_history', 'aculta_pm_thread_history')
      ->condition('thread_id', $this->id());
    $query->addExpression('MIN(delete_timestamp)', 'min_deleted');
    /** @var \Drupal\Core\Database\StatementInterface<mixed> $statement */
    $statement = $query->execute();
    $min_deleted = $statement->fetchField();

    // If no messages have been created after every member has deleted thread.
    if ($min_deleted >= $last_creation_timestamp) {
      $this->delete();
    }
    $this->clearCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function filterUserDeletedMessages(AccountInterface $account): array {
    $last_delete_timestamp = $this->getLastDeleteTimestamp($account);
    $messages = $this->getMessages();
    $start_index = FALSE;
    foreach ($messages as $index => $message) {
      if ($message->getCreatedTime() > $last_delete_timestamp) {
        /** @var int $start_index */
        $start_index = $index;

        break;
      }
    }

    if ($start_index !== FALSE) {
      return array_slice($messages, $start_index);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatedTime(): int {
    /** @var int|string|null $updated */
    $updated = $this->get('updated')->value;
    return (int) $updated;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCacheTags(): void {
    $tags = [];

    foreach ($this->getMembers() as $member) {
      $tags[] = 'private_message_inbox_block:uid:' . $member->id();
      $tags[] = 'private_message_notification_block:uid:' . $member->id();
      $tags[] = 'private_message:status:uid:' . $member->id();
    }

    // Invalidate cache for list of private message threads.
    $tags[] = 'private_message_thread_list';

    Cache::invalidateTags($tags);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    /** @var \Drupal\Core\Field\BaseFieldDefinition $id_field */
    $id_field = $fields['id'];
    $id_field->setLabel(t('Private message thread ID'))
      ->setDescription(t('The private message thread ID.'));

    /** @var \Drupal\Core\Field\BaseFieldDefinition $uuid_field */
    $uuid_field = $fields['uuid'];
    $uuid_field->setDescription(t('The custom private message thread UUID.'));

    $fields['updated'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Updated'))
      ->setDescription(t('The most recent time at which the thread was updated'));

    // Member(s) of the private message thread.
    // Entity reference field, holds the reference to user objects.
    $fields['members'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Member(s)'))
      ->setDescription(t('The member(s) of the private message thread'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->addConstraint('private_message_thread_member')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'match_limit' => 10,
          'max_members' => 0,
          'size' => 60,
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Subject.
    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject'))
      ->setSetting('max_length', 64)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // Private messages in this thread.
    // Entity reference field, holds the reference to user objects.
    $fields['private_messages'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Messages'))
      ->setDescription(t('The private messages that belong to this thread'))
      ->setSetting('target_type', 'private_message')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = parent::getCacheTags();
    $tags[] = 'private_message_thread:' . $this->id() . ':view:uid:' . \Drupal::currentUser()->id();

    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);
    if (!$update) {
      $members = $this->getMembers();
      foreach ($members as $member) {
        $this->addHistoryRecord($member);
      }
    }
  }

}
