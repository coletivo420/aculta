<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Template\Attribute;
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

  /** Nome exibido na caixa de perfil do memorial. */
  private const NOME = 'Lendas históricas do Growroom';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly ExtensionList $modules,
    private readonly string $appRoot,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
      $container->getParameter('app.root'),
    );
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
      'perfil' => $this->profileBox(),
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

  /**
   * Caixa de perfil do memorial, com a mesma estrutura do perfil público (data-portal-ui), avatar único deste endereço
   * e a bio aprovada. A aparência vem do tema; o módulo só entrega a marcação semântica.
   */
  private function profileBox(): array {
    $bio = [
      (string) $this->t('Este é o memorial coletivo do Growroom, o fórum que reuniu, entre 2002 e 2025, pessoas interessadas em cultivo, cultura canábica e debate sobre políticas de drogas.'),
      (string) $this->t('Aqui ficam registrados os nomes de quem participou, ligados às suas próprias publicações, como memória da comunidade.'),
      (string) $this->t('Este memorial não indica falecimento de ninguém.'),
    ];
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'public-profile'],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'public-profile-header'],
        'avatar' => $this->avatar(),
        'identity' => [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'public-profile-identity'],
          'name' => ['#theme' => 'aculta_portal_element', '#tag' => 'h2', '#value' => self::NOME, '#element_attributes' => new Attribute(['data-portal-ui' => 'public-profile-name'])],
        ],
      ],
      'bio' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'public-profile-bio'],
        'paragrafos' => array_map(fn(string $texto) => ['#theme' => 'aculta_portal_element', '#tag' => 'p', '#value' => $texto], $bio),
      ],
    ];
  }

  /**
   * Avatar único do memorial: a arte aprovada, quando o arquivo existir; sem ela, a inicial.
   */
  private function avatar(): array {
    $wrap = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'public-profile-avatar']];
    $arquivo = $this->appRoot . '/' . $this->modules->getPath('aculta_user') . '/' . LegacyAccountManager::AVATAR_SOURCE;
    if (is_file($arquivo)) {
      $wrap['image'] = [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => '/' . $this->modules->getPath('aculta_user') . '/' . LegacyAccountManager::AVATAR_SOURCE,
          'alt' => self::NOME,
          'data-portal-ui' => 'public-profile-avatar-image',
        ],
      ];
    }
    else {
      $wrap['initial'] = ['#theme' => 'aculta_portal_element', '#tag' => 'span', '#value' => 'L', '#element_attributes' => new Attribute(['data-portal-ui' => 'public-profile-initial', 'aria-hidden' => 'true'])];
    }
    return $wrap;
  }

}
