<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\aculta_events\Entity\EventOrganization;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller da página pública de Organização Cultural (ARQ-05).
 */
final class EventOrganizationPageController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * Título da página pública da organização.
   */
  public function title(string $slug): string {
    $org = $this->loadBySlug($slug);
    return $org !== NULL ? (string) $org->label() : 'Organização';
  }

  /**
   * Renderiza o perfil público da organização.
   */
  public function page(string $slug): array {
    $org = $this->loadBySlug($slug);
    if ($org === NULL) {
      throw new NotFoundHttpException();
    }

    if (!$org->access('view')) {
      throw new NotFoundHttpException();
    }

    $isUnclaimed = $org->isUnclaimed();
    $hideLocation = $org->shouldHideLocation();

    $build = [
      '#theme' => 'container',
      '#attributes' => ['class' => ['aculta-organization-profile']],
      '#cache' => [
        'tags' => $org->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];

    // Badge de não reivindicada.
    if ($isUnclaimed) {
      $build['unclaimed_badge'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['organization-badge', 'organization-badge--unclaimed']],
        'text' => [
          '#markup' => '<div class="alert alert-info"><strong>' . $this->t('Perfil não reivindicado') . '</strong>: ' . $this->t('Esta organização cultural ainda não possui titular verificado. Se você é responsável legal ou produtor desta organização, solicite a titularidade.') . '</div>',
        ],
      ];
    }

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['organization-header']],
      'title' => [
        '#markup' => '<h1 class="organization-title">' . htmlspecialchars((string) $org->label(), ENT_QUOTES, 'UTF-8') . '</h1>',
      ],
    ];

    // Localidade (Cidade/UF) respeita a flag de visibilidade.
    if (!$hideLocation) {
      $cidade = (string) $org->get('localidade')->value;
      $uf = (string) $org->get('uf')->value;
      if ($cidade !== '' || $uf !== '') {
        $locStr = trim($cidade . ($uf !== '' ? ' / ' . $uf : ''));
        $build['header']['location'] = [
          '#markup' => '<p class="organization-location">📍 ' . htmlspecialchars($locStr, ENT_QUOTES, 'UTF-8') . '</p>',
        ];
      }
    }

    // Apresentação e descrição.
    $body = (string) $org->get('body')->value;
    if ($body !== '') {
      $build['body'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['organization-body']],
        'content' => [
          '#markup' => '<div class="organization-description">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</div>',
        ],
      ];
    }

    // Links oficiais.
    $links = (string) $org->get('links')->value;
    if ($links !== '') {
      $build['links'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['organization-links']],
        'title' => ['#markup' => '<h3>' . $this->t('Canais oficiais') . '</h3>'],
        'content' => ['#markup' => '<p>' . nl2br(htmlspecialchars($links, ENT_QUOTES, 'UTF-8')) . '</p>'],
      ];
    }

    // Eventos publicados associados a esta organização.
    $eventStorage = $this->entityTypeManager()->getStorage('aculta_event');
    $eventIds = $eventStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_organizacao', $org->id())
      ->condition('field_status', 'publicado')
      ->range(0, 20)
      ->execute();

    if (!empty($eventIds)) {
      $events = $eventStorage->loadMultiple($eventIds);
      $eventItems = [];
      foreach ($events as $event) {
        $eventItems[] = [
          '#markup' => '<li class="organization-event-item"><a href="' . $event->toUrl()->toString() . '">' . htmlspecialchars((string) $event->label(), ENT_QUOTES, 'UTF-8') . '</a></li>',
        ];
      }
      $build['events'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['organization-events']],
        'title' => ['#markup' => '<h2>' . $this->t('Eventos organizados') . '</h2>'],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $eventItems,
        ],
      ];
    }

    // Ações públicas: Denunciar perfil e Reivindicar titularidade.
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['organization-footer-actions']],
    ];

    if ($isUnclaimed && $this->currentUser()->isAuthenticated()) {
      $build['actions']['claim_link'] = [
        '#markup' => '<p><a class="btn btn-outline-primary" href="/painel-administrativo/pessoas/reivindicacoes">' . $this->t('Reivindicar posse desta organização') . '</a></p>',
      ];
    }

    if ($this->currentUser()->isAuthenticated()) {
      $build['actions']['report_link'] = [
        '#markup' => '<p class="text-muted small">' . $this->t('Encontrou algo incorreto? <a href="/painel-administrativo/ugc/denuncias">Denunciar este perfil à moderação</a>.') . '</p>',
      ];
    }

    return $build;
  }

  /**
   * Carrega a organização a partir do slug.
   */
  private function loadBySlug(string $slug): ?EventOrganization {
    $storage = $this->entityTypeManager()->getStorage('aculta_event_organization');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_slug', $slug)
      ->range(0, 1)
      ->execute();

    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\aculta_events\Entity\EventOrganization|null $org */
    $org = $storage->load(reset($ids));
    return $org;
  }

}
