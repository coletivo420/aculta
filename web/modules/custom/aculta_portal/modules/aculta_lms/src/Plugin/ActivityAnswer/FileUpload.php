<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Plugin\ActivityAnswer;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\file\Validation\FileValidatorSettingsTrait;
use Drupal\lms\Attribute\ActivityAnswer;
use Drupal\lms\Entity\Answer;
use Drupal\lms\Entity\AnswerInterface;
use Drupal\lms\Plugin\ActivityAnswerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * File upload answer plugin, ported from lms_file_upload.
 *
 * Keeps the plugin id, the private directory and the file usage names of the
 * contrib module, so answers saved before the port keep their files.
 */
#[ActivityAnswer(
  id: 'file_upload',
  name: new TranslatableMarkup('File upload'),
)]
final class FileUpload extends ActivityAnswerBase implements PluginFormInterface {

  use FileValidatorSettingsTrait;

  /**
   * Upload directory. Kept from the contrib module so existing files stay put.
   */
  public const UPLOAD_DIRECTORY = 'private://lms_file_upload';

  /**
   * File usage module and type. Kept from the contrib module for existing rows.
   */
  public const USAGE_MODULE = 'lms_file_upload';

  public const USAGE_TYPE = 'lms_answer';

  /**
   * Route that streams the answer file.
   */
  public const DOWNLOAD_ROUTE = 'aculta_lms.answer_file_download';

  protected FileUsageInterface $fileUsage;

  protected ConsumerUploadPolicy $uploadPolicy;

  /**
   * {@inheritdoc}
   */
  public function evaluatedOnSave(Answer $activity): bool {
    // Manually evaluated activity.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function injectServices(ContainerInterface $container): void {
    $this->fileUsage = $container->get('file.usage');
    $this->uploadPolicy = $container->get('aculta_file_uploader.consumer_policy');

    parent::injectServices($container);
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\file\Plugin\Field\FieldType\FileItem::fieldSettingsForm
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->configuration;

    $extensions = \str_replace(' ', ', ', $settings['default_file_extensions'] ?? 'txt');
    $form['default_file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default allowed file extensions'),
      '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character. <strong>This value can be overridden on a per-activity basis.</strong>"),
      '#required' => TRUE,
      '#default_value' => $extensions,
      '#element_validate' => [[FileItem::class, 'validateExtensions']],
      '#maxlength' => 256,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function answeringForm(array &$form, FormStateInterface $form_state, Answer $answer): void {
    $activity = $answer->getActivity();
    $data = $answer->getData();

    $default_value = $this->fidFromData($data);
    $default_value = $default_value === NULL ? NULL : [$default_value];
    $default_file_extensions = $this->configuration['default_file_extensions'];
    $file_extensions = $activity->get('field_file_extensions')->first()?->getValue()['value'] ?? $default_file_extensions;
    $file_extensions = \str_replace(',', ' ', $file_extensions);

    $validators = $this->uploadPolicy->restrictValidators(
      $this->getFileUploadValidators(['file_extensions' => $file_extensions]),
      'lms',
    );

    $form['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#description' => $this->t('Upload a file. Allowed extensions: @extensions.', ['@extensions' => $this->uploadPolicy->acceptedExtensions($validators)]),
      '#required' => TRUE,
      '#default_value' => $default_value ? ['fids' => $default_value] : NULL,
      '#upload_location' => self::UPLOAD_DIRECTORY,
      '#upload_validators' => $validators,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitAnsweringForm(array &$form, FormStateInterface $form_state, Answer $answer): void {
    // Remove previous file usage if existed.
    $file = $this->getFile($answer);
    if ($file instanceof FileInterface) {
      $this->fileUsage->delete($file, self::USAGE_MODULE, self::USAGE_TYPE, $answer->id());
    }

    parent::submitAnsweringForm($form, $form_state, $answer);

    // Add new file usage.
    $file = $this->getFile($answer);
    if (!$file instanceof FileInterface) {
      return;
    }
    if ($answer->isNew()) {
      $answer->save();
    }
    $this->fileUsage->add($file, self::USAGE_MODULE, self::USAGE_TYPE, $answer->id());
  }

  /**
   * {@inheritdoc}
   */
  protected function getAnswerRenderable(Answer $answer): array {
    $file = $this->getFile($answer);
    if (!$file instanceof FileInterface) {
      return ['#markup' => $this->t('No file uploaded')];
    }

    $url = Url::fromRoute(self::DOWNLOAD_ROUTE, ['lms_answer' => $answer->id()]);
    if (!$url->access()) {
      return ['#markup' => $this->t('File not accessible.')];
    }

    return [
      '#type' => 'link',
      '#title' => $file->getFilename(),
      '#attributes' => [
        'data-portal-ui' => 'lms-answer-file',
        'target' => '_blank',
      ],
      '#url' => $url,
    ];
  }

  /**
   * Default file extensions of the plugin.
   */
  public function defaultAllowedExtensions(): string {
    return $this->configuration['default_file_extensions'];
  }

  /**
   * Uploaded file of the answer, if any.
   */
  public function getFile(AnswerInterface $answer): ?FileInterface {
    $fid = $this->fidFromData($answer->getData());
    return $fid === NULL ? NULL : $this->entityTypeManager->getStorage('file')->load($fid);
  }

  /**
   * Extrai o fid gravado pelo managed_file do Core.
   *
   * O Form API persiste `['file_upload' => ['fids' => [fid]]]`. O formato
   * antigo `[fid]` também é aceito para respostas gravadas antes do port.
   */
  private function fidFromData(array $data): ?int {
    $value = $data['file_upload'] ?? NULL;
    if (!\is_array($value)) {
      return NULL;
    }
    $fid = $value['fids'][0] ?? $value[0] ?? NULL;
    return \is_numeric($fid) && (int) $fid > 0 ? (int) $fid : NULL;
  }

}
