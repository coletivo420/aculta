<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Entity;

use Drupal\aculta_qrcode\Access\QrCodeAccessControlHandler;
use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Código QR dinâmico: uma URL estável do ACULTA que redireciona a um destino.
 *
 * Guarda destino, código opaco, preset e janela de validade. Não guarda imagem:
 * SVG e PNG são gerados a partir da entidade. O histórico é revisionável.
 */
#[ContentEntityType(
  id: 'aculta_qr_code',
  label: new TranslatableMarkup('Código QR dinâmico'),
  label_collection: new TranslatableMarkup('Códigos QR dinâmicos'),
  handlers: [
    'access' => QrCodeAccessControlHandler::class,
  ],
  base_table: 'aculta_qr_code',
  revision_table: 'aculta_qr_code_revision',
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'uuid' => 'uuid',
    'label' => 'title',
    'owner' => 'uid',
    'published' => 'status',
  ],
  revision_metadata_keys: [
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
  admin_permission: 'administer aculta qr codes',
  show_revision_ui: FALSE,
)]
final class QrCode extends EditorialContentEntityBase implements EntityOwnerInterface {

  use EntityOwnerTrait;

  /**
   * Código público de 12 caracteres (minúsculas e dígitos).
   */
  public function getCode(): string {
    return (string) $this->get('code')->value;
  }

  /**
   * Destino absoluto `http` ou `https`, validado na gravação.
   */
  public function getTargetUrl(): string {
    return (string) $this->get('target_url')->value;
  }

  /**
   * Identificador do preset visual associado.
   */
  public function getPresetId(): string {
    return (string) $this->get('preset')->value;
  }

  /**
   * Início da validade em timestamp Unix, ou NULL para sem início.
   */
  public function getValidFrom(): ?int {
    $value = $this->get('valid_from')->value;
    return $value === NULL ? NULL : (int) $value;
  }

  /**
   * Fim da validade em timestamp Unix, ou NULL para sem fim.
   */
  public function getValidUntil(): ?int {
    $value = $this->get('valid_until')->value;
    return $value === NULL ? NULL : (int) $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Título'))
      ->setDescription(new TranslatableMarkup('Nome administrativo do código. Não aparece no QR Code.'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Código público'))
      ->setDescription(new TranslatableMarkup('Identificador gerado automaticamente. Não muda depois de criado.'))
      ->setSetting('max_length', 12)
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->addPropertyConstraints('value', [
        'Regex' => [
          'pattern' => '/^[a-z0-9]{12}$/D',
          'message' => new TranslatableMarkup('O código deve conter 12 letras minúsculas ou números.'),
        ],
      ]);

    $fields['target_url'] = BaseFieldDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('Destino'))
      ->setDescription(new TranslatableMarkup('URL absoluta http ou https, sem credenciais. Para onde o código redireciona.'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->addPropertyConstraints('value', ['AcultaQrCodeTarget' => []]);

    $fields['preset'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Preset visual'))
      ->setSetting('max_length', 32)
      ->setDefaultValue('aculta_padrao')
      ->setRevisionable(TRUE);

    $fields['valid_from'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Válido a partir de'))
      ->setDescription(new TranslatableMarkup('Vazio para válido desde a publicação.'))
      ->setRevisionable(TRUE);

    $fields['valid_until'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Válido até'))
      ->setDescription(new TranslatableMarkup('Vazio para sem data de término. Após essa data, o código responde 404.'))
      ->setRevisionable(TRUE);

    $fields['uid']->setRevisionable(TRUE);
    $fields['status']->setRevisionable(TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Criado em'))
      ->setRevisionable(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(new TranslatableMarkup('Alterado em'))
      ->setRevisionable(TRUE);

    return $fields;
  }

}
