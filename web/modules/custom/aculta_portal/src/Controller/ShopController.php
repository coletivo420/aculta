<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Página inicial da loja (SHOP). A raiz de loja.aculta serve esta rota, como a raiz de cada subdomínio serve a
 * sua (`/apoio`, `/wiki`, `/cursos`). O conteúdo é o da página "Loja da Associação" (nó 372). O catálogo de produtos
 * ainda não existe; quando existir, entra aqui.
 */
final class ShopController extends ControllerBase {

  /** Nó da página inicial da loja ("Loja da Associação"). */
  private const PAGINA_INICIAL_NODE = 372;

  public function __construct(
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  public function home(): array {
    $pagina = $this->entities->getStorage('node')->load(self::PAGINA_INICIAL_NODE);
    if ($pagina === NULL) {
      return ['#markup' => ''];
    }
    return $this->entities->getViewBuilder('node')->view($pagina, 'full');
  }

}
