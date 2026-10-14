<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Bio;

use Drupal\Component\Serialization\Json;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Caixa Bio da Minha conta: textarea bloqueado com a bio (ou o aviso quando vazia) e o lápis que abre o modal.
 *
 * Usado pela visão geral e pela resposta AJAX do modal, para que as duas mostrem a mesma marcação.
 */
final class AccountBioCard {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Monta a caixa da conta informada.
   */
  public function build(AccountInterface $account): array {
    $profile = $this->entityTypeManager->getStorage('profile')->loadByUser($account, 'participante');
    $bio = $profile && $profile->hasField('field_bio') ? trim((string) $profile->get('field_bio')->value) : '';

    return [
      '#type' => 'container',
      // id estável para a substituição AJAX: o tema troca data-portal-ui por classes ao renderizar.
      '#attributes' => ['id' => 'aculta-account-bio-card', 'data-portal-ui' => 'account-bio-card'],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => t('Bio')],
      // Caixa de formulário bloqueada: leitura da bio, ou o aviso como placeholder quando vazia.
      'text' => [
        '#type' => 'textarea',
        '#title' => t('Bio'),
        '#title_display' => 'invisible',
        '#value' => $bio,
        '#disabled' => TRUE,
        '#resizable' => 'none',
        '#rows' => 4,
        '#attributes' => [
          'disabled' => 'disabled',
          'readonly' => 'readonly',
          'placeholder' => (string) t('Você ainda não escreveu sua bio.'),
          'data-portal-ui' => 'account-bio-text',
        ],
      ],
      // Lápis abaixo da caixa: abre o modal de edição.
      'edit' => PortalLinkElement::build(
        t('Editar bio'),
        Url::fromRoute('aculta_user.bio_form'),
        [
          'class' => 'use-ajax',
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['title' => (string) t('Editar bio')]),
          'data-portal-ui' => 'account-bio-edit',
          'aria-label' => (string) t('Editar bio'),
        ],
      ) + ['#attached' => ['library' => ['core/drupal.dialog.ajax']]],
      '#cache' => [
        'tags' => $profile ? $profile->getCacheTags() : [],
      ],
    ];
  }

}
