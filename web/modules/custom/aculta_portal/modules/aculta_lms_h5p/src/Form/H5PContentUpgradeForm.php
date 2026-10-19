<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Form;

use Drupal\aculta_lms_h5p\H5PDrupal\H5PDrupal;
use Drupal\aculta_lms_h5p\Controller\H5PContentUpgrade;
use Drupal\aculta_lms_h5p\Controller\H5PLibraryAdmin;

use Drupal\Core\Asset\AssetQueryStringInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements the H5PContentUpgradeForm form.
 */
class H5PContentUpgradeForm extends FormBase {

  protected Connection $database;

  protected ModuleExtensionList $moduleList;

  protected FileUrlGeneratorInterface $fileUrlGenerator;

  protected AssetQueryStringInterface $assetQueryString;

  public function __construct(
    Connection $database,
    ModuleExtensionList $moduleList,
    FileUrlGeneratorInterface $fileUrlGenerator,
    AssetQueryStringInterface $assetQueryString,
  ) {
    $this->database = $database;
    $this->moduleList = $moduleList;
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->assetQueryString = $assetQueryString;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('extension.list.module'),
      $container->get('file_url_generator'),
      $container->get('asset.query_string'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_content_upgrade_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $library_id = NULL) {
    $versions = H5PContentUpgrade::getLibraryVersions($this->database, $library_id);
    if (!isset($versions[$library_id])) {
      return ['#markup' => $this->t('Biblioteca não encontrada.')];
    }
    if (count($versions) < 2) {
      return ['#markup' => $this->t('Não há atualizações disponíveis para esta biblioteca.')];
    }

    $library = $versions[$library_id];
    $core = H5PDrupal::getInstance('core');
    $upgrades = $core->getUpgrades($library, $versions);

    // Get num of contents that can be upgraded
    $contents = $core->h5pF->getNumContent($library_id);
    if (!$contents) {
      return ['#markup' => $this->t('Não há atividades para atualizar.')];
    }

    $contents_plural = $this->formatPlural($contents, '1 atividade', '@count atividades');
    $returnLink = Link::fromTextAndUrl($this->t('Voltar'), Url::fromUri('internal:/admin/content/h5p/'))->toString();
    $h5p_module_rel = base_path() . $this->moduleList->getPath('aculta_lms_h5p');
    $settings = [
      'libraryInfo' => [
        'message' => $this->t('Você vai atualizar %num. Selecione a versão de destino.', ['%num' => $contents_plural]),
        'inProgress' => $this->t('Atualizando para %ver...'),
        'error' => $this->t('Ocorreu um erro ao processar os parâmetros:'),
        'errorData' => $this->t('Não foi possível carregar os dados da biblioteca %lib.'),
        'errorScript' => $this->t('Não foi possível carregar o script de atualização de %lib.'),
        'errorContent' => $this->t('Não foi possível atualizar a atividade %id:'),
        'errorParamsBroken' => $this->t('Os parâmetros estão inválidos.'),
        'errorLibrary' => $this->t('A biblioteca obrigatória %lib não foi encontrada.'),
        'errorTooHighVersion' => $this->t('Os parâmetros usam %used, mas somente %supported ou versões anteriores são aceitas.'),
        'errorNotSupported' => $this->t('Os parâmetros usam %used, que não é aceito.'),
        'done' => $this->t('As atividades foram atualizadas: %num.', ['%num' => $contents_plural]) . $returnLink,
        'library' => [
          'name' => $library->machine_name,
          'version' => $library->major_version . '.' . $library->minor_version,
        ],
        'libraryBaseUrl' => Url::fromUri('internal:/admin/content/h5p/upgrade/library')->toString(),
        'scriptBaseUrl' => $this->fileUrlGenerator->generate("assets://h5p/h5p-core/js/")->toString(),
        'buster' => '?' . $this->assetQueryString->get(),
        'versions' => $upgrades,
        'contents' => $contents,
        'buttonLabel' => $this->t('Atualizar'),
        'infoUrl' => Url::fromRoute('aculta_lms_h5p.admin.content.aculta_lms_h5p.libraries.upgrade', ['library_id' => $library_id])->toString(),
        'total' => $contents,
        'token' => \H5PCore::createToken('contentupgrade'), // Use token to avoid unauthorized updating
      ]
    ];

    // Create page - add settings and JS
    $build['#markup'] = '<div id="h5p-admin-container">' . $this->t('Ative o JavaScript para usar esta página.') . '</div>';
    // The upgrade endpoint URL contains a session-bound Drupal CSRF token.
    $build['#cache']['contexts'][] = 'session';
    $build['#attached'] = H5PLibraryAdmin::addSettings($settings);
    $build['#attached']['library'][] = 'aculta_lms_h5p/h5p.admin.library.upgrade';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This is intentionally left blank
  }
}
