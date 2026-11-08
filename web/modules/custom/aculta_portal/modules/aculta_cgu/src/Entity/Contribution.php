<?php

declare(strict_types=1);

namespace Drupal\aculta_cgu\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * Private, revisionable community proposal independent of official content.
 */
#[ContentEntityType(
  id: 'aculta_contribution',
  label: new TranslatableMarkup('Community proposal'),
  label_collection: new TranslatableMarkup('Community proposals'),
  handlers: [
    'access' => ContributionAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'aculta_contribution',
  data_table: 'aculta_contribution_field_data',
  revision_table: 'aculta_contribution_revision',
  revision_data_table: 'aculta_contribution_field_revision',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'uuid' => 'uuid',
    'langcode' => 'langcode',
    'label' => 'subject',
    'owner' => 'uid',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
  admin_permission: 'administer aculta contributions',
  collection_permission: 'view any aculta contributions',
  show_revision_ui: FALSE,
  translatable: TRUE,
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_contribution_list'],
)]
final class Contribution extends RevisionableContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * Human-readable state labels keyed by stable lifecycle state IDs.
   *
   * @var array<string, string>
   */
  public const STATES = [
    'submitted' => 'Submitted',
    'needs_changes' => 'Needs changes',
    'accepted' => 'Accepted',
    'declined' => 'Declined',
    'withdrawn' => 'Withdrawn',
    'conflict' => 'Conflict',
  ];

  /**
   * Returns the structured proposal values.
   *
   * @return array<string, mixed>
   *   Proposed field values keyed by the adapter's stable allowlisted names.
   */
  public function getPayload(): array {
    $item = $this->get('payload')->first();
    $value = $item?->getValue() ?? [];
    return is_array($value) ? $value : [];
  }

  /**
   * Returns private, source-owned context that is not applied to the target.
   *
   * @return array<string, mixed>
   *   Validated source context, such as citations or a domain-specific intent.
   */
  public function getSourceData(): array {
    $item = $this->get('source_data')->first();
    $value = $item?->getValue() ?? [];
    return is_array($value) ? $value : [];
  }

  /**
   * Returns the reviewer decision details for this proposal revision.
   *
   * @return array<string, mixed>
   *   Selected allowlisted fields and any validated application choices.
   */
  public function getDecisionData(): array {
    $item = $this->get('decision_data')->first();
    $value = $item?->getValue() ?? [];
    return is_array($value) ? $value : [];
  }

  /**
   * {@inheritdoc} */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Summary'))
      ->setSetting('max_length', 255)->setRequired(TRUE)->setRevisionable(TRUE)->setTranslatable(TRUE);
    $fields['source'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Source adapter'))
      ->setSetting('max_length', 128)->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['source_module'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Source module'))
      ->setSetting('max_length', 128)->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['purpose'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Domain purpose'))
      ->setSetting('max_length', 64)->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['intent'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Intent'))
      ->setSetting('allowed_values', ['create' => 'Create', 'update' => 'Update'])
      ->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['target_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target entity type'))
      ->setSetting('max_length', 128)->setRevisionable(TRUE);
    $fields['target_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Target UUID'))
      ->setSetting('max_length', 128)->setRevisionable(TRUE);
    $fields['base_revision'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Base revision fingerprint'))
      ->setSetting('max_length', 255)->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['payload'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Proposed values'))->setRevisionable(TRUE);
    $fields['source_data'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Private source context'))->setRevisionable(TRUE);
    $fields['decision_data'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Decision details'))->setRevisionable(TRUE);
    $fields['rationale'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Rationale'))->setRequired(TRUE)->setRevisionable(TRUE)->setTranslatable(TRUE);
    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('State'))->setSetting('allowed_values', static::STATES)
      ->setDefaultValue('submitted')->setRequired(TRUE)->setRevisionable(TRUE);
    $fields['decision_note'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Decision note'))->setRevisionable(TRUE)->setTranslatable(TRUE);
    $fields['idempotency_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Idempotency key hash'))->setSetting('max_length', 64);
    $fields['created'] = BaseFieldDefinition::create('created')->setLabel(new TranslatableMarkup('Submitted'))->setRevisionable(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel(new TranslatableMarkup('Changed'))->setRevisionable(TRUE);
    return $fields;
  }

}
