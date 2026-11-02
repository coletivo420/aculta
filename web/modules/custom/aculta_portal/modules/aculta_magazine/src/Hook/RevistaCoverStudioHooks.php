<?php

declare(strict_types=1);

namespace Drupal\aculta_magazine\Hook;

use Drupal\aculta_file_uploader\ConsumerUploadPolicy;
use Drupal\aculta_file_uploader\RecentUploads;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;

/**
 * Lets the Revista form pick a recent image from the Studio as editorial image.
 *
 * The file is uploaded in the Studio (integração revista). This form only
 * chooses it. The choice is checked for ownership and image validity, then
 * turned into an image Media before the entity is built. It is used only when
 * the editorial image field is empty.
 */
final class RevistaCoverStudioHooks {

  private const FORM_IDS = ['node_revista_form', 'node_revista_edit_form'];

  private const FIELD = 'field_media_image';

  private const PICK = 'studio_cover';

  private const CONSUMER = 'revista';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly RecentUploads $recentUploads,
    private readonly ConsumerUploadPolicy $uploadPolicy,
  ) {}

  /**
   * Adds the Studio choice and relaxes the required check on the editorial image.
   */
  #[Hook('form_alter')]
  public function alter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!in_array($form_id, self::FORM_IDS, TRUE) || !isset($form[self::FIELD])) {
      return;
    }
    $isAdmin = $this->currentUser->hasPermission('administer site configuration');
    $options = array_filter(
      $this->recentUploads->forUser((int) $this->currentUser->id(), self::CONSUMER, 20, $isAdmin),
      fn (string $name): bool => in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $this->uploadPolicy->imageExtensions(self::CONSUMER), TRUE),
    );
    if ($options === []) {
      return;
    }

    $form[self::PICK] = [
      '#type' => 'select',
      '#title' => t('Ou escolha uma imagem recente do Studio'),
      '#options' => ['' => t('- Nenhuma -')] + $options,
      '#default_value' => '',
      '#description' => t('Usada somente se a imagem editorial acima estiver vazia.'),
      '#weight' => (float) ($form[self::FIELD]['#weight'] ?? 0) + 0.01,
    ];
    // MediaLibraryWidget::validateRequired lê a seleção do user input bruto,
    // antes dos handlers de #validate. A conversão precisa ocorrer no
    // after_build e gravar também o user input.
    $this->relax($form[self::FIELD]);
    $form['#after_build'][] = [self::class, 'afterBuild'];
    $form['#validate'] = array_merge(
      ['Drupal\aculta_magazine\Hook\RevistaCoverStudioHooks:validateCover'],
      $form['#validate'] ?? [],
    );
  }

  /**
   * Converte a escolha do Studio antes da validação do widget.
   */
  public static function afterBuild(array $form, FormStateInterface $form_state): array {
    if (!isset($form[self::FIELD])) {
      return $form;
    }
    static::relax($form[self::FIELD]);
    $pick = (int) $form_state->getValue(self::PICK);
    if ($pick < 1 || (int) $form_state->getValue([self::FIELD, 0, 'target_id']) > 0) {
      return $form;
    }
    $media_id = \Drupal::service(self::class)->createMediaFromPick($pick, $form_state);
    if ($media_id === NULL) {
      return $form;
    }
    $value = [['target_id' => $media_id]];
    $form_state->setValue(self::FIELD, $value);
    $form_state->setValue([self::FIELD, 'selection'], $value);
    // validateRequired usa getUserInput(), não os valores do form state.
    $input = $form_state->getUserInput();
    NestedArray::setValue($input, [self::FIELD, 'selection'], $value);
    $form_state->setUserInput($input);
    $form_state->setValue(self::PICK, 0);
    return $form;
  }

  /**
   * Cria a Media da escolha do Studio, ou NULL quando ela não é válida.
   */
  public function createMediaFromPick(int $pick, FormStateInterface $form_state): ?int {
    $file = $this->entityTypeManager->getStorage('file')->load($pick);
    $isAdmin = $this->currentUser->hasPermission('administer site configuration');
    if (!$file instanceof FileInterface
      || !$this->recentUploads->isOwnedBy($file, (int) $this->currentUser->id(), self::CONSUMER, $isAdmin)
      || !$this->uploadPolicy->isValidImage($file, self::CONSUMER)) {
      $form_state->setErrorByName(self::PICK, t('A imagem escolhida não está disponível. Escolha novamente.'));
      return NULL;
    }
    if (!$file->isPermanent()) {
      $file->setPermanent();
      $file->save();
    }
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'image',
      'name' => $file->getFilename(),
      'uid' => $this->currentUser->id(),
      'status' => 1,
      'field_media_image' => ['target_id' => $file->id(), 'alt' => (string) t('Imagem editorial')],
    ]);
    $media->save();
    return (int) $media->id();
  }

  /**
   * Turns the Studio choice into the editorial image Media, when it is used.
   */
  public function validateCover(array &$form, FormStateInterface $form_state): void {
    $existing = $this->selectedMediaId($form_state);
    $pick = (int) $form_state->getValue(self::PICK);

    if ($existing > 0 || $pick === 0) {
      if ($existing === 0 && $pick === 0) {
        $form_state->setErrorByName(self::FIELD, t('Escolha ou envie a imagem editorial.'));
      }
      elseif ($existing > 0) {
        // A escolha do widget chega em selection até o massageFormValues.
        $form_state->setValue(self::FIELD, [['target_id' => $existing]]);
      }
      return;
    }
    if ($form_state->hasAnyErrors()) {
      return;
    }

    $file = $this->entityTypeManager->getStorage('file')->load($pick);
    $isAdmin = $this->currentUser->hasPermission('administer site configuration');
    if (!$file instanceof FileInterface
      || !$this->recentUploads->isOwnedBy($file, (int) $this->currentUser->id(), self::CONSUMER, $isAdmin)
      || !$this->uploadPolicy->isValidImage($file, self::CONSUMER)) {
      $form_state->setErrorByName(self::PICK, t('A imagem escolhida não está disponível. Escolha novamente.'));
      return;
    }

    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'image',
      'name' => $file->getFilename(),
      'uid' => $this->currentUser->id(),
      'status' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => (string) t('Imagem editorial'),
      ],
    ]);
    $file->setPermanent();
    $file->save();
    $media->save();

    $value = [['target_id' => (int) $media->id()]];
    $form_state->setValue(self::FIELD, $value);
    $form_state->setValue(self::PICK, 0);
  }

  /**
   * Returns the Media chosen in the widget, or 0 when there is none.
   *
   * The media library widget keeps the chosen item in `selection` until the
   * form values are massaged, so validation must read both places.
   */
  private function selectedMediaId(FormStateInterface $form_state): int {
    $direct = (int) $form_state->getValue([self::FIELD, 0, 'target_id']);
    if ($direct > 0) {
      return $direct;
    }
    foreach ((array) $form_state->getValue([self::FIELD, 'selection']) as $item) {
      $id = is_array($item) ? (int) ($item['target_id'] ?? 0) : 0;
      if ($id > 0) {
        return $id;
      }
    }
    return 0;
  }

  /**
   * Removes the required flag from the widget tree. Our validator covers it.
   */
  private static function relax(array &$element): void {
    if (isset($element['#required'])) {
      $element['#required'] = FALSE;
    }
    foreach (Element::children($element) as $child) {
      static::relax($element[$child]);
    }
  }

}
