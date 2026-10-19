<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Social;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Caixa "Redes sociais" da Minha conta, embaixo da bio: uma linha por rede, com ícone, valor atual e lápis.
 * O lápis abre o modal de edição do link (aculta_user.redes_form).
 */
final class SocialLinksCard {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SocialNetworks $networks,
  ) {}

  /**
   * Monta a caixa da conta informada.
   */
  public function build(AccountInterface $account): array {
    $profile = PerfilDoPapel::principal($this->entityTypeManager->getStorage('profile'), $account);
    $links = $profile ? $this->networks->linksOf($profile) : [];

    $items = [];
    foreach (SocialNetworks::NETWORKS as $key => $definition) {
      $label = (string) t($definition['label']);
      $items[$key] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-social-item'],
        'icon' => $this->element('span', '', 'account-social-icon-' . $key, ['aria-hidden' => 'true']),
        'text' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'account-social-text'],
          // Com link, o nome é um link para a rede. Sem link, só o nome.
          'name' => isset($links[$key])
            ? PortalLinkElement::build($label, Url::fromUri($links[$key]), [
              'data-portal-ui' => 'account-social-name',
              'rel' => 'noopener nofollow',
              'target' => '_blank',
            ])
            : $this->element('span', $label, 'account-social-name'),
          // Sem link: o status aparece abaixo do nome. Com link, nada é exibido (o valor fica armazenado).
          'status' => isset($links[$key]) ? [] : $this->element('span', (string) t('Não informado'), 'account-social-status'),
        ],
        'edit' => PortalLinkElement::build(
          '',
          Url::fromRoute('aculta_user.redes_form', ['rede' => $key]),
          [
            'class' => 'use-ajax',
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['title' => (string) t('Editar @rede', ['@rede' => $label])]),
            'data-portal-ui' => 'account-social-edit',
            'aria-label' => (string) t('Editar @rede', ['@rede' => $label]),
          ],
        ) + ['#attached' => ['library' => ['core/drupal.dialog.ajax']]],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['id' => 'aculta-account-social-card', 'data-portal-ui' => 'account-social-card'],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h4', '#value' => t('Redes sociais')],
      'list' => ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'account-social-list'], 'items' => $items],
      '#cache' => ['tags' => $profile ? $profile->getCacheTags() : []],
    ];
  }

  /**
   * Elemento neutro do módulo (template do tema), com token data-portal-ui.
   */
  private function element(string $tag, string $value, string $token, array $attributes = []): array {
    return [
      '#theme' => 'aculta_portal_element',
      '#tag' => $tag,
      '#value' => $value,
      '#element_attributes' => new Attribute(['data-portal-ui' => $token] + $attributes),
    ];
  }

  /**
   * Valor curto para exibir: o endereço do e-mail, ou o host e o caminho do link.
   */
  private function shortValue(string $key, string $uri): string {
    $host = (string) parse_url($uri, PHP_URL_HOST);
    $path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/');
    return $host . $path;
  }

}
