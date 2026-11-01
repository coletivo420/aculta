<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller da página pública de Organização Cultural (ARQ-05).
 */
final class EventOrganizationPageController extends ControllerBase {

  public function __construct(
    private readonly ?DomainPurposeManager $purposes = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->has('aculta_portal.domain_purpose') ? $container->get('aculta_portal.domain_purpose') : NULL,
    );
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
      '#attributes' => ['data-portal-ui' => 'event-organization-profile'],
      '#cache' => [
        'tags' => $org->getCacheTags(),
        'contexts' => ['user.permissions'],
      ],
    ];

    // Badge de não reivindicada.
    if ($isUnclaimed) {
      $build['unclaimed_badge'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-organization-unclaimed'],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('<strong>Perfil não reivindicado</strong>: Esta organização cultural ainda não possui titular verificado. Se você é responsável legal ou produtor desta organização, solicite a titularidade.'),
        ],
      ];
    }

    $build['header'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organization-header'],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => (string) $org->label(),
        '#attributes' => ['data-portal-ui' => 'event-organization-title'],
      ],
    ];

    // Localidade (Cidade/UF) respeita a flag de visibilidade.
    if (!$hideLocation) {
      $cidade = (string) $org->get('localidade')->value;
      $uf = (string) $org->get('uf')->value;
      if ($cidade !== '' || $uf !== '') {
        $locStr = trim($cidade . ($uf !== '' ? ' / ' . $uf : ''));
        $build['header']['location'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => '📍 ' . $locStr,
          '#attributes' => ['data-portal-ui' => 'event-organization-location'],
        ];
      }
    }

    // Apresentação e descrição.
    $body = (string) $org->get('body')->value;
    if ($body !== '') {
      $build['body'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-organization-body'],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => Markup::create(nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))),
          '#attributes' => ['data-portal-ui' => 'event-organization-description'],
        ],
      ];
    }

    // Links oficiais.
    $links = (string) $org->get('links')->value;
    if ($links !== '') {
      $build['links'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-organization-links'],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Canais oficiais'),
        ],
        'content' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => Markup::create(nl2br(htmlspecialchars($links, ENT_QUOTES, 'UTF-8'))),
        ],
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
          '#type' => 'link',
          '#title' => (string) $event->label(),
          '#url' => $event->toUrl(),
          '#attributes' => ['data-portal-ui' => 'event-organization-event-link'],
        ];
      }
      $build['events'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'event-organization-events'],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Eventos organizados'),
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $eventItems,
        ],
      ];
    }

    // Ações públicas e administrativas contextuais.
    $build['actions'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'event-organization-actions'],
    ];

    if ($isUnclaimed) {
      $build['actions']['claim_info'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Responsáveis pela organização podem cadastrar eventos em seu nome ou solicitar titularidade formal à moderação.'),
        '#attributes' => ['data-portal-ui' => 'event-organization-admin-link'],
      ];
    }

    if ($this->currentUser()->hasPermission('administer aculta claims') && $this->purposes !== NULL) {
      $adminClaimsUrl = $this->purposes->environmentPathUrl('main', '/painel-administrativo/pessoas/reivindicacoes?target_type=aculta_event_organization&target_id=' . $org->id());
      if ($adminClaimsUrl !== NULL) {
        $build['actions']['admin_claims'] = [
          '#type' => 'link',
          '#title' => $this->t('Ver pedidos de reivindicação desta organização'),
          '#url' => $adminClaimsUrl,
          '#attributes' => ['data-portal-ui' => 'event-organization-admin-action'],
        ];
      }
    }

    if ($this->currentUser()->hasPermission('moderar denuncias ugc') && $this->purposes !== NULL) {
      $reportsUrl = $this->purposes->environmentPathUrl('main', '/painel-administrativo/moderacao/denuncias');
      if ($reportsUrl !== NULL) {
        $build['actions']['admin_reports'] = [
          '#type' => 'link',
          '#title' => $this->t('Ver fila de denúncias da moderação'),
          '#url' => $reportsUrl,
          '#attributes' => ['data-portal-ui' => 'event-organization-admin-link'],
        ];
      }
    }

    // Dados estruturados schema.org (Organization).
    $orgLd = [
      '@context' => 'https://schema.org',
      '@type' => 'Organization',
      'name' => (string) $org->label(),
      'url' => Url::fromRoute('aculta_events.organization_page', ['slug' => $slug], ['absolute' => TRUE])->toString(),
    ];

    if ($body !== '') {
      $orgLd['description'] = mb_substr(trim(strip_tags($body)), 0, 500);
    }

    if (!$hideLocation) {
      $cidade = trim((string) $org->get('localidade')->value);
      $uf = trim((string) $org->get('uf')->value);
      if ($cidade !== '' || $uf !== '') {
        $postalAddress = [
          '@type' => 'PostalAddress',
          'addressCountry' => 'BR',
        ];
        if ($cidade !== '') {
          $postalAddress['addressLocality'] = $cidade;
        }
        if ($uf !== '') {
          $postalAddress['addressRegion'] = $uf;
        }
        $orgLd['address'] = $postalAddress;
      }
    }

    $linksRaw = (string) $org->get('links')->value;
    if ($linksRaw !== '') {
      $lines = array_filter(
        array_map('trim', explode("\n", $linksRaw)),
        static fn($line) => $line !== '' && filter_var($line, FILTER_VALIDATE_URL) !== FALSE,
      );
      if (!empty($lines)) {
        $orgLd['sameAs'] = array_values($lines);
      }
    }

    $jsonLd = json_encode($orgLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $json = str_replace('</', '<\\/', (string) $jsonLd);
    $build['#attached']['html_head'][] = [
      [
        '#type' => 'html_tag',
        '#tag' => 'script',
        '#attributes' => ['type' => 'application/ld+json'],
        '#value' => $json,
      ],
      'aculta_events_organization_jsonld',
    ];

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
