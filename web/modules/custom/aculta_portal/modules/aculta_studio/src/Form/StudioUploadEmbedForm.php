<?php

declare(strict_types=1);

namespace Drupal\aculta_studio\Form;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\RecentUploads;
use Drupal\aculta_portal\Upload\UserUploadDialogBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drop zone embutida na casca do Studio, uma por integração.
 *
 * Recebe o arquivo pela política da integração (aculta_file_uploader) e o deixa
 * temporário. Quem decide o uso é a própria integração, ao referenciá-lo, e ela
 * valida a permissão no salvar. Esta página não cria nenhum conteúdo.
 */
final class StudioUploadEmbedForm extends FormBase {

  /**
   * Integrações que o Studio expõe. Usuário (foto) fica fora desta página.
   *
   * @var array<string, string>
   */
  public const INTEGRACOES = [
    'wiki' => 'Wiki',
    'forum' => 'Fórum',
    'galeria' => 'Galeria',
    'revista' => 'Revista',
    'lms' => 'LMS',
  ];

  /**
   * Diretório privado de cada envio, separado por integração.
   */
  public const DIRECTORY = 'private://studio/envio';

  public function __construct(
    private readonly ConsumerUploadPolicy $policy,
    private readonly UserUploadDialogBuilder $dialogBuilder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RecentUploads $recentUploads,
    private readonly FileUsageInterface $fileUsage,
    private readonly AccountProxyInterface $currentUser,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('aculta_file_uploader.consumer_policy'),
      $container->get('aculta_portal.user_upload_dialog_builder'),
      $container->get('entity_type.manager'),
      $container->get('aculta_file_uploader.recent_uploads'),
      $container->get('file.usage'),
      $container->get('current_user'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_studio_upload_embed_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $integracao = ''): array {
    if (!isset(self::INTEGRACOES[$integracao])) {
      throw new \InvalidArgumentException('Integração fora da lista do Studio.');
    }
    $form['#attributes']['class'][] = 'aculta-studio-upload-embed';
    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Envie o arquivo aqui. Ele fica temporário até a integração escolhida usá-lo; a própria integração decide o uso e valida a permissão.'),
    ];
    $form['integracao'] = ['#type' => 'hidden', '#value' => $integracao];
    $this->dialogBuilder->addFileControls($form, $integracao, self::DIRECTORY . '/' . $integracao, 'image_file', 'document_file', TRUE);
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enviar'),
      '#button_type' => 'primary',
    ];
    $form['recentes'] = $this->recentList($integracao);
    return $form;
  }

  /**
   * Lista os envios recentes da integração: os do usuário, ou todos se administrador.
   *
   * @return array<string, mixed>
   */
  private function recentList(string $integracao): array {
    $isAdmin = $this->currentUser->hasPermission('administer site configuration');
    $files = $this->recentUploads->forUser((int) $this->currentUser->id(), $integracao, 10, $isAdmin);
    $rows = [];
    foreach (array_keys($files) as $fid) {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$file instanceof FileInterface) {
        continue;
      }
      $inUse = $this->fileUsage->listUsage($file) !== [];
      $rows[] = [
        $file->getFilename(),
        $this->dateFormatter->format((int) $file->getCreatedTime(), 'short'),
        $inUse ? $this->t('Em uso') : $this->t('Sem uso ainda'),
      ];
    }
    return [
      '#type' => 'details',
      '#title' => $this->t('Envios recentes'),
      '#open' => TRUE,
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Envios sem uso são apagados após 6 horas, para imagens e documentos.'),
      ],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Arquivo'), $this->t('Enviado em'), $this->t('Situação')],
        '#rows' => $rows,
        '#empty' => $this->t('Nenhum envio recente nesta integração.'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $integracao = (string) $form_state->getValue('integracao');
    if (!isset(self::INTEGRACOES[$integracao])) {
      $form_state->setErrorByName('integracao', $this->t('Integração desconhecida.'));
      return;
    }
    // Revalida o conteúdo pela política da integração, além do Managed File.
    $loader = $this->entityTypeManager->getStorage('file');
    foreach (['image_file' => TRUE, 'document_file' => FALSE] as $key => $is_image) {
      foreach ((array) $form_state->getValue($key) as $fid) {
        $file = $loader->load((int) $fid);
        $valid = $file !== NULL && ($is_image
          ? $this->policy->isValidImage($file, $integracao)
          : $this->policy->isValidDocument($file, $integracao));
        if (!$valid) {
          $form_state->setErrorByName($key, $this->t('O arquivo não atende às extensões ou ao tamanho permitidos.'));
          return;
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $total = 0;
    foreach (['image_file', 'document_file'] as $key) {
      $total += count(array_filter((array) $form_state->getValue($key)));
    }
    $this->messenger()->addStatus($this->formatPlural($total, 'Um arquivo recebido.', '@count arquivos recebidos.'));
  }

}
