<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aculta_mailer\Access\MailLogAccessControlHandler;
use Drupal\views\EntityViewsData;

/**
 * Registro de um envio transacional: metadados e estado, sem corpo e sem destinatário em claro.
 *
 * O destinatário é guardado como HMAC; o corpo e os links de token nunca entram aqui (ver docs/LOGGING-PRIVACY.md).
 */
#[ContentEntityType(
  id: 'aculta_mail_log',
  label: new TranslatableMarkup('Registro de e-mail'),
  label_collection: new TranslatableMarkup('Registros de e-mail'),
  base_table: 'aculta_mail_log',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
    'label' => 'correlation_id',
  ],
  handlers: [
    'access' => MailLogAccessControlHandler::class,
    'views_data' => EntityViewsData::class,
  ],
  admin_permission: 'administer aculta_mailer',
)]
final class MailLog extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['correlation_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Correlação'))
      ->setSetting('max_length', 36)
      ->setRequired(TRUE);
    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Tipo'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);
    $fields['recipient_hash'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Destinatário (HMAC)'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);
    $fields['langcode'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Idioma'))
      ->setSetting('max_length', 12);
    $fields['status'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Estado'))
      ->setSetting('max_length', 16)
      ->setRequired(TRUE);
    $fields['transport'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Transporte'))
      ->setSetting('max_length', 32);
    $fields['attempts'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Tentativas'))
      ->setRequired(TRUE)
      ->setDefaultValue(1);
    $fields['error_code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Código do erro'))
      ->setSetting('max_length', 64);
    $fields['error_message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Texto completo da falha'));
    $fields['sent'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Aceito pelo sistema de e-mail em'));
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Registrado em'));
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'));
    return $fields;
  }

}
