<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\Core\Url;

/**
 * URL pública do código QR, usada na exportação (SVG, PNG, WebP e PDF).
 *
 * A implementação padrão usa o domínio do purpose links (aculta_portal). Sem ele, devolve NULL e a exportação
 * recusa gerar o arquivo. Testes de Kernel substituem a implementação pelo container.
 */
interface QrCodePublicUrlInterface {

  /**
   * URL pública do código, ou NULL quando não há domínio configurado.
   */
  public function publicUrl(QrCode $entity): ?Url;

}
