<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\aculta_magazine\Presentation\PostPresentation;
use Drupal\aculta_magazine\Revista\RevistaPublications;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_user\Identity\AuthorCardBuilder;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Template\Attribute;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Caixa de autores da Revista: autor principal e coautores editoriais.
 *
 * Só aparece em publicações da Revista publicadas, no purpose MAGAZINE. A
 * identidade vem do cartão compartilhado de aculta_user; Magazine acrescenta
 * somente o papel editorial de autor ou coautor.
 *
 * Este é o único `node_view` do módulo.
 */
final class ArticleAuthorsHooks {

  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_portal.domain_purpose')]
    private readonly DomainPurposeManager $purposeManager,
    #[Autowire(service: 'aculta_magazine.post_presentation')]
    private readonly PostPresentation $presentation,
    #[Autowire(service: 'aculta_user.author_card_builder')]
    private readonly AuthorCardBuilder $authorCardBuilder,
  ) {}

  /**
   * Adiciona a caixa de autores à publicação da Revista.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, EntityInterface $entity, $display, $view_mode): void {
    if (!$entity instanceof NodeInterface
      || !RevistaPublications::isPublication($entity->bundle())
      || $entity->isNew()
      || !$entity->isPublished()) {
      return;
    }
    if ($view_mode !== 'full' || $this->purposeManager->getCurrentPurpose() !== 'magazine') {
      return;
    }

    // Cabeçalho editorial e caixa de autores da publicação.
    $this->presentation->build($build, $entity);

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
      $item = $this->authorItem($entry['user'], $entry['role']);
      if ($item !== NULL) {
        $items[] = $item;
      }
    }
    if ($items === []) {
      return;
    }
    $build['article_authors'] = [
      '#type' => 'container',
      '#weight' => 100,
      '#attributes' => ['data-portal-ui' => 'article-authors'],
      'title' => [
        '#theme' => 'aculta_portal_element',
        '#tag' => 'h2',
        '#value' => $this->t('Autoria'),
        '#element_attributes' => new Attribute(['data-portal-ui' => 'article-authors-title']),
      ],
      'list' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'article-authors-list'],
        'items' => $items,
      ],
      '#cache' => [
        'tags' => $cache->getCacheTags(),
        'contexts' => array_merge($cache->getCacheContexts(), ['url.path']),
      ],
    ];
  }

  /**
   * Builds the shared identity card with Magazine-owned author/co-author role.
   *
   * @return array|null
   *   The render array for this author, or NULL without a public identity.
   */
  private function authorItem(UserInterface $user, string $role): ?array {
    $designation = (string) ($role === 'author' ? $this->t('Autor') : $this->t('Coautor'));
    $card = $this->authorCardBuilder->build($user, 'author_editorial', [], $designation);
    if ($card === NULL) {
      return NULL;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'article-author'],
      'card' => $card,
    ];
  }

}
