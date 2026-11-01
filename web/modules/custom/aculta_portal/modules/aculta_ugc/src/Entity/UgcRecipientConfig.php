<?php

declare(strict_types=1);

namespace Drupal\aculta_ugc\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Destinatários de notificação de moderação por purpose (D-03, C-01).
 *
 * Cada purpose (área) possui seus moderadores configurados em entidade
 * de configuração própria, substituindo listas estáticas e ECA.
 */
#[ConfigEntityType(
  id: 'aculta_ugc_recipients',
  label: new TranslatableMarkup('Destinatários de notificação UGC'),
  config_prefix: 'recipients',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  config_export: [
    'id',
    'label',
    'emails',
  ],
  admin_permission: 'moderar denuncias ugc',
)]
final class UgcRecipientConfig extends ConfigEntityBase {

  /**
   * The purpose identifier.
   */
  protected string $id = '';

  /**
   * The label.
   */
  protected string $label = '';

  /**
   * E-mails of the moderators for this purpose.
   *
   * @var string[]
   */
  protected array $emails = [];

  /**
   * @return string[]
   */
  public function getEmails(): array {
    return $this->emails;
  }

  /**
   * @param string[] $emails
   */
  public function setEmails(array $emails): self {
    $this->emails = array_values(array_unique(array_filter($emails)));
    return $this;
  }

}