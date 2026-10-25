<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Controller;

use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\aculta_user\Identity\AuthorIdentityResolver;
use Drupal\aculta_user\PublicProfileLinkInterface;
use Drupal\aculta_user\Social\SocialLinksCard;
use Drupal\aculta_image\ImageRenderer;
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
    private readonly ?AuthorIdentityResolver $identityResolver = NULL,
    private readonly ?ImageRenderer $imageRenderer = NULL,
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

    // Prefer the ACULTA User nickname and policy-filtered avatar. The editor
    // only handles the current user and never guesses a public URL.
    $displayName = $user->getDisplayName();
    $avatar = [];
    $identity = $this->identityResolver?->resolve($user, 'profile_full');
    if ($identity !== NULL) {
      $cache->addCacheableDependency($identity);
      $identityData = $identity->toArray();
      $displayName = (string) ($identityData['display_name'] ?: $displayName);
      $avatar = $identityData['avatar'] ?? [];
    }

    // The profile editor is private to its owner. The owner may see their
    // own photo even when they have not opted to publish it; public author
    // cards continue obeying PublicProfilePolicy. Use only aculta_image.
    if ($this->imageRenderer !== NULL && $user->hasField('user_picture') && !$user->get('user_picture')->isEmpty()) {
      $file = $user->get('user_picture')->entity;
      if ($file !== NULL) {
        $ownPhoto = $this->imageRenderer->build('avatar', $file->getFileUri(), $displayName, ['loading' => 'lazy']);
        if ($ownPhoto !== NULL) {
          $avatar = $ownPhoto;
          $cache->addCacheableDependency($file);
        }
      }
    }

    $identityActions = [[
      '#type' => 'link',
      '#title' => new TranslatableMarkup('Editar apelido público'),
      '#url' => Url::fromRoute('aculta_studio.profile_nickname'),
      '#attributes' => ['data-portal-ui' => 'studio-profile-nickname-edit'],
    ]];
    $bioActions = [];
    $socialActions = [];

    if ($this->bioCard !== NULL && $this->socialCard !== NULL) {
      $identityActions[] = [
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
      $identityActions[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Configurar privacidade'),
        '#url' => Url::fromRoute('aculta_studio.profile_privacy'),
      ];
      $bioActions[] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Editar biografia'),
        '#url' => Url::fromRoute('aculta_studio.profile_bio'),
      ];
      foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'reddit' => 'Reddit'] as $key => $label) {
        $socialActions[] = [
          '#type' => 'link',
          '#title' => new TranslatableMarkup('Editar @network', ['@network' => $label]),
          '#url' => Url::fromRoute('aculta_studio.profile_social', ['rede' => $key]),
        ];
      }
    }

    $identitySlots = [
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $identityActions,
        '#attributes' => ['data-portal-ui' => 'studio-profile-actions'],
      ],
    ];
    if ($avatar !== []) {
      $identitySlots['avatar'] = $avatar;
    }
    if ($publicUrl !== NULL) {
      $identitySlots['public_link'] = [
        '#type' => 'link',
        '#title' => new TranslatableMarkup('Ver perfil público'),
        '#url' => $publicUrl,
        '#attributes' => ['data-portal-ui' => 'studio-profile-public-link'],
      ];
    }

    $slots = [
      'intro' => [
        '#plain_text' => new TranslatableMarkup('Administre somente sua identidade na comunidade: foto, biografia, redes e visibilidade. Informações cadastrais e segurança pertencem a Minha Conta.'),
      ],
      'identity' => [
        '#type' => 'component',
        '#component' => 'aculta420:profile-identity',
        '#props' => [
          'display_name' => $displayName,
          'hint' => (string) new TranslatableMarkup('O apelido público é editado separadamente do nome cadastral e dos dados privados.'),
        ],
        '#slots' => $identitySlots,
        '#cache' => ['contexts' => ['user'], 'max-age' => 0],
      ],
    ];

    if ($this->bioCard !== NULL && $this->socialCard !== NULL) {
      // The read-only textarea, network rows and cache metadata are produced
      // by aculta_user. Remove modal editors only in Studio; legacy is intact.
      $bio = $this->bioCard->build($user);
      unset($bio['title'], $bio['edit']);
      $social = $this->socialCard->build($user);
      unset($social['title']);
      if (isset($social['list']['items']) && is_array($social['list']['items'])) {
        foreach ($social['list']['items'] as &$item) {
          unset($item['edit']);
        }
        unset($item);
      }

      $slots['biography'] = [
        '#type' => 'component',
        '#component' => 'aculta420:profile-bio',
        '#props' => ['title' => (string) new TranslatableMarkup('Biografia')],
        '#slots' => [
          'body' => $bio,
          'actions' => ['#theme' => 'item_list', '#items' => $bioActions],
        ],
      ];
      $slots['social'] = [
        '#type' => 'component',
        '#component' => 'aculta420:profile-social',
        '#props' => ['title' => (string) new TranslatableMarkup('Redes sociais')],
        '#slots' => [
          'items' => $social,
          'actions' => ['#theme' => 'item_list', '#items' => $socialActions],
        ],
      ];
    }

    // Slots carry all owner metadata, while the complete personal editor
    // is uncacheable and varies by account and permissions.
    $build = [
      '#type' => 'component',
      '#component' => 'aculta420:profile-editor',
      '#props' => [],
      '#slots' => $slots,
      '#attributes' => ['data-portal-ui' => 'studio-profile-editor'],
      '#cache' => [
        'contexts' => ['user', 'user.permissions', 'url.site'],
        'max-age' => 0,
      ],
    ];
    $cache->applyTo($build);
    return $build;
  }

}
