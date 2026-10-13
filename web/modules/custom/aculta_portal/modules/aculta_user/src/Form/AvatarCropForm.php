<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\crop\Entity\Crop;
use Drupal\file\FileInterface;
use Drupal\image_widget_crop\ImageWidgetCropInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Popup de recorte da foto personalizada.
 *
 * Fluxo (decisão do responsável): ao enviar a foto, o popup de recorte abre sozinho. Ao aplicar o recorte, o envio
 * é um POST comum (sem AJAX, porque o Core não grava estado de formulário em GET): o recorte é gravado pela API do
 * image_widget_crop, o arquivo fica como pendente, os derivados antigos são apagados e a página volta ao painel
 * com ?aculta_recorte=1, que reabre o popup da foto para o "Salvar".
 *
 * O arquivo a recortar é, nesta ordem: o informado em ?fid= (upload enviado, dono da conta), o pendente já recortado
 * ou a foto personalizada salva. A foto do acervo nunca é recortada aqui.
 */
final class AvatarCropForm extends FormBase {

  /** Tipo de recorte do avatar (config/sync/crop.type.aculta_avatar_1x1.yml). */
  public const CROP_TYPE = 'aculta_avatar_1x1';

  /** Opções do modal de recorte: altura máxima com barra de rolagem quando o conteúdo passa dela. */
  public const DIALOG_OPTIONS = ['width' => 640, 'maxHeight' => 560, 'title' => 'Recortar foto'];

  /** Estilo de imagem usado pela visualização do avatar. */
  public const STYLE = 'aculta_avatar';

  /** Chave em user.data com o arquivo recortado que aguarda o "Salvar" do popup da foto. */
  public const PENDING_KEY = 'recorte_pendente';


  /** Marcador do painel que reabre o popup da foto depois do recorte. */
  public const RETURN_QUERY = 'aculta_recorte';

  public function __construct(
    private readonly ImageWidgetCropInterface $cropManager,
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountInterface $currentUserService,
    private readonly UserDataInterface $userData,
    private readonly RequestStack $cropRequestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('image_widget_crop.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('request_stack'),
    );
  }

  public function getFormId(): string {
    return 'aculta_user_avatar_crop_form';
  }

  /** Arquivo a recortar (ver a ordem na documentação da classe), ou NULL. */
  public function targetFile(): ?FileInterface {
    $uid = (int) $this->currentUserService->id();
    $storage = $this->entityTypeManagerService->getStorage('file');
    $fid = (int) $this->cropRequestStack->getCurrentRequest()?->query->get('fid', 0);
    if ($fid > 0) {
      $file = $storage->load($fid);
      if ($file !== NULL && (int) $file->getOwnerId() === $uid && $this->isCroppable($file)) {
        return $file;
      }
    }
    $pending = (int) $this->userData->get('aculta_user', $uid, self::PENDING_KEY);
    $file = $pending > 0 ? $storage->load($pending) : NULL;
    if ($file !== NULL && $this->isCroppable($file)) {
      return $file;
    }
    $account = $this->entityTypeManagerService->getStorage('user')->load($uid);
    if (!$account instanceof UserInterface) {
      return NULL;
    }
    $current = (int) ($account->get('user_picture')->target_id ?? 0);
    $file = $current > 0 ? $storage->load($current) : NULL;
    return $file !== NULL && $this->isCroppable($file) ? $file : NULL;
  }

  /** Imagem que não é do acervo do sistema. */
  private function isCroppable(FileInterface $file): bool {
    return !str_starts_with($file->getFileUri(), AvatarPool::DIRECTORY . '/');
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $file = $this->targetFile();
    if ($file === NULL) {
      $form['vazio'] = ['#markup' => $this->t('Envie uma foto própria para recortar.')];
      return $form;
    }
    // Envio sem AJAX para a própria rota, com o arquivo a recortar: o modal não pode usar a URL com formato de modal.
    $form['#action'] = Url::fromRoute('aculta_user.avatar_crop', [], ['query' => ['fid' => (int) $file->id()]])->toString();
    // Sem abas nem seções: o recorte mostra direto a caixa de recorte (ver flattenCropWrappers).
    $form['#after_build'][] = [self::class, 'flattenCropWrappers'];
    $form['image_crop'] = [
      '#type' => 'image_crop',
      '#file' => $file,
      '#crop_type_list' => [self::CROP_TYPE],
      // Base do canvas de recorte: estilo só de redimensionamento. O estilo aculta_avatar já aplica o recorte,
      // e recortar sobre uma imagem já recortada não funciona.
      '#crop_preview_image_style' => 'crop_thumbnail',
      '#show_default_crop' => TRUE,
      '#show_crop' => TRUE,
      '#warn_multiple_usages' => FALSE,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['aplicar'] = [
      '#type' => 'submit',
      '#value' => $this->t('Aplicar recorte'),
    ];
    $form['actions']['cancelar'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancelar'),
      '#url' => Url::fromRoute('aculta_portal.dashboard', [], ['query' => [self::RETURN_QUERY => '1']]),
    ];
    return $form;
  }

  /**
   * Remove as abas e seções que o image_crop cria em volta do tipo de recorte ("Crop image" e o nome do tipo).
   *
   * Troca só o tipo de elemento para container: nomes, #parents e valores enviados ficam iguais, então o submit lê o
   * mesmo caminho (image_crop > crop_wrapper > CROP_TYPE > crop_container > values).
   */
  public static function flattenCropWrappers(array $form, FormStateInterface $form_state): array {
    if (!isset($form['image_crop']['crop_wrapper']) || !is_array($form['image_crop']['crop_wrapper'])) {
      return $form;
    }
    self::flattenElement($form['image_crop']['crop_wrapper']);
    return $form;
  }

  /** Troca a moldura (tipo e theme_wrappers) de details/vertical_tabs por container, em todos os níveis. */
  private static function flattenElement(array &$element): void {
    foreach (Element::children($element) as $key) {
      if (!is_array($element[$key])) {
        continue;
      }
      if (in_array($element[$key]['#type'] ?? NULL, ['details', 'vertical_tabs'], TRUE)) {
        $element[$key]['#type'] = 'container';
        $element[$key]['#theme_wrappers'] = ['container'];
        unset($element[$key]['#group'], $element[$key]['#open']);
      }
      self::flattenElement($element[$key]);
    }
    if (($element['#type'] ?? NULL) === 'details') {
      $element['#type'] = 'container';
      $element['#theme_wrappers'] = ['container'];
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $file = $this->targetFile();
    $wrapper = $form_state->getValue('image_crop')['crop_wrapper'] ?? [];
    $cropType = $this->entityTypeManagerService->getStorage('crop_type')->load(self::CROP_TYPE);
    if ($file === NULL || $cropType === NULL || !isset($wrapper[self::CROP_TYPE]['crop_container']['values'])) {
      $this->messenger()->addError($this->t('Não foi possível aplicar o recorte.'));
      return;
    }
    $props = $wrapper[self::CROP_TYPE]['crop_container']['values'];
    $ref = ['file-id' => $file->id(), 'file-uri' => $file->getFileUri()];
    if (($props['crop_applied'] ?? '0') === '1' && !empty($props['width']) && !empty($props['height'])) {
      if (Crop::cropExists($file->getFileUri(), self::CROP_TYPE)) {
        $this->cropManager->updateCrop($props, $ref, $cropType);
      }
      else {
        $this->cropManager->applyCrop($props, $ref, $cropType);
      }
    }
    elseif (Crop::cropExists($file->getFileUri(), self::CROP_TYPE)) {
      $this->cropManager->deleteCrop($file->getFileUri(), $cropType, $file->id());
    }

    $uid = (int) $this->currentUserService->id();
    $previous = (int) $this->userData->get('aculta_user', $uid, self::PENDING_KEY);
    if ($previous > 0 && $previous !== (int) $file->id()) {
      $old = $this->entityTypeManagerService->getStorage('file')->load($previous);
      if ($old !== NULL && (int) $old->isPermanent() === 0) {
        $old->delete();
      }
    }
    $this->userData->set('aculta_user', $uid, self::PENDING_KEY, (int) $file->id());
    $this->flushDerivatives($file);

    $form_state->setRedirect('aculta_portal.dashboard', [], ['query' => [self::RETURN_QUERY => '1']]);
  }

  /** Apaga os derivados do estilo de avatar deste arquivo, para não servir recorte antigo. */
  private function flushDerivatives(FileInterface $file): void {
    $style = $this->entityTypeManagerService->getStorage('image_style')->load(self::STYLE);
    if ($style !== NULL) {
      $style->flush($file->getFileUri());
    }
  }

}
