<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_image\ImageRenderer;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\crop\Entity\CropType;
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
 * Fluxo: ao enviar a foto, o modal de recorte abre sozinho. Aplicar o recorte grava o recorte pela API do
 * image_widget_crop, torna o arquivo permanente, troca a foto da conta e recarrega o painel (POST comum, sem AJAX,
 * sem reabrir o popup). Por isso não há etapa de "Salvar" depois do recorte.
 *
 * O arquivo a recortar é só o informado em ?fid= (upload temporário do próprio usuário). Não há recorte da foto já
 * salva nem do acervo: para recortar de novo, a pessoa envia a imagem outra vez.
 */
final class AvatarCropForm extends FormBase {

  /**
   * Tipo de recorte do avatar (config/sync/crop.type.aculta_avatar_1x1.yml).
   */
  public const CROP_TYPE = 'aculta_avatar_1x1';

  /**
   * Opções do modal de recorte. Tamanho e altura são do tema (dialog.css, data-portal-ui="avatar-crop-dialog").
   */
  public const DIALOG_OPTIONS = ['title' => 'Recortar foto'];

  /**
   * Estilo de imagem usado pela visualização do avatar.
   */
  public const STYLE = 'aculta_avatar';

  public function __construct(
    protected ImageWidgetCropInterface $cropManager,
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected AccountInterface $currentUserService,
    protected UserDataInterface $userData,
    protected RequestStack $cropRequestStack,
    protected readonly ImageRenderer $imageRenderer,
  ) {}

  /**
   * Cria a instância do formulário com os serviços necessários.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container de serviços.
   *
   * @return static
   *   Instância do formulário.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('image_widget_crop.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('request_stack'),
      $container->get('aculta_image.render'),
    );
  }

  /**
   * Devolve o ID do formulário.
   *
   * @return string
   *   ID do formulário.
   */
  public function getFormId(): string {
    return 'aculta_user_avatar_crop_form';
  }

  /**
   * Arquivo a recortar, ou NULL. Só o arquivo recém-enviado: temporário, do próprio usuário e recortável.
   *
   * Não há fallback para a foto atual nem para o recorte pendente: para recortar de novo, a pessoa envia a imagem
   * outra vez (regra de estado, docs/FOTO-PERFIL.md).
   */
  public function targetFile(): ?FileInterface {
    $uid = (int) $this->currentUserService->id();
    $fid = (int) $this->cropRequestStack->getCurrentRequest()?->query->get('fid', 0);
    if ($fid <= 0) {
      return NULL;
    }
    $file = $this->entityTypeManagerService->getStorage('file')->load($fid);
    if ($file === NULL || (int) $file->getOwnerId() !== $uid || $file->isPermanent() || !$this->isCroppable($file)) {
      return NULL;
    }
    return $file;
  }

  /**
   * Imagem que não é do acervo do sistema.
   */
  private function isCroppable(FileInterface $file): bool {
    $uri = $file->getFileUri();
    return is_string($uri) && $uri !== '' && !str_starts_with($uri, AvatarPool::DIRECTORY . '/');
  }

  /**
   * Monta o formulário de recorte da foto.
   *
   * @param array<string, mixed> $form
   *   Estrutura do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   *
   * @return array<string, mixed>
   *   Estrutura do formulário.   *   *   *
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $attributes = is_array($form['#attributes'] ?? NULL) ? $form['#attributes'] : [];
    $attributes['data-portal-ui'] = 'avatar-crop-dialog';
    $form['#attributes'] = $attributes;
    $file = $this->targetFile();
    if ($file === NULL) {
      $form['vazio'] = ['#markup' => $this->t('Envie uma foto própria para recortar.')];
      return $form;
    }
    // Envio sem AJAX para a própria rota; o modal não pode usar a URL de formato modal.
    $form['#action'] = Url::fromRoute('aculta_user.avatar_crop', [], ['query' => ['fid' => (int) $file->id()]])->toString();
    // Sem abas nem seções: mostra diretamente a caixa de recorte.
    $afterBuild = is_array($form['#after_build'] ?? NULL) ? $form['#after_build'] : [];
    $afterBuild[] = [self::class, 'flattenCropWrappers'];
    $form['#after_build'] = $afterBuild;
    $form['image_crop'] = [
      '#type' => 'image_crop',
      '#file' => $file,
      '#crop_type_list' => [self::CROP_TYPE],
      // Base do canvas de recorte: estilo só de redimensionamento. O estilo aculta_avatar já aplica o recorte,
      // e recortar sobre uma imagem já recortada não funciona.
      '#crop_preview_image_style' => $this->imageRenderer->styleId('recorte_quadrado'),
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
      '#type' => 'portal_button',
      '#props' => [
        'text' => (string) $this->t('Cancelar'),
        'url' => Url::fromRoute('aculta_portal.dashboard')->toString(),
        'variant' => 'secondary',
      ],
    ];
    return $form;
  }

  /**
   * Remove as abas e seções que o image_crop cria em volta do tipo de recorte ("Crop image" e o nome do tipo).
   *
   * Troca só o tipo de elemento para container: nomes, #parents e valores enviados ficam iguais, então o submit lê o
   * mesmo caminho (image_crop > crop_wrapper > CROP_TYPE > crop_container > values).
   *
   * @param array<string, mixed> $form
   *   Estrutura do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   *
   * @return array<string, mixed>
   *   Estrutura do formulário com as molduras trocadas.
   */
  public static function flattenCropWrappers(array $form, FormStateInterface $form_state): array {
    $image = $form['image_crop'] ?? NULL;
    if (!is_array($image)) {
      return $form;
    }
    $wrapper = $image['crop_wrapper'] ?? NULL;
    if (!is_array($wrapper)) {
      return $form;
    }
    self::flattenElement($wrapper);
    $image['crop_wrapper'] = $wrapper;
    $form['image_crop'] = $image;
    return $form;
  }

  /**
   * Troca a moldura (tipo e theme_wrappers) de details/vertical_tabs por container, em todos os níveis.
   *
   * Trabalha numa cópia de cada filho e grava de volta, o que produz a mesma estrutura do ajuste por referência.
   *
   * @param array<array-key, mixed> $element
   *   Elemento a ajustar, alterado no lugar.
   */
  private static function flattenElement(array &$element): void {
    foreach (Element::children($element) as $key) {
      if (!is_int($key) && !is_string($key)) {
        continue;
      }
      $child = $element[$key] ?? NULL;
      if (!is_array($child)) {
        continue;
      }
      if (in_array($child['#type'] ?? NULL, ['details', 'vertical_tabs'], TRUE)) {
        // O pre_render de details/vertical_tabs reagrupa os filhos (#group): sem ele, cada campo sai duplicado.
        $child['#type'] = 'container';
        $child['#theme_wrappers'] = ['container'];
        $child['#pre_render'] = [];
        unset($child['#group'], $child['#open']);
      }
      self::flattenElement($child);
      $element[$key] = $child;
    }
    if (($element['#type'] ?? NULL) === 'details') {
      $element['#type'] = 'container';
      $element['#theme_wrappers'] = ['container'];
      $element['#pre_render'] = [];
    }
  }

  /**
   * Valores enviados do recorte (crop_wrapper > tipo > crop_container > values), ou NULL quando ausentes.
   *
   * @return array<array-key, mixed>|null
   *   Propriedades do recorte enviadas pelo formulário (valores não tipados, vindos do POST).
   */
  private function cropValues(FormStateInterface $form_state): ?array {
    $imageCrop = $form_state->getValue('image_crop');
    $wrapper = is_array($imageCrop) ? ($imageCrop['crop_wrapper'] ?? NULL) : NULL;
    $type = is_array($wrapper) ? ($wrapper[self::CROP_TYPE] ?? NULL) : NULL;
    $container = is_array($type) ? ($type['crop_container'] ?? NULL) : NULL;
    $values = is_array($container) ? ($container['values'] ?? NULL) : NULL;
    return is_array($values) ? $values : NULL;
  }

  /**
   * Aplica o recorte, torna o arquivo permanente e troca a foto da conta.
   *
   * @param array<array-key, mixed> $form
   *   Estrutura do formulário.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Estado atual do formulário.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $file = $this->targetFile();
    $props = $this->cropValues($form_state);
    $cropType = $this->entityTypeManagerService->getStorage('crop_type')->load(self::CROP_TYPE);
    $uri = $file?->getFileUri();
    $fid = $file?->id();
    if ($file === NULL || !is_string($uri) || !is_int($fid) || !$cropType instanceof CropType || $props === NULL) {
      $this->messenger()->addError($this->t('Não foi possível aplicar o recorte.'));
      return;
    }
    $ref = ['file-id' => $fid, 'file-uri' => $uri];
    if (($props['crop_applied'] ?? '0') === '1' && !empty($props['width']) && !empty($props['height'])) {
      if (Crop::cropExists($uri, self::CROP_TYPE)) {
        $this->cropManager->updateCrop($props, $ref, $cropType);
      }
      else {
        $this->cropManager->applyCrop($props, $ref, $cropType);
      }
    }
    elseif (Crop::cropExists($uri, self::CROP_TYPE)) {
      $this->cropManager->deleteCrop($uri, $cropType, $fid);
    }

    $this->flushDerivatives($file);

    // Aplicar o recorte já troca a foto da conta: o arquivo recortado vira a foto e a página recarrega (sem reabrir o
    // popup).
    $file->setPermanent();
    $file->save();
    $account = $this->entityTypeManagerService->getStorage('user')->load($this->currentUserService->id());
    if ($account instanceof UserInterface) {
      $account->set('user_picture', ['target_id' => $fid, 'alt' => (string) $account->label()]);
      $account->save();
    }
    $this->messenger()->addStatus($this->t('Foto do perfil atualizada.'));
    $form_state->setRedirect('aculta_portal.dashboard');
  }

  /**
   * Apaga os derivados do estilo de avatar deste arquivo, para não servir recorte antigo.
   */
  private function flushDerivatives(FileInterface $file): void {
    $style = $this->imageRenderer->styleEntity('avatar');
    if ($style !== NULL) {
      $style->flush($file->getFileUri());
    }
  }

}
