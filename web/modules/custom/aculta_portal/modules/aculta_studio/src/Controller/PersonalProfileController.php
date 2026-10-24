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
final class PersonalProfileController {

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

    $actions = [
      [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Alterar apelido e dados básicos'),
        '#url' => Url::fromRoute('aculta_portal.my_data'),
      ],
    ];
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
        '#url' => Url::fromRoute('aculta_user.configuracoes_privacidade'),
      ];
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
      '#attributes' => ['data-portal-ui' => 'studio-personal-profile'],
      'intro' => [
        '#plain_text' => new TranslatableMarkup('Edite seu perfil com os mesmos controles e permissões da sua conta. A página pública é independente deste editor.'),
      ],
      'identity' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'studio-profile-identity'],
        'name' => ['#plain_text' => $user->getDisplayName()],
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
      $build['social'] = $this->socialCard->build($user);
    }
    $cache->applyTo($build);
    return $build;
  }

}
