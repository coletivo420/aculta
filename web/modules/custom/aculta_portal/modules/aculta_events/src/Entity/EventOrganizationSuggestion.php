<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Pending proposal to update an unclaimed cultural organization.
 */
#[ContentEntityType(
  id: 'aculta_event_org_suggestion',
  label: new TranslatableMarkup('Sugestão de alteração de organização'),
  label_collection: new TranslatableMarkup('Sugestões de alteração de organizações'),
  label_singular: new TranslatableMarkup('sugestão de alteração'),
  label_plural: new TranslatableMarkup('sugestões de alteração'),
  base_table: 'aculta_event_org_suggestion',
  admin_permission: 'administer aculta events',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
  ],
  handlers: [
    'storage' => 'Drupal\\Core\\Entity\\Sql\\SqlContentEntityStorage',
    'access' => 'Drupal\\aculta_events\\Access\\EventOrganizationSuggestionAccessControlHandler',
  ],
)]
final class EventOrganizationSuggestion extends ContentEntityBase {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Organização'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['field_organization'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Organização'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'aculta_event_organization');

    $fields['field_submitted_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Enviada por'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user');

    $fields['field_changes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Alterações propostas'))
      ->setRequired(TRUE);

    $fields['field_original_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Impressão digital do perfil no envio'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['field_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Situação'))
      ->setRequired(TRUE)
      ->setDefaultValue('pending')
      ->setSetting('allowed_values', [
        'pending' => new TranslatableMarkup('Pendente'),
        'approved' => new TranslatableMarkup('Aprovada'),
        'rejected' => new TranslatableMarkup('Rejeitada'),
        'stale' => new TranslatableMarkup('Desatualizada'),
      ]);

    $fields['field_reviewed_by'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Revisada por'))
      ->setSetting('target_type', 'user');

    $fields['field_review_note'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Nota da revisão'));

    $fields['field_reviewed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Revisada em'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Enviada em'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterada em'));

    return $fields;
  }

}
