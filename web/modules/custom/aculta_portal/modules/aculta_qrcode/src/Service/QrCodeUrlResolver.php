<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_portal\Domain\DomainSiteManager;
use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\Core\Url;

/**
 * Monta a URL pública estável de um código QR dinâmico.
 *
 * A dependência do `aculta_portal` é opcional no container: sem ela, o serviço
 * não monta URL. Assim, os testes Kernel do módulo não carregam o Portal.
 *
 * O host vem do `DomainSiteManager`, nunca de concatenação. O purpose
 * `links` é o mesmo do redirecionamento curto do `aculta_linkweed`.
 */
final class QrCodeUrlResolver implements QrCodePublicUrlInterface {

  /**
   * Purpose que atende o redirecionamento público.
   */
  public const PURPOSE = 'links';

  /**
   * Cria o serviço.
   */
  public function __construct(private readonly ?DomainSiteManager $siteManagers) {}

  /**
   * Retorna a URL canônica `/qr/{codigo}` no domínio do purpose, ou NULL.
   */
  public function publicUrl(QrCode $entity): ?Url {
    if ($this->siteManagers === NULL) {
      return NULL;
    }
    return $this->siteManagers->canonicalPathUrl(self::PURPOSE, '/qr/' . $entity->getCode());
  }

}
