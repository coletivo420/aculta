<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\Social\SocialLinksCard;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Own-profile Studio page composed from the ACULTA User owner components.
 *
 * The Studio owns only composition/navigation. Profile data, write handlers,
 * visibility rules, picture cropping, and social-link normalization remain
 * entirely in aculta_user and aculta_portal.
 */
final class StudioProfileController {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ?PublicProfileLinkInterface $publicProfileLink = NULL,
    private readonly ?AccountBioCard $bioCard = NULL,
    private readonly ?SocialLinksCard $socialCard = NULL,
  ) {}

  /**
   * Renders the profile editor entry, scoped exclusively to the active user.
   *
   * @return array<string, mixed>
   *   Render array containing delegated editors and authorized navigation.
   */
  public function page(): array {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('access aculta portal')) {
      throw new AccessDeniedHttpException();
    }

    $user = $this->entities->getStorage('user')->load($this->account->id());
    if (!$user instanceof UserInterface || (int) $user->id() !== (int) $this->account->id()) {
      throw new AccessDeniedHttpException();
    }

    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($user);
    // The owner decides whether any public link exists at all; this must
    // not be inferred from the fact that the member can edit their profile.
    $publicUrl = $this->publicProfileLink?->build($user, $cache);

    // Nickname editing must be extracted from the Portal's full personal-data
    // form before enabling it here. Never embed an account form in Perfil.
    $actions = [];
    if ($this->bioCard !== NULL && $this->socialCard !== NULL) {
      $actions[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Alterar foto ou avatar'),
        '#url' => Url::fromRoute('aculta_user.foto'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['title' => 'Alterar foto do perfil']),
          'data-portal-ui' => 'studio-profile-photo-edit',
        ],
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax', 'aculta_user/photo-flow'],
          'drupalSettings' => [
            'acultaUser' => [
              'savedFid' => $user->hasField('user_picture') ? (int) ($user->get('user_picture')->target_id ?? 0) : 0,
            ],
          ],
        ],
      ];
      $actions[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Configurar privacidade'),
        '#url' => Url::fromRoute('aculta_studio.profile_privacy'),
      ];
    }
    if ($this->bioCard !== NULL && $this->socialCard !== NULL) {
      $actions[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Editar biografia'),
        '#url' => Url::fromRoute('aculta_studio.profile_bio'),
      ];
      foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'reddit' => 'Reddit'] as $key => $label) {
        $actions[] = [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Editar @network', ['@network' => $label]),
          '#url' => Url::fromRoute('aculta_studio.profile_social', ['rede' => $key]),
        ];
      }
    }
    if ($publicUrl !== NULL) {
      $actions[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Ver perfil público'),
        '#url' => $publicUrl,
        '#attributes' => ['data-portal-ui' => 'studio-profile-public-link'],
      ];
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'studio-profile-editor'],
      'intro' => [
        '#plain_text' => new TranslatableMarkup('Administre somente sua identidade na comunidade: foto, biografia, redes e visibilidade. Informações cadastrais e segurança pertencem a Minha Conta.'),
      ],
      'identity' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-profile-identity'],
        'name' => ['#plain_text' => $user->getDisplayName()],
        'nickname_note' => [
          '#plain_text' => new TranslatableMarkup('A edição isolada do apelido será integrada após separar esse campo do formulário cadastral.'),
        ],
      ],
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $actions,
        '#attributes' => ['data-portal-ui' => 'studio-profile-actions'],
      ],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
        // Editorial/profile decisions and direct account details are private.
        'max-age' => 0,
      ],
    ];

    // ACULTA User owns the current values, edits and modal contracts. The
    // same components used by Minha Conta are reused verbatim.
    if ($this->bioCard !== NULL && $this->socialCard !== NULL) {
      $build['bio'] = $this->bioCard->build($user);
      // The Studio uses dedicated, accessible Form API routes. Original
      // modal edit controls are retained exclusively in Minha Conta legacy.
      unset($build['bio']['edit']);
      $build['social'] = $this->socialCard->build($user);
      foreach ($build['social']['list']['items'] as &$item) {
        unset($item['edit']);
      }
      unset($item);
    }
    $cache->applyTo($build);
    return $build;
  }

}
