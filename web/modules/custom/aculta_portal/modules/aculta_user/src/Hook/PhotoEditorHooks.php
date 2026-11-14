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
   * @param array $photoForm
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

    $fid = (int) ($account->get('user_picture')->target_id ?? 0);
    $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    $form['#attached']['library'][] = 'aculta_user/photo-flow';
    $form['#attached']['drupalSettings']['acultaUser']['savedFid'] = $fid;

    if (PhotoEditorGuard::isAcervo($account, $file)) {
      $this->guard->adjustForm($form, $form_state, $fid);
      return;
    }

    // Foto personalizada: sem recorte inline; o recorte fica no popup próprio.
    $form['user_picture']['widget'][0]['#after_build'][] = [PhotoEditorGuard::class, 'hideInlineCrop'];
  }

}
