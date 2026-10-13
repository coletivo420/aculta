<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_user\PublicProfilePolicy;
use Drupal\aculta_user\Social\SocialNetworks;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Caixa de autores da Revista: autor principal (node.uid) e coautores (co_authors, Node Co-Authors).
 *
 * Só aparece em Notícias publicadas, no propósito MAGAZINE. Nome, foto e link seguem PublicProfilePolicy.
 */
final class ArticleAuthorsHooks {

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'aculta_user.public_profile_policy')]
    private readonly PublicProfilePolicy $policy,
    #[Autowire(service: 'aculta_user.social_networks')]
    private readonly SocialNetworks $networks,
  ) {}

  /**
   * Adiciona a caixa de autores à publicação da Revista.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, $display, $view_mode): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'article' || $entity->isNew() || !$entity->isPublished()) {
      return;
    }
    if ($view_mode !== 'full' || $this->purposeManager->getCurrentPurpose() !== 'magazine') {
      return;
    }

    $cache = (new CacheableMetadata())->addCacheableDependency($entity);
    $authors = [];
    $owner = $entity->getOwner();
    if ($owner instanceof UserInterface) {
      $authors[(int) $owner->id()] = ['user' => $owner, 'role' => 'author'];
      $cache->addCacheableDependency($owner);
    }
    if ($entity->hasField('co_authors')) {
      foreach ($entity->get('co_authors')->referencedEntities() as $user) {
        if ($user instanceof UserInterface && !isset($authors[(int) $user->id()])) {
          $authors[(int) $user->id()] = ['user' => $user, 'role' => 'coauthor'];
          $cache->addCacheableDependency($user);
        }
      }
    }
    if ($authors === []) {
      return;
    }

    $items = [];
    foreach ($authors as $entry) {
      $items[] = $this->authorItem($entry['user'], $entry['role'], $cache);
    }
    $build['article_authors'] = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => ['data-portal-ui' => 'article-authors'],
      'title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => (string) t('Autoria'), '#element_attributes' => new Attribute(['data-portal-ui' => 'article-authors-title'])],
      'list' => ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'article-authors-list'], 'items' => $items],
      '#cache' => ['tags' => $cache->getCacheTags(), 'contexts' => array_merge($cache->getCacheContexts(), ['url.path'])],
    ];
  }

  /**
   * Um autor: foto, nome (com link se o perfil for público) e papel.
   */
  private function authorItem(UserInterface $user, string $role, CacheableMetadata $cache): array {
    $profile = $this->entityTypeManager->getStorage('profile')->loadByUser($user, 'participante');
    if ($profile) {
      $cache->addCacheableDependency($profile);
    }
    $nickname = $profile && $profile->hasField('field_nickname') ? trim((string) $profile->get('field_nickname')->value) : '';
    $name = $nickname !== '' ? $nickname : $user->getDisplayName();
    $publico = $profile ? (bool) $profile->get('field_perfil_publico')->value : TRUE;
    $publico = $publico || PublicProfilePolicy::isObrigatorioPublico($user->getRoles());
    $flag = static fn(string $field): bool => $profile && $profile->hasField($field) && (bool) $profile->get($field)->value;
    $items = $this->policy->publicItems([
      'publico' => $publico,
      'apelido' => $name,
      'exibir_foto' => TRUE,
      'exibir_bio' => $flag('field_exibir_bio'),
      'exibir_redes' => $flag('field_exibir_redes'),
    ]);

    $photo = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'article-author-photo']];
    if (isset($items['foto']) && !$user->get('user_picture')->isEmpty()) {
      $photo['image'] = ['#theme' => 'image_style', '#style_name' => 'aculta_avatar', '#uri' => $user->get('user_picture')->entity->getFileUri(), '#alt' => $name];
    }
    else {
      $photo['initial'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => mb_strtoupper(mb_substr($name, 0, 1)), '#element_attributes' => new Attribute(['data-portal-ui' => 'article-author-initial', 'aria-hidden' => 'true'])];
    }

    $nameElement = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $name, '#element_attributes' => new Attribute(['data-portal-ui' => 'article-author-name'])];
    if ($publico && $nickname !== '') {
      $url = $this->purposeManager->routeUrl('main', 'aculta_user.perfil_publico', ['apelido' => $nickname]);
      if ($url !== NULL) {
        $nameElement = PortalLinkElement::build($name, $url, ['data-portal-ui' => 'article-author-name']);
      }
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'article-author'],
      'photo' => $photo,
      'text' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'article-author-text'],
        'name' => $nameElement,
        'role' => ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => $role === 'author' ? (string) t('Autor') : (string) t('Coautor'), '#element_attributes' => new Attribute(['data-portal-ui' => 'article-author-role'])],
        'social' => $this->socialIcons($profile, $items),
      ],
      'bio' => isset($items['bio']) && $profile && $profile->hasField('field_bio') && !$profile->get('field_bio')->isEmpty()
        ? ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#value' => (string) $profile->get('field_bio')->value, '#element_attributes' => new Attribute(['data-portal-ui' => 'article-author-bio'])]
        : [],
    ];
  }

  /**
   * Ícones das redes preenchidas (somente ícone, clicável). Sem redes ou sem permissão de exibição, nada aparece.
   */
  private function socialIcons(?\Drupal\profile\Entity\ProfileInterface $profile, array $items): array {
    if (!isset($items['redes']) || !$profile) {
      return [];
    }
    $links = [];
    foreach ($this->networks->linksOf($profile) as $key => $uri) {
      $label = (string) SocialNetworks::NETWORKS[$key]['label'];
      $links[] = PortalLinkElement::build('', Url::fromUri($uri), [
        'data-portal-ui' => 'article-author-social-link-' . $key,
        'rel' => 'noopener nofollow',
        'target' => '_blank',
        'aria-label' => $label,
        'title' => $label,
      ]);
    }
    if ($links === []) {
      return [];
    }
    return ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'article-author-social'], 'links' => $links];
  }

}
