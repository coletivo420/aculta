<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Controller;

use Drupal\aculta_magazine\Access\EditorialCapability;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Administrative landing page for Magazine configuration and oversight. */
final class EditorialAdminController extends ControllerBase {

  public function __construct(private readonly EditorialCapability $capability) {}

  /**
   * Creates the administrative controller with its capability policy.
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('aculta_magazine.capability'));
  }

  /**
   * Allows administrators to access Magazine settings and oversight.
   */
  public function access(): AccessResultInterface {
    return $this->capability->administer($this->currentUser());
  }

  /**
   * Presents the administrative entry points for the Magazine.
   */
  public function overview(): array {
    return [
      '#type' => 'container',
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['config:aculta_magazine.settings'],
      ],
      'intro' => ['#plain_text' => $this->t('Central administrativa da Revista. O trabalho editorial cotidiano acontece no ACULTA Studio Marley.')],
      'links' => [
        '#theme' => 'item_list',
        '#items' => [
          [
            '#type' => 'link',
            '#title' => $this->t('Configurar capacidades e papéis'),
            '#url' => Url::fromRoute('aculta_magazine.capabilities'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Acompanhar publicações'),
            '#url' => Url::fromRoute('view.aculta_magazine_admin_content.page_magazine'),
          ],
          [
            '#type' => 'link',
            '#title' => $this->t('Acompanhar comentários e moderação'),
            '#url' => Url::fromRoute('aculta_magazine.admin_comments'),
          ],
        ],
      ],
    ];
  }

  /**
   * Explains that the configured Comment View block supplies the queue.
   */
  public function comments(): array {
    return [
      '#type' => 'container',
      'intro' => [
        '#plain_text' => $this->t('Fila administrativa de comentários da Revista; a fila é fornecida pela View contextual configurada nesta página.'),
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'user.node_grants:view'],
        'tags' => ['config:aculta_magazine.settings'],
      ],
    ];
  }

}
