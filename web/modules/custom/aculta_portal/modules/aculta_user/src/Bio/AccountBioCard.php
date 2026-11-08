<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Bio;

use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Caixa Bio da Minha conta: textarea bloqueado com a bio (ou o aviso quando vazia) e o lápis que abre o modal.
 *
 * Usado pela visão geral e pela resposta AJAX do modal, para que as duas mostrem a mesma marcação.
 */
final class AccountBioCard {

  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Monta a caixa da conta informada.
   */
  public function build(AccountInterface $account): array {
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    $bio = $profile && $profile->hasField('field_bio') ? trim((string) $profile->get('field_bio')->value) : '';

    $editUrl = Url::fromRoute('aculta_user.bio_form');
    $dialog = [
      '#type' => 'html_tag',
      '#tag' => 'dialog',
      '#attributes' => [
        'id' => 'aculta-account-bio-dialog',
        'data-portal-ui' => 'account-bio-dialog',
        'aria-label' => (string) $this->t('Editar bio'),
        'data-aculta-utility-drawer' => '',
      ],
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'header',
        '#attributes' => ['data-portal-ui' => 'account-bio-dialog-header'],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#attributes' => ['data-portal-ui' => 'account-bio-dialog-title'],
          '#value' => $this->t('Editar bio'),
        ],
        'close' => [
          '#type' => 'component',
          '#component' => 'aculta420_v2:button',
          '#props' => [
            'text' => '×',
            'aria_label' => (string) $this->t('Fechar'),
            'variant' => 'ghost',
          ],
          '#attributes' => [
            'data-portal-ui' => 'account-bio-dialog-close',
            'data-aculta-dialog-close' => '',
          ],
        ],
      ],
      'content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'data-portal-ui' => 'account-bio-dialog-body',
          'data-aculta-drawer-content' => '',
          'aria-live' => 'polite',
          'aria-busy' => 'false',
        ],
        '#value' => $this->t('Carregando…'),
      ],
    ];

    (new Htmx())
      ->get($editUrl)
      ->trigger('aculta:drawer:open')
      ->target('find [data-aculta-drawer-content]')
      ->select('#aculta-user-account-bio-form')
      ->swap('innerHTML')
      ->sync('body:replace')
      ->onlyMainContent()
      ->applyTo($dialog);

    return [
      '#type' => 'container',
      // Id estável para a substituição AJAX: o tema troca data-portal-ui por classes ao renderizar.
      '#attributes' => ['id' => 'aculta-account-bio-card', 'data-portal-ui' => 'account-bio-card'],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $this->t('Bio')],
      // Caixa de formulário bloqueada: leitura da bio, ou o aviso como placeholder quando vazia.
      'text' => [
        '#type' => 'textarea',
        '#title' => $this->t('Bio'),
        '#title_display' => 'invisible',
        '#value' => $bio,
        '#disabled' => TRUE,
        '#resizable' => 'none',
        '#rows' => 4,
        '#attributes' => [
          'disabled' => 'disabled',
          'readonly' => 'readonly',
          'placeholder' => (string) $this->t('Você ainda não escreveu sua bio.'),
          'data-portal-ui' => 'account-bio-text',
        ],
      ],
      // Lápis abaixo da caixa: abre o modal de edição.
      'edit' => PortalLinkElement::build(
        $this->t('Editar bio'),
        $editUrl,
        [
          'data-portal-ui' => 'account-bio-edit',
          'data-aculta-dialog-trigger' => '',
          'aria-controls' => 'aculta-account-bio-dialog',
          'aria-haspopup' => 'dialog',
          'aria-expanded' => 'false',
          'aria-label' => (string) $this->t('Editar bio'),
        ],
      ),
      'dialog' => $dialog,
      '#cache' => [
        'tags' => $profile ? $profile->getCacheTags() : [],
      ],
    ];
  }

}
