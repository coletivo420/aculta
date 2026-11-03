<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_superficies\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fase 3.0 com Kernel: rotas reais e 404 dos caminhos antigos.
 *
 * DEFERRED: código escrito, NÃO executado. Requer vendor/ e o Runtime
 * (PHP 8.4). Confere o roteiro em docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md.
 */
#[Group('aculta_superficies')]
final class SurfaceRoutesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'domain',
    'aculta_portal',
    'aculta_wiki',
    'aculta_studio',
    'aculta_commerce',
    'aculta_superficies',
  ];

  /**
   * Caminhos novos resolvem para as rotas esperadas.
   */
  public function testCaminhosNovosResolvem(): void {
    $provider = $this->container->get('router.route_provider');
    $this->assertSame('/we-sativa', $provider->getRouteByName('aculta_studio.we_sativa')->getPath());
    $this->assertSame('/green-stash', $provider->getRouteByName('aculta_commerce.green_stash')->getPath());
    $this->assertSame('wiki', $provider->getRouteByName('aculta_studio.we_sativa')->getOption('_aculta_domain_purpose'));
    $this->assertSame('shop', $provider->getRouteByName('aculta_commerce.green_stash')->getOption('_aculta_domain_purpose'));
  }

  /**
   * Caminhos antigos respondem 404 sem redirecionamento.
   */
  public function testCaminhosAntigosRespondem404(): void {
    $kernel = $this->container->get('http_kernel');
    foreach (['/wesativa', '/greenstash', '/wiki/busca', '/studio/we-sativa'] as $caminho) {
      $resposta = $kernel->handle(Request::create($caminho));
      $this->assertSame(404, $resposta->getStatusCode(), $caminho);
      $this->assertFalse($resposta->headers->has('Location'), $caminho);
    }
  }

}
