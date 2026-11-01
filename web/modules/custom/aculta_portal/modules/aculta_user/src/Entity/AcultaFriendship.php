<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Defines the private relationship record shared by a member pair. */
#[ContentEntityType(
  id: 'aculta_friendship',
  label: new TranslatableMarkup('Relacionamento entre membros'),
  handlers: [
    'access' => AcultaFriendshipAccessControlHandler::class,
  ],
  base_table: 'aculta_friendship',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  admin_permission: 'administer aculta friendships',
  internal: TRUE,
)]
final class AcultaFriendship extends ContentEntityBase implements AcultaFriendshipInterface {

  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    // A canonical pair key is the entity's primary ID. The database therefore
    // enforces one relationship row per pair, including concurrent requests.
    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Identificador do par'))
      ->setSetting('max_length', 24)
      ->setSetting('is_ascii', TRUE)
      ->setReadOnly(TRUE)
      ->setRequired(TRUE);
    $fields['member_low'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Membro'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['member_high'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Outro membro'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['requester'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Solicitante'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['recipient'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Destinatário'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Estado'))
      ->setSetting('max_length', 16)
      ->setRequired(TRUE)
      ->setDefaultValue('pending');
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Atualizado'));

    return $fields;
  }

  public function getMemberLowId(): int {
    return (int) $this->get('member_low')->target_id;
  }

  public function getMemberHighId(): int {
    return (int) $this->get('member_high')->target_id;
  }

  public function getRequesterId(): int {
    return (int) $this->get('requester')->target_id;
  }

  public function getRecipientId(): int {
    return (int) $this->get('recipient')->target_id;
  }

  public function getStatus(): string {
    return (string) $this->get('status')->value;
  }

}
