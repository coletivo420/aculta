<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_paths\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Fase 3.0 com Kernel: rotas reais e 404 dos caminhos antigos.
 *
 * Confere o roteiro em docs/architecture/TESTES-3.0-ROTAS-SUPERFICIES.md.
 */
#[Group('aculta_paths')]
#[RunTestsInSeparateProcesses]
final class SurfaceRoutesKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'path_alias',
    'key',
    'aculta_mailer',
    'aculta_mail_confirmer',
    'aculta_messenger',
    'private_message',
    'site_alert',
    'message_notify',
    'message_subscribe',
    'flag',
    'views',
    'aculta_user',
    'aculta_image',
    'commerce_cart',
    'commerce_store',
    'commerce_checkout',
    'commerce_price',
    'commerce_order',
    'commerce',
    'content_moderation',
    'workflows',
    'text',
    'options',
    'address',
    'entity_reference_revisions',
    'profile',
    'state_machine',
    'node',
    'domain',
    'aculta_file_uploader',
    'aculta_portal',
    'aculta_wiki',
    'aculta_studio',
    'aculta_commerce',
    'aculta_paths',
    'aculta_calendar',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('domain');
    $this->installEntitySchema('path_alias');
  }

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
