<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Caixa de bio na visão geral da Minha conta, bloqueada para leitura. O lápis abre o modal de edição.
 *
 * Substitui o resumo de Cursos (aculta_portal). O Portal dispara aculta_portal_dashboard.
 */
final class AccountDashboardHooks {

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adiciona a caixa Bio à visão geral.
   */
  #[Hook('aculta_portal_dashboard_alter')]
  public function dashboardAlter(array &$build, AccountInterface $account): void {
    $storage = $this->entityTypeManager->getStorage('profile');
    $profile = $storage->loadByUser($account, 'participante');
    $bio = $profile && $profile->hasField('field_bio') ? trim((string) $profile->get('field_bio')->value) : '';

    $build['bio'] = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-bio-card'],
      'header' => [
        '#type' => 'container',
        'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => t('Bio')],
        'edit' => [
          '#type' => 'link',
          '#title' => t('Editar bio'),
          '#url' => Url::fromRoute('aculta_user.bio_form'),
          '#attributes' => [
            'class' => ['use-ajax'],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => 560, 'title' => (string) t('Editar bio')]),
            'data-portal-ui' => 'account-bio-edit',
            'aria-label' => (string) t('Editar bio'),
          ],
          '#attached' => ['library' => ['core/drupal.dialog.ajax']],
        ],
      ],
      'text' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['data-portal-ui' => 'account-bio-text'],
        '#value' => $bio !== '' ? $bio : (string) t('Você ainda não escreveu sua bio.'),
      ],
      '#cache' => [
        'tags' => $profile ? $profile->getCacheTags() : [],
      ],
    ];
  }

}
