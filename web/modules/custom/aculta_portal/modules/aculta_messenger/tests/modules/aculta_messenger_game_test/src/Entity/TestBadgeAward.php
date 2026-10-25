<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger_game_test\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Kernel fixture for canonical achievement source entities.
 */
#[ContentEntityType(
  id: 'aculta_badge_award',
  label: new TranslatableMarkup('Test achievement grant'),
  base_table: 'aculta_badge_award',
  entity_keys: ['id' => 'id', 'uuid' => 'uuid'],
)]
final class TestBadgeAward extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Recipient'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);
    $fields['badge'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Badge'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);
    $fields['concedido_em'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Granted at'))
      ->setRequired(TRUE);
    $fields['revogado_em'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Revoked at'));
    return $fields;
  }

}
