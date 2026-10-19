<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\aculta_user\Form\FriendshipActionForm;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\FriendshipManagerInterface;
use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Página pública do perfil em /u/<apelido>.
 *
 * Perfil inexistente, sem apelido ou fora do propósito MAIN responde 404. Perfil privado mostra só foto e apelido. Só entra no render o que PublicProfilePolicy liberar.
 */
final class PublicProfileController extends ControllerBase {

  /**
   * Propósito em que a página pública é servida.
   */
  private const PURPOSE = 'main';

  public function __construct(
    private readonly DomainPurposeManager $purposeManager,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly PublicProfilePolicy $policy,
    private readonly SocialNetworks $networks,
    private readonly FriendshipManagerInterface $friendshipManager,
    private readonly AccountProxyInterface $account,
    private readonly FormBuilderInterface $forms,
    private readonly ModuleHandlerInterface $modules,
    private readonly AvatarPool $avatarPool,
    private readonly ImageRenderer $imageRenderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_portal.domain_purpose'),
      $container->get('entity_type.manager'),
      $container->get('aculta_user.public_profile_policy'),
      $container->get('aculta_user.social_networks'),
      $container->get('aculta_user.friendship_manager'),
      $container->get('current_user'),
      $container->get('form_builder'),
      $container->get('module_handler'),
      $container->get('aculta_user.avatar_pool'),
      $container->get('aculta_image.render'),
    );
  }

  /**
   * Renderiza o perfil público do apelido informado.
   */
  public function view(string $apelido): array {
    if ($this->purposeManager->getCurrentPurpose() !== self::PURPOSE) {
      throw new NotFoundHttpException();
    }

    $profile = $this->loadByNickname($apelido);
    if ($profile === NULL) {
      throw new NotFoundHttpException();
    }

    // The URL slug is canonical lowercase, while the stored nickname is the
    // user-facing display value and may retain uppercase characters.
    $displayNickname = (string) $profile->get('field_nickname')->value;

    $account = $profile->getOwner();
    $cache = (new CacheableMetadata())
      ->addCacheTags(['profile:' . $profile->id(), 'user:' . $profile->getOwnerId()])
      ->addCacheContexts(['url.path']);

    // Perfil privado mostra só foto e apelido (PublicProfilePolicy). Sem apelido, a resposta é a de inexistente.
    $items = $this->policy->publicItems($this->valuesOf($profile, $account));
    if ($items === []) {
      throw new NotFoundHttpException();
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'public-profile'],
      'header' => ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'public-profile-header'], 'avatar' => $this->avatar($account, $displayNickname, isset($items['foto'])), 'identity' => ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'public-profile-identity'], 'name' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => $displayNickname, '#element_attributes' => new Attribute(['data-portal-ui' => 'public-profile-name'])]]],
    ];

    if (isset($items['bio']) && $profile->hasField('field_bio') && !$profile->get('field_bio')->isEmpty()) {
      $build['bio'] = ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#value' => (string) $profile->get('field_bio')->value, '#element_attributes' => new Attribute(['data-portal-ui' => 'public-profile-bio'])];
    }

    $links = [];
    if (isset($items['redes'])) {
      foreach ($this->networks->linksOf($profile) as $key => $uri) {
        $links[] = $this->socialLink($key, $uri);
      }
    }
    if (isset($items['email']) && $account !== NULL && $account->getEmail() !== '') {
      $links[] = $this->emailLink((string) $account->getEmail());
    }
    if ($account !== NULL && $this->account->isAuthenticated() && (int) $this->account->id() !== (int) $account->id()) {
      $relationship = $this->friendshipManager->getRelationshipWith((int) $account->id());
      $build['header']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'public-profile-actions'],
        'friendship' => $this->forms->getForm(FriendshipActionForm::class, $account, $relationship),
      ];
      $cache->addCacheContexts(['user'])->addCacheTags(['user:' . $this->account->id()]);
      $cache->addCacheTags($this->entityTypeManagerService->getDefinition('aculta_friendship')->getListCacheTags());
      if ($relationship !== NULL) {
        $cache->addCacheableDependency($relationship);
      }
    }
    // Other modules add to the profile's actions (the messenger's "Opções" box, which holds the friendship actions).
    $actions = $build['header']['actions'] ?? [];
    $this->modules->alter('aculta_user_public_profile_actions', $actions, $profile);
    if ($actions !== []) {
      $build['header']['actions'] = $actions;
    }
    if ($links !== []) {
      $build['links'] = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'public-profile-links', 'aria-label' => (string) $this->t('Contatos e redes')], 'items' => $links];
    }

    $this->modules->alter('aculta_user_public_profile', $build, $profile);
    $cache->applyTo($build);
    return $build;
  }

  /**
   * Avatar do perfil: foto permitida, acervo ilustrativo ou inicial de segurança.
   */
  private function avatar($account, string $apelido, bool $showPhoto): array {
    $wrap = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'public-profile-avatar']];
    if ($showPhoto && $account !== NULL && $account->hasField('user_picture') && !$account->get('user_picture')->isEmpty()) {
      $wrap['image'] = $this->imageRenderer->build('avatar', $account->get('user_picture')->entity->getFileUri(), $apelido);
    }
    else {
      $fallback = $this->avatarPool->fallbackEntry((int) $account?->id());
      if ($fallback !== NULL) {
        $wrap['image'] = [
          '#type' => 'html_tag',
          '#tag' => 'img',
          '#attributes' => [
            'src' => $this->avatarPool->previewUrl($fallback),
            'alt' => (string) $this->t('Avatar ilustrativo para @name', ['@name' => $apelido]),
            'data-portal-ui' => 'public-profile-avatar-image',
          ],
          '#cache' => ['contexts' => ['languages:language_interface']],
        ];
      }
      else {
        $wrap['initial'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => mb_strtoupper(mb_substr($apelido, 0, 1)), '#element_attributes' => new Attribute(['data-portal-ui' => 'public-profile-initial', 'aria-hidden' => 'true'])];
      }
    }
    return $wrap;
  }

  /**
   * Botão de rede social (Instagram, Facebook, Reddit): ícone Bootstrap e nome, em nova aba.
   */
  private function socialLink(string $key, string $uri): array {
    $label = (string) SocialNetworks::NETWORKS[$key]['label'];
    return PortalLinkElement::build($label, Url::fromUri($uri), [
      'data-portal-ui' => 'public-profile-link-' . $key,
      'rel' => 'noopener nofollow',
      'target' => '_blank',
      'aria-label' => $label,
    ]);
  }

  /**
   * E-mail do perfil: botão com ícone e rótulo, como as demais redes, com link mailto:.
   */
  private function emailLink(string $email): array {
    $label = (string) $this->t('E-mail');
    return PortalLinkElement::build($label, Url::fromUri('mailto:' . $email), [
      'data-portal-ui' => 'public-profile-link-email',
      'aria-label' => $label . ': ' . $email,
    ]);
  }

  /**
   * Carrega o perfil participante cujo apelido é o informado.
   */
  private function loadByNickname(string $apelido): ?ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', PerfilDoPapel::TIPOS, 'IN')
      ->condition('field_nickname', $apelido)
      ->range(0, 1)
      ->execute();
    if ($ids === []) {
      return NULL;
    }
    $profile = $storage->load(reset($ids));
    return $profile instanceof ProfileInterface ? $profile : NULL;
  }

  /**
   * Lê os valores de visibilidade do perfil. Campo ausente usa o padrão: perfil público, itens privados.
   *
   * @return array{publico: bool, obrigatorio: bool, apelido: string, exibir_foto: bool, exibir_bio: bool, exibir_email: bool, exibir_redes: bool}
   */
  private function valuesOf(ProfileInterface $profile, $account): array {
    $flag = static function (string $field, bool $default) use ($profile): bool {
      if (!$profile->hasField($field) || $profile->get($field)->isEmpty()) {
        return $default;
      }
      return (bool) $profile->get($field)->value;
    };

    return [
      'publico' => $flag('field_perfil_publico', TRUE),
      'obrigatorio' => $account !== NULL && PublicProfilePolicy::isObrigatorioPublico($account->getRoles()),
      'apelido' => (string) $profile->get('field_nickname')->value,
      'exibir_foto' => $flag('field_exibir_foto', FALSE),
      'exibir_bio' => $flag('field_exibir_bio', FALSE),
      'exibir_email' => $flag('field_exibir_email', FALSE),
      'exibir_redes' => $flag('field_exibir_redes', FALSE),
    ];
  }

}
