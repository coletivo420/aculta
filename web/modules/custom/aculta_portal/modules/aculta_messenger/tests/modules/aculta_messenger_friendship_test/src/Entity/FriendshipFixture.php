<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_friendship_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_user\Entity\AcultaFriendshipInterface;

/**
 * Isolated entity implementing the production relationship contract. */
#[ContentEntityType(
  id: 'aculta_friendship',
  label: new TranslatableMarkup('Friendship fixture'),
  handlers: [
    'access' => FriendshipFixtureAccessControlHandler::class,
  ],
  base_table: 'aculta_friendship',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  internal: TRUE,
)]
final class FriendshipFixture extends ContentEntityBase implements AcultaFriendshipInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc} */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Pair ID'))
      ->setSetting('max_length', 24)
      ->setRequired(TRUE);
    foreach ([
      'member_low' => new TranslatableMarkup('Lower member'),
      'member_high' => new TranslatableMarkup('Higher member'),
      'requester' => new TranslatableMarkup('Requester'),
      'recipient' => new TranslatableMarkup('Recipient'),
    ] as $name => $label) {
      $fields[$name] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($label)
        ->setSetting('target_type', 'user')
        ->setRequired(TRUE);
    }
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Status'))
      ->setSetting('max_length', 16)
      ->setRequired(TRUE)
      ->setDefaultValue('pending');
    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(new TranslatableMarkup('Created'));
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(new TranslatableMarkup('Changed'));
    return $fields;
  }

  /**
   * {@inheritdoc} */
  public function getMemberLowId(): int {
    return (int) $this->get('member_low')->target_id;
  }

  /**
   * {@inheritdoc} */
  public function getMemberHighId(): int {
    return (int) $this->get('member_high')->target_id;
  }

  /**
   * {@inheritdoc} */
  public function getRequesterId(): int {
    return (int) $this->get('requester')->target_id;
  }

  /**
   * {@inheritdoc} */
  public function getRecipientId(): int {
    return (int) $this->get('recipient')->target_id;
  }

  /**
   * {@inheritdoc} */
  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

}
