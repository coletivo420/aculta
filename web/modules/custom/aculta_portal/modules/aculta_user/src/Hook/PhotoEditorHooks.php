<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\aculta_user\Form\PhotoEditorGuard;
use Drupal\aculta_user\Form\UserPictureForm;
use Drupal\user\UserInterface;

/**
 * Editor de foto do painel (ponto de extensão aculta_portal_photo_editor e formulário do painel).
 *
 * - Sorteio de avatar por link AJAX com token CSRF.
 * - Foto do acervo: sem remover e sem recorte (PhotoEditorGuard).
 * - Foto personalizada: o recorte inline sai e fica no popup de recorte, que abre sozinho ao enviar a foto
 *   (biblioteca aculta_user/photo-flow). O botão "Recortar" reabre o mesmo popup.
 * - Recorte pendente: o popup da foto mostra o arquivo recortado até a pessoa clicar em "Salvar".
 */
final class PhotoEditorHooks {

  use \Drupal\Core\StringTranslation\StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'aculta_user.photo_editor_guard')]
    private readonly PhotoEditorGuard $guard,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Entrega a prévia e o formulário próprio para a composição do tema.
   *
   * @param array<array-key, mixed> $photoForm
   *   Estrutura do editor de foto da conta.
   * @param \Drupal\user\UserInterface $account
   *   Conta cuja foto está sendo editada.
   */
  #[Hook('aculta_portal_photo_editor_alter')]
  public function photoEditorAlter(array &$photoForm, UserInterface $account): void {
    // A rota segue disponível como fallback. Na conta, o formulário é renderizado dentro do popup SDC e
    // mantém seu #action apontado para a rota própria para os envios e reconstruções do formulário.
    $photoForm = [
      'preview' => $photoForm['preview'] ?? [],
      'form' => $this->formBuilder->getForm(UserPictureForm::class),
    ];
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for user forms: formulário de foto do painel.
   *
   * @param array<array-key, mixed> $form
   *   Estrutura do formulário, alterada no lugar.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($this->routeMatch->getRouteName() !== 'aculta_portal.dashboard' || !isset($form['user_picture'])) {
      return;
    }
    $formObject = $form_state->getFormObject();
    $account = $formObject instanceof EntityFormInterface ? $formObject->getEntity() : NULL;
    if (!$account instanceof UserInterface || (int) $account->id() !== (int) $this->currentUser->id()) {
      return;
    }
    // O painel envia só a foto. O buildEntity do Core espera o campo "roles": mantém os papéis
    // atuais (sem apagar nenhum).
    $form['roles'] = ['#type' => 'value', '#value' => array_values($account->getRoles(TRUE))];

    $fid = $this->intValue($account->get('user_picture')->target_id ?? 0);
    $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    // Anexos são lidos e gravados de volta por nível, sem escrita aninhada em valores não tipados.
    $attached = is_array($form['#attached'] ?? NULL) ? $form['#attached'] : [];
    $libraries = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
    $libraries[] = 'aculta_user/photo-flow';
    $attached['library'] = $libraries;
    $settings = is_array($attached['drupalSettings'] ?? NULL) ? $attached['drupalSettings'] : [];
    $acultaUser = is_array($settings['acultaUser'] ?? NULL) ? $settings['acultaUser'] : [];
    $acultaUser['savedFid'] = $fid;
    $settings['acultaUser'] = $acultaUser;
    $attached['drupalSettings'] = $settings;
    $form['#attached'] = $attached;

    if (PhotoEditorGuard::isAcervo($account, $file)) {
      $this->guard->adjustForm($form, $form_state, $fid);
      return;
    }

    // Foto personalizada: sem recorte inline; o recorte fica no popup próprio.
    $userPicture = is_array($form['user_picture']) ? $form['user_picture'] : [];
    $widgets = is_array($userPicture['widget'] ?? NULL) ? $userPicture['widget'] : [];
    $widget = is_array($widgets[0] ?? NULL) ? $widgets[0] : [];
    $afterBuild = is_array($widget['#after_build'] ?? NULL) ? $widget['#after_build'] : [];
    $afterBuild[] = [PhotoEditorGuard::class, 'hideInlineCrop'];
    $widget['#after_build'] = $afterBuild;
    $widgets[0] = $widget;
    $userPicture['widget'] = $widgets;
    $form['user_picture'] = $userPicture;
  }

  /**
   * Inteiro de um valor de campo; zero quando não é numérico.
   */
  private function intValue(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

}
