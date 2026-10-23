<?php

declare(strict_types=1);

namespace Drupal\aculta_wiki\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\views\EntityViewsData;

/**
 * A private, revisioned contribution proposal for an existing Wiki entry.
 */
#[ContentEntityType(
  id: 'aculta_wiki_proposal',
  label: new TranslatableMarkup('Proposta para verbete da Wiki'),
  label_collection: new TranslatableMarkup('Propostas para verbetes da Wiki'),
  handlers: [
    'access' => WikiProposalAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'aculta_wiki_proposal',
  data_table: 'aculta_wiki_proposal_field_data',
  revision_table: 'aculta_wiki_proposal_revision',
  revision_data_table: 'aculta_wiki_proposal_field_revision',
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
  admin_permission: 'administer wiki proposals',
  collection_permission: 'view any wiki proposals',
  show_revision_ui: FALSE,
  translatable: TRUE,
  common_reference_target: TRUE,
  list_cache_contexts: ['user', 'user.permissions'],
  list_cache_tags: ['aculta_wiki_proposal_list'],
)]
final class WikiProposal extends RevisionableContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * Allowlisted contribution intents.
   *
   * These values are stable storage identifiers, not user supplied routes.
   *
   * @var array<string, string>
   */
  public const INTENTS = [
    'correction' => 'Corrigir informação',
    'reference' => 'Acrescentar fonte',
    'improve' => 'Melhorar conteúdo',
    'image' => 'Sugerir imagem',
  ];

  /**
   * Proposal lifecycle values. Decisions are recorded in entity revisions.
   *
   * @var array<string, string>
   */
  public const STATES = [
    'submitted' => 'Enviada',
    'needs_changes' => 'Precisa de ajustes',
    'accepted' => 'Aceita',
    'declined' => 'Recusada',
    'withdrawn' => 'Retirada',
  ];

  /**
   * Editorial fields that authors may propose for a Wiki entry.
   *
   * The editor may apply any subset when accepting a proposal.
   *
   * @var array<string, string>
   */
  public const EDITORIAL_FIELDS = [
    'title' => 'Título',
    'field_wiki_summary' => 'Resumo',
    'body' => 'Corpo',
    'field_wiki_references' => 'Referências',
    'field_wiki_categories' => 'Categorias',
    'field_wiki_related' => 'Ver também',
    'field_wiki_image' => 'Imagem',
  ];

  /**
   * Returns the structured editorial values stored with this proposal.
   *
   * @return array<string, mixed>
   *   Proposed field values keyed by the allowlisted Node field name.
   */
  public function getProposedFields(): array {
    $item = $this->get('proposed_fields')->first();
    if ($item === NULL) {
      return [];
    }
    $value = $item->getValue();
    return is_array($value) ? $value : [];
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']->setRequired(TRUE)->setRevisionable(TRUE);

    $fields['subject'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Resumo da proposta'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['target'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Verbete'))
      ->setSetting('target_type', 'node')
      ->setSetting('handler', 'default:node')
      ->setSetting('handler_settings', ['target_bundles' => ['wiki_entry' => 'wiki_entry']])
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['target_revision_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Revisão-base do verbete'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['intent'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Tipo de contribuição'))
      ->setSetting('allowed_values', static::INTENTS)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['proposed_text'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Alteração sugerida'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['proposed_fields'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Campos propostos'))
      ->setDescription(new TranslatableMarkup('Valores estruturados para campos editoriais permitidos da Wiki.'))
      ->setRevisionable(TRUE);

    $fields['rationale'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Justificativa'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['source_uri'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Fonte'))
      ->setCardinality(-1)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['state'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Estado'))
      ->setSetting('allowed_values', static::STATES)
      ->setDefaultValue('submitted')
      ->setRequired(TRUE)
      ->setRevisionable(TRUE);

    $fields['decision_note'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Retorno editorial'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Enviada em'))
      ->setRevisionable(TRUE);
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterada em'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
