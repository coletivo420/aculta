<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar\Controller;

use Drupal\aculta_calendar\Embed\DeployerHostDirectory;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Read-only administration page for calendar embedding hosts.
 */
final class EmbedHostsController extends ControllerBase {

  public function __construct(
    private readonly DeployerHostDirectory $hosts,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('aculta_calendar.embed_hosts'));
  }

  /**
   * Renders the test and production hosts defined by ACULTA Deployer.
   *
   * @return array<string, mixed>
   *   Administrative render array.
   */
  public function page(): array {
    $rows = [];
    foreach ($this->hosts->mappings() as $mapping) {
      $rows[] = [
        $mapping['domain_id'],
        $mapping['test_host'],
        $mapping['production_host'],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['aculta-calendar-embed-hosts']],
      'description' => [
        '#plain_text' => $this->t('Os hosts abaixo vêm do ACULTA Deployer. Esta tela é somente para consulta; alterações devem ser feitas no mapeamento de domínios do Deployer.'),
      ],
      'hosts' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Domínio'),
          $this->t('Ambiente de teste'),
          $this->t('Produção'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('O ACULTA Deployer não declarou hosts válidos.'),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
