<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Email;

use Drupal\aculta_mail_confirmer\Entity\AcultaMailConfirmation;

/**
 * Pedido de confirmação de e-mail. O link de uso único vale 24 horas (SingleUseLinkPolicy, decisão do responsável).
 *
 * Um pedido novo substitui o anterior, com novo hash, e o conteúdo guardado do pedido antigo é cancelado (ver
 * docs/portal/LINKS-DE-USO-UNICO.md).
 */
class EmailConfirmationEntity extends AcultaMailConfirmation {

  public function isExpired() {
    return SingleUseLinkPolicy::isExpired((int) $this->getCreatedTime(), time());
  }

}
