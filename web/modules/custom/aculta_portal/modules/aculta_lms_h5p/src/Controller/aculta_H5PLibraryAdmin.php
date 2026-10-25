<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Controller;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;
use Drupal\aculta_lms_h5p\Entity\aculta_H5PContent;

use Drupal\Core\Url;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\RequestStack;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class aculta_H5PLibraryAdmin implements ContainerInjectionInterface {

  /**
   * constructor.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly FormBuilderInterface $h5pFormBuilder,
    private readonly RequestStack $requestStack,
    private readonly TranslationInterface $translation,
    private readonly RedirectDestinationInterface $redirectDestination,
    private readonly aculta_H5PDrupal $framework,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('request_stack'),
      $container->get('string_translation'),
      $container->get('redirect.destination'),
      $container->get('aculta_lms_h5p.framework'),
    );
  }

  /**
   * Creates the library list page
   *
   * @return string Html
   */
  function libraryList() {
    $core = aculta_H5PDrupal::getInstance('core');
    $numNotFiltered = $core->h5pF->getNumNotFiltered();
    $libraries = $core->h5pF->loadLibraries();

    // Add settings for each library
    $settings = [];
    $i = 0;
    foreach ($libraries as $versions) {
      foreach ($versions as $library) {
        // TODO: Fix interface, getLibraryUsage only take 1 arg
        $usage = $core->h5pF->getLibraryUsage($library->id, $numNotFiltered ? TRUE : FALSE);
        if ($library->runnable) {
          $upgrades = $core->getUpgrades($library, $versions);

          $url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/upgrade-confirm')->toString();
          $upgradeUrl = empty($upgrades) ? FALSE : $url;

          $restricted = ($library->restricted === '1' ? TRUE : FALSE);
          $option = [
            'query' => [
              'token' => \H5PCore::createToken('library_' . $library->id),
              'token_id' => $library->id,
              'restrict' => ($library->restricted === '1' ? 0 : 1)
            ]
          ];
          $restricted_url = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/restrict', $option)->toString();
        }
        else {
          $upgradeUrl = NULL;
          $restricted = NULL;
          $restricted_url = NULL;
        }

        $option = [
          'query' => $this->redirectDestination->getAsArray(),
        ];
        $deleteUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id . '/delete', $option);
        //$detailsUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library->id);

        $settings['libraryList']['listData'][] = [
          'title' => $library->title . ' (' . \H5PCore::libraryVersion($library) . ')',
          'restricted' => $restricted,
          'restrictedUrl' => $restricted_url,
          'numContent' => $core->h5pF->getNumContent($library->id),
          'numContentDependencies' => $usage['content'] === -1 ? '' : $usage['content'],
          'numLibraryDependencies' => $usage['libraries'],
          'upgradeUrl' => $upgradeUrl,
          //'detailsUrl' => $detailsUrl->toString(),
          'deleteUrl' => $deleteUrl->toString(),
        ];

        $i++;
      }
    }

    // All translations are made server side
    $settings['libraryList']['listHeaders'] = [$this->translation->translate('Título'), $this->translation->translate('Restrita'), $this->translation->translate('Atividades'), $this->translation->translate('Dependências de atividades'), $this->translation->translate('Dependências da biblioteca'), $this->translation->translate('Ações')];

    // Make it possible to rebuild all caches.
    if ($numNotFiltered) {
      $settings['libraryList']['notCached'] = $this->getNotCachedSettings($numNotFiltered);
    }

    $settings['containerSelector'] = '#h5p-admin-container';

    // Add the needed css and javascript
    // Rebuild-cache links carry Drupal's per-session CSRF token.
    $build['#cache']['contexts'][] = 'session';
    $build['#attached'] = self::addSettings($settings);
    $build['#attached']['library'][] = 'aculta_lms_h5p/h5p.admin.library.list';

    $build['title_add'] = ['#type' => 'html_tag', '#tag' => 'h3', '#attributes' => ['data-portal-ui' => 'h5p-admin-header'], '#value' => $this->translation->translate('Adicionar bibliotecas')];
    $build['form'] = $this->h5pFormBuilder->getForm('Drupal\aculta_lms_h5p\Form\aculta_H5PLibraryUploadForm');
    $build['title_installed'] = ['#type' => 'html_tag', '#tag' => 'h3', '#attributes' => ['data-portal-ui' => 'h5p-admin-header'], '#value' => $this->translation->translate('Bibliotecas instaladas')];
    $build['container'] = ['#markup' => '<div id="h5p-admin-container"></div>'];

    return $build;
  }

  /**
   * Settings needed to rebuild cache from UI.
   *
   * @param int $num
   * @return array
   */
  function getNotCachedSettings($num) {
    $url = Url::fromRoute('aculta_lms_h5p.admin.content.aculta_lms_h5p.rebuild_cache');
    return [
      'num' => $num,
      'url' => $url->toString(),
      'message' => $this->translation->translate('Ainda há atividades sem cache atualizado. Reconstrua o cache para excluir bibliotecas e ver quantas atividades usam cada uma.'),
      'progress' => $this->translation->formatPlural($num, '1 conteúdo precisa reconstruir o cache.', '@count conteúdos precisam reconstruir o cache.'),
      'button' => $this->translation->translate('Reconstruir cache')
    ];
  }

  /**
   * Restrict a library
   *
   * @param string $library_id
   *
   * @return JsonResponse
   */
  function restrict($library_id) {
    $request = $this->requestStack->getCurrentRequest();
    $restricted = $request?->query->getString('restrict');
    $restrict = ($restricted === '1');

    $token_id = $request?->query->getString('token_id', '');
    $token = $request?->query->getString('token', '');
    if ($request === NULL || !ctype_digit((string) $library_id) || $token_id !== (string) $library_id
      || !\H5PCore::validToken('library_' . $token_id, $token) || (!$restrict && $restricted !== '0')) {
      return new JsonResponse('Biblioteca inválida.', 403);
    }

    $this->database->update('h5p_libraries')
      ->fields(['restricted' => $restricted])
      ->condition('library_id', $library_id)
      ->execute();

    $restrictUrl = Url::fromUri('internal:/admin/content/h5p/libraries/' . $library_id . '/restrict', [
      'query' => [
        'token' => \H5PCore::createToken('library_' . $token_id),
        'token_id' => $token_id,
        'restrict' => ($restrict ? 0 : 1),
      ]
    ]);

    return new JsonResponse(['url' => $restrictUrl->toString()]);
  }

  /**
   * Callback for rebuilding all content cache.
   *
   * @return JsonResponse
   */
  function rebuildCache() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL || $request->getMethod() !== 'POST') {
      return new JsonResponse('Método não permitido.', 403);
    }

    // Do as many as we can in ten seconds.
    $start = microtime(TRUE);
    $query = $this->database->select('h5p_content', 'hc')
      ->fields('hc', ['id'])
      ->isNull('hc.filtered_parameters');
    $num_rows = $query->countQuery()->execute()->fetchField();
    $result = $query->execute();

    $done = 0;
    foreach ($result as $row) {
      $h5p_content = aculta_H5PContent::load($row->id);
      $h5p_content->getFilteredParameters();
      $done++;

      if ((microtime(TRUE) - $start) > 10) {
        break;
      }
    }

    $count = $num_rows - $done;

    return new JsonResponse((string)$count);
  }


  /**
   * Helper function - creates admin settings
   *
   * @param array $settings
   * @return array
   */
  public static function addSettings($settings = NULL) {
    $settings['containerSelector'] = '#h5p-admin-container';
    $settings['l10n'] = [
      'NA' => new TranslatableMarkup('Não se aplica'),
      /*'viewLibrary' => t('View library details'),*/
      'deleteLibrary' => new TranslatableMarkup('Excluir biblioteca'),
      'upgradeLibrary' => new TranslatableMarkup('Atualizar atividades da biblioteca')
    ];

    $build['drupalSettings']['h5p']['drupal_h5p_admin_integration'] = [
      'H5PAdminIntegration' =>  $settings,
    ];
    $build['drupalSettings']['h5p']['drupal_h5p'] = [
      'H5P' => aculta_H5PDrupal::getGenericaculta_H5PIntegrationSettings(),
    ];

    return $build;
  }
}
