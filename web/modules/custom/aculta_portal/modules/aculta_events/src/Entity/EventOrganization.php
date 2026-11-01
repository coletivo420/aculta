<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Drupal\views\EntityViewsData;

/**
 * Organização Cultural: produtora e coorganizadora de eventos (ARQ-05).
 *
 * Possui perfil público canônico no purpose eventos. Endereço completo e CEP
 * não entram na projeção pública padrão; cidade e UF podem ser ocultadas.
 */
#[ContentEntityType(
  id: 'aculta_event_organization',
  label: new TranslatableMarkup('Organização'),
  label_collection: new TranslatableMarkup('Organizações'),
  label_singular: new TranslatableMarkup('organização'),
  label_plural: new TranslatableMarkup('organizações'),
  handlers: [
    'access' => 'Drupal\aculta_events\Access\EventOrganizationAccessControlHandler',
    'form' => [
      'default' => 'Drupal\aculta_events\Form\EventOrganizationForm',
      'add' => 'Drupal\aculta_events\Form\EventOrganizationForm',
      'edit' => 'Drupal\aculta_events\Form\EventOrganizationForm',
    ],
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'aculta_event_organization',
  admin_permission: 'administer aculta events',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'title',
    'published' => 'status',
  ],
  links: [
    'canonical' => '/organizacoes/{slug}',
    'edit-form' => '/culturas/organizacoes/{aculta_event_organization}/editar',
  ],
)]
final class EventOrganization extends ContentEntityBase implements EntityOwnerInterface, EntityPublishedInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public function getOwner(): ?UserInterface {
    $titular = $this->get('titular')->entity;
    if ($titular !== NULL) {
      return $titular;
    }
    return $this->get('field_organizador')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): ?int {
    $titularId = $this->get('titular')->target_id;
    if ($titularId !== NULL && $titularId !== '') {
      return (int) $titularId;
    }
    $orgId = $this->get('field_organizador')->target_id;
    return $orgId !== NULL && $orgId !== '' ? (int) $orgId : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): static {
    $this->set('titular', $uid);
    $this->set('field_organizador', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): static {
    $this->set('titular', $account->id());
    $this->set('field_organizador', $account->id());
    return $this;
  }

  /**
   * Verifica se a organização é reivindicada por um titular.
   */
  public function isClaimed(): bool {
    return !empty($this->get('reivindicada')->value);
  }

  /**
   * Verifica se a organização é não reivindicada.
   */
  public function isUnclaimed(): bool {
    return !$this->isClaimed();
  }

  /**
   * Verifica se a exibição pública de cidade e UF deve ser omitida.
   */
  public function shouldHideLocation(): bool {
    return !empty($this->get('ocultar_localidade')->value);
  }

  /**
   * Retorna o slug público estável da organização.
   */
  public function getSlug(): string {
    return (string) $this->get('field_slug')->value;
  }

  /**
   * Retorna o status da reivindicação.
   */
  public function getClaimStatus(): string {
    return (string) $this->get('reivindicacao_status')->value;
  }

  /**
   * Retorna a URL canônica absoluta no purpose eventos.
   */
  public function toCanonicalUrl(?DomainPurposeManager $purposes = NULL): Url {
    $slug = $this->getSlug();
    if ($purposes !== NULL && $slug !== '') {
      $canonical = $purposes->canonicalRouteUrl('eventos', 'aculta_events.organization_page', ['slug' => $slug]);
      if ($canonical !== NULL) {
        return $canonical;
      }
    }
    return Url::fromRoute('aculta_events.organization_page', ['slug' => $slug]);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Nome da Organização'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 0])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_slug'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Slug canônico'))
      ->setSetting('max_length', 255);

    $fields['body'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Descrição e apresentação'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE);

    $fields['imagem'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Imagem ou logotipo oficial'))
      ->setSetting('max_length', 512)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 15])
      ->setDisplayConfigurable('form', TRUE);

    $fields['links'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Links oficiais (sites, redes)'))
      ->setDisplayOptions('form', ['type' => 'string_textarea', 'weight' => 20])
      ->setDisplayConfigurable('form', TRUE);

    $fields['localidade'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Cidade'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 25])
      ->setDisplayConfigurable('form', TRUE);

    $fields['uf'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('UF'))
      ->setSetting('max_length', 2)
      ->setDisplayOptions('form', ['type' => 'string_textfield', 'weight' => 30])
      ->setDisplayConfigurable('form', TRUE);

    $fields['ocultar_localidade'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Ocultar cidade e UF do perfil público e das buscas'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', ['type' => 'boolean_checkbox', 'weight' => 35])
      ->setDisplayConfigurable('form', TRUE);

    $fields['field_status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Situação'))
      ->setDefaultValue('publicado')
      ->setSetting('allowed_values', [
        'rascunho' => new TranslatableMarkup('Rascunho'),
        'publicado' => new TranslatableMarkup('Publicado'),
      ])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => 40])
      ->setDisplayConfigurable('form', TRUE);

    $fields['reivindicada'] = BaseFieldDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Organização reivindicada'))
      ->setDefaultValue(FALSE);

    $fields['reivindicacao_status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Situação da reivindicação'))
      ->setSetting('max_length', 32)
      ->setDefaultValue('nao_reivindicada');

    $fields['titular'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Titular'))
      ->setSetting('target_type', 'user');

    $fields['pending_owner_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Novo titular pendente de aceite'))
      ->setSetting('target_type', 'user');

    $fields['field_organizador'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Criador / Organizador inicial'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));

    return $fields;
  }

}
