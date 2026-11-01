<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewExecutableFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Presents the course catalog on the COURSES Domain. */
final class CoursesController extends ControllerBase {

  private const CATALOG_VIEW = 'courses_catalog';
  private const CATALOG_DISPLAY = 'block_1';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
    #[Autowire(service: 'views.executable')]
    private readonly ViewExecutableFactory $viewExecutableFactory,
  ) {}

  /** Builds the public course landing page from the configured catalog View. */
  public function home(): array {
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'courses-home'],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Formação para ampliar conhecimento, fortalecer direitos e construir caminhos antiproibicionistas.'),
        '#attributes' => ['data-portal-ui' => 'courses-home-intro'],
      ],
      'catalog_title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Cursos disponíveis'),
      ],
      'catalog' => $this->catalog(),
      'collaboration' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Acesse sua conta para participar dos cursos.'),
        '#attributes' => ['data-portal-ui' => 'courses-home-collaboration'],
      ],
    ];
  }

  /**
   * Returns the native Views render element, or the unavailable message.
   *
   * Replaces the views_embed_view wrapper (deprecated in drupal:11.4.0,
   * removed in drupal:13.0.0, https://www.drupal.org/node/3572594) with the
   * same contract: the display access check runs before choosing between the
   * '#type' => 'view' element and the fallback. The View keeps its own empty area, pager and
   * cache plugin.
   */
  private function catalog(): array {
    $config = $this->entities->getStorage('view')->load(self::CATALOG_VIEW);
    $view = $config ? $this->viewExecutableFactory->get($config) : NULL;
    if ($view !== NULL && $view->access(self::CATALOG_DISPLAY)) {
      return [
        '#type' => 'view',
        '#name' => self::CATALOG_VIEW,
        '#display_id' => self::CATALOG_DISPLAY,
        '#arguments' => [],
      ];
    }

    return [
      '#markup' => $this->t('Nenhum curso está disponível no momento.'),
      // The fallback depends on the View config and on the permission-based
      // Views access plugin.
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => ['config:views.view.' . self::CATALOG_VIEW],
      ],
    ];
  }

}
