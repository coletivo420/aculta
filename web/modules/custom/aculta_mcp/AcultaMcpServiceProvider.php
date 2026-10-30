<?php

declare(strict_types=1);

namespace Drupal\aculta_mcp;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Sets the canonical HTTP path for the ACULTA MCP endpoint.
 */
final class AcultaMcpServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $container->setParameter('mcp_server.base_path', '/integracoes/mcp');
  }

}
