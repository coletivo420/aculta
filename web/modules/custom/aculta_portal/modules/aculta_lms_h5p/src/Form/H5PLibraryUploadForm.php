<?php

declare(strict_types=1);

namespace Drupal\aculta_lms_h5p\Form;

use Drupal\aculta_lms_h5p\H5PDrupal\H5PLibraryPackageInstaller;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Validation\FileValidatorSettingsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Adds reviewed local H5P library packages through the H5P validator. */
final class H5PLibraryUploadForm extends FormBase {

  use FileValidatorSettingsTrait;

  /** H5P package installer. */
  protected H5PLibraryPackageInstaller $installer;

  /** Entity type manager. */
  protected EntityTypeManagerInterface $entityTypeManager;

  /** H5P configuration factory. */
  protected ConfigFactoryInterface $h5pConfigFactory;

  /** Resolves managed file URIs to local paths for the H5P package validator. */
  protected FileSystemInterface $fileSystem;

  public function __construct(
    H5PLibraryPackageInstaller $installer,
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $h5pConfigFactory,
    FileSystemInterface $fileSystem,
  ) {
    $this->installer = $installer;
    $this->entityTypeManager = $entityTypeManager;
    $this->h5pConfigFactory = $h5pConfigFactory;
    $this->fileSystem = $fileSystem;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_lms_h5p.library_package_installer'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('file_system'),
    );
  }

  public function getFormId(): string {
    return 'aculta_lms_h5p_library_upload_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $storagePath = (string) ($this->h5pConfigFactory->get('aculta_lms_h5p.settings')->get('aculta_lms_h5p_default_path') ?? 'h5p');
    if (!preg_match('@^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$@iD', $storagePath)) {
      throw new \RuntimeException('The private H5P storage path is invalid.');
    }
    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('Instalar um pacote de bibliotecas'),
      '#description' => $this->t('Envie um pacote .h5p confiável. As bibliotecas serão validadas e instaladas; atividades e conteúdos incluídos no pacote serão ignorados.'),
    ];
    $form['package'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Pacote H5P'),
      '#required' => TRUE,
      '#upload_location' => "private://{$storagePath}/packages",
      '#upload_validators' => $this->getFileUploadValidators(['file_extensions' => 'h5p']),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Instalar bibliotecas'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('package');
    $file = is_array($fids) && isset($fids[0]) ? $this->entityTypeManager->getStorage('file')->load($fids[0]) : NULL;
    if (!$file || !str_ends_with(strtolower($file->getFilename()), '.h5p') || $file->getFileUri() === '') {
      $form_state->setErrorByName('package', $this->t('Selecione um pacote .h5p válido.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('package');
    $file = $this->entityTypeManager->getStorage('file')->load($fids[0] ?? 0);
    if (!$file) {
      $form_state->setErrorByName('package', $this->t('Não foi possível localizar o pacote enviado.'));
      return;
    }
    try {
      $packagePath = $this->fileSystem->realpath($file->getFileUri());
      if ($packagePath === FALSE) {
        throw new \UnexpectedValueException('The uploaded H5P package is not accessible.');
      }
      $count = $this->installer->install($packagePath);
      $this->messenger()->addStatus($this->formatPlural($count, 'Uma biblioteca H5P foi instalada.', '@count bibliotecas H5P foram instaladas.'));
    }
    catch (\Throwable $exception) {
      $this->getLogger('aculta_lms_h5p')->warning('H5P library package installation failed: @message', ['@message' => $exception->getMessage()]);
      $this->messenger()->addError($this->t('Não foi possível instalar o pacote. Verifique se ele contém bibliotecas H5P válidas e compatíveis.'));
    }
    finally {
      $this->entityTypeManager->getStorage('file')->delete([$file]);
    }
  }

}
