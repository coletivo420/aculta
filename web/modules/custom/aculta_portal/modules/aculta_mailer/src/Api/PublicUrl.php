<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Api;


use Drupal\aculta_portal\Domain\SiteKey;
/**
 * URL absoluta de caminhos do site público para e-mails, pelo propósito principal do ambiente.
 *
 * O domínio vem do serviço de propósitos do aculta_portal, recebido pelo id e sem referência à classe: o Portal depende
 * deste módulo, e a referência direta criaria um ciclo. Sem o Portal habilitado, a URL é vazia.
 */
final class PublicUrl implements PublicUrlInterface {

  public function __construct(
    private readonly ?object $siteManagers = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function absolute(string $path): string {
    if ($this->siteManagers === NULL || !method_exists($this->siteManagers, 'pathUrl')) {
      return '';
    }
    $url = $this->siteManagers->pathUrl(SiteKey::Main, $path);
    return $url === NULL ? '' : $url->setAbsolute(TRUE)->toString();
  }

}
