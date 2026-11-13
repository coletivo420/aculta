<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Bio;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Caixa Bio da Minha conta: textarea bloqueado e diálogo de edição.
 *
 * Usado pela visão geral e pela resposta AJAX para manter a marcação
 * consistente.
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
      '#type' => 'component',
      '#component' => 'aculta420_v2:dialog',
      '#props' => [
        'id' => 'aculta-account-bio-dialog',
        'title' => (string) $this->t('Editar bio'),
        'trigger_label' => (string) $this->t('Editar bio'),
        'trigger_url' => $editUrl->toString(),
        'close_label' => (string) $this->t('Fechar'),
      ],
      '#slots' => ['content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'data-aculta-drawer-content' => '',
          'aria-live' => 'polite',
          'aria-busy' => 'false',
        ],
        '#value' => $this->t('Carregando…'),
      ]],
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
      '#type' => 'component',
      '#component' => 'aculta420_v2:surface-panel',
      '#props' => ['tone' => 'raised', 'radius' => 'md', 'spacing' => 'compact', 'elevation' => 'none'],
      '#attributes' => ['id' => 'aculta-account-bio-card'],
      '#slots' => [
        'content' => [
          'title' => [
            '#theme' => 'aculta_portal_element',
            '#tag' => 'h3',
            '#value' => $this->t('Bio'),
          ],
          // Campo bloqueado para leitura, com aviso quando a bio está vazia.
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
            ],
          ],
          'dialog' => $dialog,
        ],
      ],
      '#cache' => [
        'tags' => $profile ? $profile->getCacheTags() : [],
      ],
    ];
  }

}
