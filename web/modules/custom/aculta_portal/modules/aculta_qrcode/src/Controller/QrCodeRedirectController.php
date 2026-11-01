<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Controller;

use Drupal\aculta_qrcode\Service\QrCodeManager;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redireciona `/qr/{codigo}` ao destino publicado e dentro da validade.
 *
 * Sem registro de leitura nesta versão. O cache do redirecionamento depende do
 * código e expira junto com a janela de validade.
 */
final class QrCodeRedirectController {

  /**
   * Cria o controlador.
   */
  public function __construct(
    private readonly QrCodeManager $manager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Responde 302 para o destino, ou 404 quando o código não está ativo.
   */
  public function redirectByCode(string $codigo): TrustedRedirectResponse {
    $now = $this->time->getRequestTime();
    $entity = $this->manager->findActive($codigo, $now);
    if ($entity === NULL) {
      throw new NotFoundHttpException();
    }
    $until = $entity->getValidUntil();
    $cache = (new CacheableMetadata())
      ->addCacheableDependency($entity)
      ->setCacheMaxAge($until === NULL ? Cache::PERMANENT : max(0, $until - $now));

    return (new TrustedRedirectResponse($entity->getTargetUrl(), 302))
      ->addCacheableDependency($cache);
  }

}
