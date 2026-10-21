<?php
// ACULTA fork of Drupal H5P 2.0.0-beta1; adapted 2026-10-18.

namespace Drupal\aculta_lms_h5p\Form;

use Drupal\aculta_lms_h5p\aculta_H5PDrupal\aculta_H5PDrupal;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the H5P Admin Settings Form.
 */
class aculta_H5PAdminSettingsForm extends FormBase {

  protected FileSystemInterface $fileSystem;

  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;
  protected aculta_H5PDrupal $framework;

  public function __construct(
    FileSystemInterface $fileSystem,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    aculta_H5PDrupal $framework,
  ) {
    $this->fileSystem = $fileSystem;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->framework = $framework;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('file_system'), $container->get('cache_tags.invalidator'), $container->get('aculta_lms_h5p.framework'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'h5p_admin_settings_form';
  }

  function buildForm(array $form, FormStateInterface $form_state) {

    // make sure core is loaded
    $interface = aculta_H5PDrupal::getInstance();
    $form['h5p_display_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Opções de exibição'),
    ];

    // todo $JM
    /*
    $disable_hub_data = array(
      'errors' => $server_setup_errors,
      'header' => $core->h5pF->t('Confirmation action'),
      'confirmationDialogMsg' => $core->h5pF->t('Do you still want to enable the hub ?'),
      'cancelLabel' => $core->h5pF->t('Cancel'),
      'confirmLabel' => $core->h5pF->t('Confirm')
    );
    $form['h5p_display_options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Display Options'),
      '#attached' => array(
        'js' => array(
          array(
            'data' => 'H5PDisableHubData = ' . json_encode($disable_hub_data) . ';',
            'type' => 'inline'
          ),
          $path . '/vendor/h5p/h5p-core/js/jquery.js',
          $path . '/vendor/h5p/h5p-core/js/h5p-event-dispatcher.js',
          $path . '/vendor/h5p/h5p-core/js/h5p-confirmation-dialog.js',
          $path . '/vendor/h5p/h5p-core/js/settings/h5p-disable-hub.js',
          $path . '/vendor/h5p/h5p-core/js/h5p-display-options.js',
        ),
        'css' => array(
          $path . '/vendor/h5p/h5p-core/styles/h5p-confirmation-dialog.css',
          $path . '/vendor/h5p/h5p-core/styles/h5p.css',
          $path . '/vendor/h5p/h5p-core/styles/h5p-core-button.css'
        )
      )
    );
    */

    $button_behaviours_download = array(
        \H5PDisplayOptionBehaviour::NEVER_SHOW => $this->t('Nunca'),
        \H5PDisplayOptionBehaviour::ALWAYS_SHOW => $this->t('Sempre'),
        \H5PDisplayOptionBehaviour::CONTROLLED_BY_PERMISSIONS => $this->t('Somente quando permitido pelas permissões'),
        \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON => $this->t('Controlado por quem criou, ligado por padrão'),
        \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF => $this->t('Controlado por quem criou, desligado por padrão'),
    );

    $button_behaviours = array(
      \H5PDisplayOptionBehaviour::NEVER_SHOW => $this->t('Nunca exibir'),
      \H5PDisplayOptionBehaviour::ALWAYS_SHOW => $this->t('Exibir sempre'),
      \H5PDisplayOptionBehaviour::CONTROLLED_BY_PERMISSIONS => $this->t('Exibir somente quando permitido pelas permissões'),
      \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_ON => $this->t('Controlado por quem criou, ligado por padrão'),
      \H5PDisplayOptionBehaviour::CONTROLLED_BY_AUTHOR_DEFAULT_OFF => $this->t('Controlado por quem criou, desligado por padrão'),
    );

    $this->addDisplayOption($form['h5p_display_options'], 'h5p_frame', $this->t('Exibir botões de download, incorporação e direitos autorais'), $interface->getOption('frame', 1), '.form-item-h5p-export, .form-item-h5p-embed, .form-item-h5p-copyright, .form-item-h5p-icon');
    $form['h5p_display_options']['h5p_export'] = array(
      '#title' => $this->t('Permitir download'),
      '#options' => $button_behaviours_download,
      '#default_value' => $interface->getOption('export'),
      '#type' => 'select',
    );

    $form['h5p_display_options']['h5p_embed'] = array(
      '#title' => $this->t('Botão de incorporação'),
      '#options' => $button_behaviours,
      '#default_value' => $interface->getOption('embed'),
      '#type' => 'select',
    );

    $this->addDisplayOption($form['h5p_display_options'], 'h5p_copyright', $this->t('Botão de direitos autorais'), $interface->getOption('copyright'));
    $this->addDisplayOption($form['h5p_display_options'], 'h5p_icon', $this->t('Botão Sobre o H5P'), $interface->getOption('icon'));

    $dir = $this->fileSystem->realpath('private://');
    $form['h5p_default_path'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Diretório privado dos arquivos H5P'),
      '#default_value' => $interface->getOption('default_path', 'h5p'),
      '#description' => $this->t('Subdiretório em %dir para armazenar os arquivos H5P. Use apenas letras, números, hífen, sublinhado e barras entre pastas.', ['%dir' => $dir]),
    );

    $form['h5p_revisioning'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Guardar os arquivos de cada revisão'),
      '#default_value' => $interface->getOption('revisioning', 1),
      '#description' => $this->t('Desative para economizar espaço. Isso não apaga arquivos de revisões já existentes.'),
    );

    $form['h5p_whitelist'] = array(
      '#type' => 'textfield',
      '#maxlength' => 8192,
      '#title' => $this->t('Extensões permitidas para os arquivos de conteúdo'),
      '#default_value' => $interface->getOption('whitelist', \H5PCore::$defaultContentWhitelist),
      '#description' => $this->t('Separe as extensões por espaço, por exemplo: png jpg jpeg gif webm mp4 ogg mp3. Alterar esta lista afeta a segurança; nunca permita extensões executáveis como php.'),
    );

    $form['h5p_library_whitelist_extras'] = array(
      '#type' => 'textfield',
      '#maxlength' => 8192,
      '#title' => $this->t('Extensões adicionais permitidas nas bibliotecas'),
      '#default_value' => $interface->getOption('library_whitelist_extras', \H5PCore::$defaultLibraryWhitelistExtras),
      '#description' => $this->t('Algumas bibliotecas precisam de extensões adicionais. Alterar esta lista afeta a segurança; nunca permita extensões executáveis como php.'),
    );

    $form['h5p_save_content_state'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Salvar o estado das atividades'),
      '#default_value' => $interface->getOption('save_content_state', 0),
      '#description' => $this->t('Salva automaticamente o estado da atividade para que cada pessoa possa continuar de onde parou.'),
    );

    $form['h5p_save_content_frequency'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Intervalo para salvar o estado'),
      '#default_value' => $interface->getOption('save_content_frequency', 30),
      '#description' => $this->t('Intervalo em segundos entre salvamentos automáticos do progresso.'),
    );

    $form['h5p_enable_lrs_content_types'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Permitir tipos que dependem de um LRS'),
      '#default_value' => $interface->getOption('enable_lrs_content_types', 0),
      '#description' => $this->t('Permite tipos de atividade que precisam de um Learning Record Store (LRS), como Questionário.'),
    );

    $form['h5p_external_services'] = [
      '#type' => 'item',
      '#title' => $this->t('Serviços externos do H5P'),
      '#markup' => '<p>' . $this->t('O Hub de conteúdo e o envio de estatísticas estão desativados nesta instalação.') . '</p>',
    ];

    // TODO: Be able to change site key
//  $site_key = variable_get('h5p_site_key', variable_get('h5p_site_uuid', ''));
//  $form['h5p_site_key'] = array(
//    '#type' => 'textfield',
//    '#title' => t('Site Key'),
//    '#default_value' => '',
//    '#attributes' => array(
//      'data-value' => $site_key,
//      'placeholder' => ($site_key ? '********-****-****-****-************' : t('Empty'))
//    ),
//    '#description' => t("The site key is a secret that uniquely identifies this site with the Hub."),
//  );

    // Development mode settings.
    $form['h5p_development_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Modo de desenvolvimento'),
      '#description' => $this->t('Permite desenvolver conteúdo e bibliotecas localmente.'),
      '#default_value' => $interface->getOption('development_mode', 0),
      '#options' => [
        \H5PDevelopment::MODE_NONE => $this->t('Desativado'),
        \H5PDevelopment::MODE_CONTENT => $this->t('Conteúdo'),
        \H5PDevelopment::MODE_LIBRARY => $this->t('Biblioteca'),
      ],
    ];


    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Salvar'),
    );

    return $form;
  }

  /**
   * Form validation handler for admin settings form.
   */
  function validateForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    // Try to create directories and warn the user of errors.
    $h5p_default_path = $values['h5p_default_path'];
    if (!preg_match('@^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$@i', (string) $h5p_default_path)) {
      $form_state->setErrorByName('h5p_default_path', $this->t('Informe um caminho relativo usando letras, números, hífen, sublinhado e barras.'));
      return;
    }
    $path = 'private://' . $h5p_default_path;
    $temp_path = $path . '/' . 'temp';

    if (!$this->fileSystem->prepareDirectory($path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('h5p_default_path', $this->t('O diretório informado é inválido.'));
    }
    if (!$this->fileSystem->prepareDirectory($temp_path, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $form_state->setErrorByName('h5p_default_path', $this->t('O diretório informado é inválido.'));
    }

    if (! is_numeric($values['h5p_save_content_frequency']) || $values['h5p_save_content_frequency'] < 0) {
      $form_state->setErrorByName('h5p_save_content_frequency', $this->t('Informe um número maior ou igual a zero.'));
    }
  }

  /**
   * Form submit handler for h5p admin settings form.
   */
  function submitForm(array &$form, FormStateInterface $form_state) {

    $interface = aculta_H5PDrupal::getInstance();
    $values = $form_state->getValues();

    $interface->setOption('frame', $values['h5p_frame']);
    $interface->setOption('export', $values['h5p_export']);
    $interface->setOption('embed', $values['h5p_embed']);
    $interface->setOption('copyright', $values['h5p_copyright']);
    $interface->setOption('icon', $values['h5p_icon']);

    // Ensure that 'h5p_default_path' variable contains no trailing slash.
    $values['h5p_default_path'] = rtrim($values['h5p_default_path'], '/\\');
    $interface->setOption('default_path', $values['h5p_default_path']);

    $interface->setOption('revisioning', $values['h5p_revisioning']);

    // Ensure that the h5p white list is always stored in lower case.
    $values['h5p_whitelist'] = mb_strtolower($values['h5p_whitelist']);
    $interface->setOption('whitelist', $values['h5p_whitelist']);

    $interface->setOption('library_whitelist_extras', $values['h5p_library_whitelist_extras']);

    $interface->setOption('save_content_state', $values['h5p_save_content_state']);
    $interface->setOption('save_content_frequency', $values['h5p_save_content_frequency']);
    $interface->setOption('enable_lrs_content_types', $values['h5p_enable_lrs_content_types']);
    $interface->setOption('hub_is_enabled', 0);
    $interface->setOption('send_usage_statistics', 0);
    $interface->setOption('development_mode', $values['h5p_development_mode']);

    $this->cacheTagsInvalidator->invalidateTags(['h5p_content']);

    // TODO: Be able to change site key
//  if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $form_state['values']['h5p_site_key'])) {
//    // Invalid key, use the old one
//    $form_state['values']['h5p_site_key'] = variable_get('h5p_site_key', variable_get('h5p_site_uuid', ''));
//  }
  }

  /** Adds one H5P display option with an optional visibility selector. */
  private function addDisplayOption(array &$element, string $name, string $title, mixed $value, ?string $visibilitySubjectId = NULL): void {
    $element[$name] = [
      '#type' => 'checkbox',
      '#title' => $title,
      '#default_value' => $value,
    ];
    if ($visibilitySubjectId !== NULL) {
      $element[$name]['#attributes'] = [
        'data-h5p-visibility-subject-selector' => $visibilitySubjectId,
      ];
    }
  }

}
