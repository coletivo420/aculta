<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Forum;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Pastas do Fórum espelhando as sessões da Revista.
 *
 * A pasta-mãe "Revista" (container) agrupa uma pasta de discussão por categoria editorial. Cada pasta guarda a
 * categoria em `field_categoria_revista`, então renomear a sessão não quebra o vínculo. Pasta ausente é criada
 * na primeira publicação da categoria, para que categoria nova não fique sem tópico.
 *
 * O módulo Forum exige que o tópico fique em pasta folha (`ForumLeaf`), por isso a pasta-mãe nunca recebe tópico.
 */
final class ForumSectionResolver {

  /** Nome da pasta-mãe da Revista no vocabulário `forums`. */
  public const CONTAINER_NAME = 'Revista';

  /** Campo que liga a pasta do Fórum à categoria editorial da Revista. */
  public const SECTION_FIELD = 'field_categoria_revista';

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Pasta-mãe "Revista". Cria se não existir.
   */
  public function container(): TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $existing = $storage->loadByProperties(['vid' => 'forums', 'name' => self::CONTAINER_NAME, 'forum_container' => 1]);
    if ($existing) {
      return reset($existing);
    }

    $term = $storage->create([
      'vid' => 'forums',
      'name' => self::CONTAINER_NAME,
      'forum_container' => 1,
      'description' => 'Discussões das publicações da Revista, na mesma separação das sessões.',
    ]);
    $term->save();
    return $term;
  }

  /**
   * Garante uma pasta para cada categoria editorial da Revista. Retorna as pastas.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   */
  public function ensureAll(): array {
    $categories = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['vid' => 'editorial_category']);
    $sections = [];
    foreach ($categories as $category) {
      $sections[] = $this->sectionFor($category);
    }
    return $sections;
  }

  /**
   * Pasta de discussão da categoria editorial. Cria se não existir.
   */
  public function sectionFor(TermInterface $category): TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $existing = $storage->loadByProperties(['vid' => 'forums', self::SECTION_FIELD => $category->id()]);
    if ($existing) {
      return reset($existing);
    }

    $section = $storage->create([
      'vid' => 'forums',
      'name' => $category->getName(),
      'parent' => [$this->container()->id()],
      'forum_container' => 0,
      self::SECTION_FIELD => ['target_id' => $category->id()],
    ]);
    $section->save();
    return $section;
  }

}
