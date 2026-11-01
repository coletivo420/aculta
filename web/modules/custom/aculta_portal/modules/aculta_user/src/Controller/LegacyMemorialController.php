<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Controller;

use Drupal\Component\Utility\Html;
use Drupal\aculta_user\Avatar\AvatarResolver;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Template\Attribute;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Memorial coletivo das autorias históricas do Fórum.
 *
 * Lista os nomes históricos em /u/lendashistoricas, sem página individual ou
 * e-mail, com busca e paginação. Sem dados importados, mostra o estado vazio.
 * O texto aprovado do memorial é pendência da F2 do Fórum.
 */
final class LegacyMemorialController extends ControllerBase {

  /**
   * Itens por página.
   */
  private const PAGE_SIZE = 50;

  /**
   * Tamanho máximo da busca digitada.
   */
  private const QUERY_MAX = 80;

  /**
   * Nome exibido na caixa de perfil do memorial.
   */
  private const NOME = 'Lendas históricas do Growroom';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AvatarResolver $avatars,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('aculta_user.avatar_resolver'),
    );
  }

  /**
   * Renderiza o memorial com busca, lista paginada e estado vazio.
   */
  public function view(Request $request): array {
    $termo = mb_substr(trim((string) $request->query->get('q', '')), 0, self::QUERY_MAX);

    $storage = $this->entityTypeManagerService->getStorage('aculta_legado_autor');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('nome', 'ASC')
      ->pager(self::PAGE_SIZE);
    if ($termo !== '') {
      $query->condition('nome', $termo, 'CONTAINS');
    }
    $autorias = $storage->loadMultiple($query->execute());

    $nomes = [];
    foreach ($autorias as $autoria) {
      $nomes[] = Html::escape((string) $autoria->get('nome')->value);
    }

    $build = [
      '#cache' => [
        'contexts' => ['url.query_args', 'user.permissions'],
        'tags' => ['aculta_legado_autor_list'],
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
   * Builds the memorial profile box.
   *
   * Usa a estrutura pública e o avatar do acervo fornecido pelo resolver
   * central. A aparência vem do tema; o módulo só entrega marcação semântica.
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
          'name' => [
            '#theme' => 'aculta_portal_element',
            '#tag' => 'h2',
            '#value' => self::NOME,
            '#element_attributes' => new Attribute([
              'data-portal-ui' => 'public-profile-name',
            ]),
          ],
        ],
      ],
      'bio' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'public-profile-bio'],
        'paragrafos' => array_map(
          fn(string $texto) => [
            '#theme' => 'aculta_portal_element',
            '#tag' => 'p',
            '#value' => $texto,
          ],
          $bio,
        ),
      ],
    ];
  }

  /**
   * Uses the same institutional avatar source as the rest of the Portal.
   */
  private function avatar(): array {
    $wrap = [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'public-profile-avatar'],
    ];
    $image = $this->avatars->build(
      NULL,
      self::NOME,
      FALSE,
      new CacheableMetadata(),
      ['data-portal-ui' => 'public-profile-avatar-image'],
    );
    if ($image !== NULL) {
      $wrap['image'] = $image;
    }
    return $wrap;
  }

}
