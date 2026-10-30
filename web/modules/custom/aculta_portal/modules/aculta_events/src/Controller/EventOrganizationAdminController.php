<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\aculta_events\Entity\EventOrganization;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Gestão administrativa de Organizações Culturais no domínio principal (ARQ-05).
 */
final class EventOrganizationAdminController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * Visão geral de organizações no painel administrativo.
   */
  public function overview(): array {
    $storage = $this->entityTypeManager()->getStorage('aculta_event_organization');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('changed', 'DESC')
      ->range(0, 50)
      ->execute();

    $rows = [];
    if (!empty($ids)) {
      /** @var \Drupal\aculta_events\Entity\EventOrganization[] $orgs */
      $orgs = $storage->loadMultiple($ids);
      foreach ($orgs as $org) {
        $titularUid = $org->getOwnerId();
        $titularName = $this->t('Nenhum (não reivindicada)');
        if ($titularUid > 0) {
          $user = User::load($titularUid);
          $titularName = $user ? $user->getDisplayName() : ('UID ' . $titularUid);
        }

        $statusStr = $org->isClaimed()
          ? $this->t('Reivindicada (@status)', ['@status' => $org->getClaimStatus()])
          : $this->t('Não reivindicada');

        $rows[] = [
          'title' => [
            'data' => [
              '#markup' => '<strong>' . htmlspecialchars((string) $org->label(), ENT_QUOTES, 'UTF-8') . '</strong>',
            ],
          ],
          'titular' => $titularName,
          'status' => $statusStr,
          'actions' => [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'edit' => [
                  'title' => $this->t('Editar'),
                  'url' => Url::fromRoute('aculta_events.organizer_organization_edit', ['aculta_event_organization' => $org->id()]),
                ],
                'members' => [
                  'title' => $this->t('Membros'),
                  'url' => Url::fromRoute('aculta_events.organizer_organization_members', ['aculta_event_organization' => $org->id()]),
                ],
              ],
            ],
          ],
        ];
      }
    }

    return [
      '#type' => 'container',
      'title' => [
        '#markup' => '<h1>' . $this->t('Organizações Culturais') . '</h1>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Organização'),
          $this->t('Titular'),
          $this->t('Situação'),
          $this->t('Ações'),
        ],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhuma organização cultural cadastrada.'),
      ],
    ];
  }

}
