<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\aculta_user\Legacy\LegacyAccountManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Memorial coletivo das autorias históricas do Fórum em /u/lendashistoricas (ACULTA Fórum, F2, F-D07).
 *
 * Lista os nomes históricos (sem página individual, sem e-mail), com busca e paginação. Sem dados importados,
 * mostra o estado vazio. O texto aprovado do memorial é pendência da F2.
 */
final class LegacyMemorialController extends ControllerBase {

  /** Itens por página. */
  private const PAGE_SIZE = 50;

  /** Tamanho máximo da busca digitada. */
  private const QUERY_MAX = 80;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Renderiza o memorial com busca, lista paginada e estado vazio.
   */
  public function view(Request $request): array {
    $termo = mb_substr(trim((string) $request->query->get('q', '')), 0, self::QUERY_MAX);

    $query = $this->entityTypeManagerService->getStorage('profile')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', LegacyAccountManager::PROFILE_TYPE)
      ->condition('field_forum_origem_id', '', '<>')
      ->condition('field_nome_historico', '', '<>')
      ->sort('field_nome_historico', 'ASC')
      ->pager(self::PAGE_SIZE);
    if ($termo !== '') {
      $query->condition('field_nome_historico', $termo, 'CONTAINS');
    }
    $perfis = $this->entityTypeManagerService->getStorage('profile')->loadMultiple($query->execute());

    $nomes = [];
    foreach ($perfis as $perfil) {
      $nomes[] = Html::escape((string) $perfil->get('field_nome_historico')->value);
    }

    $build = [
      '#cache' => [
        'contexts' => ['url.query_args', 'user.permissions'],
        'tags' => ['profile_list', 'user_list'],
        'max-age' => 3600,
      ],
      'texto' => [
        '#markup' => '<p>' . $this->t('Memorial da comunidade. O texto aprovado está pendente de aprovação.') . '</p>',
      ],
      'busca' => [
        '#markup' => '<form method="get" action="" role="search"><label for="memorial-busca">'
          . $this->t('Buscar pelo nome') . '</label> <input id="memorial-busca" type="search" name="q" value="'
          . Html::escape($termo) . '" maxlength="' . self::QUERY_MAX . '"> <button type="submit">'
          . $this->t('Buscar') . '</button></form>',
      ],
    ];

    if ($nomes === []) {
      $build['vazio'] = [
        '#markup' => '<p>' . ($termo === ''
          ? $this->t('Nenhuma autoria histórica foi publicada ainda.')
          : $this->t('Nenhum nome encontrado para a busca.')) . '</p>',
      ];
      return $build;
    }

    $build['lista'] = ['#theme' => 'item_list', '#items' => $nomes];
    $build['paginacao'] = ['#type' => 'pager'];
    return $build;
  }

}
