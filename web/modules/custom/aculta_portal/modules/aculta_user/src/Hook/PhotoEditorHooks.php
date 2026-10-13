<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\CsrfTokenGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_user\Form\AvatarCropForm;
use Drupal\aculta_user\Form\PhotoEditorGuard;
use Drupal\aculta_user\Form\UserPictureForm;
use Drupal\user\UserDataInterface;
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

  /** Caminho da rota de sorteio (o token CSRF é gerado para este caminho). */
  private const PATH = '/avatar/sortear';

  public function __construct(
    #[Autowire(service: 'csrf_token')]
    private readonly CsrfTokenGenerator $csrfToken,
    #[Autowire(service: 'aculta_user.photo_editor_guard')]
    private readonly PhotoEditorGuard $guard,
    #[Autowire(service: 'current_route_match')]
    private readonly RouteMatchInterface $routeMatch,
    #[Autowire(service: 'current_user')]
    private readonly AccountInterface $currentUser,
    #[Autowire(service: 'entity_type.manager')]
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'user.data')]
    private readonly UserDataInterface $userData,
    #[Autowire(service: 'form_builder')]
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  #[Hook('aculta_portal_photo_editor_alter')]
  public function photoEditorAlter(array &$photoForm, UserInterface $account): void {
    // O gatilho é o avatar: abre o modal Drupal com o formulário da foto (rota própria, upload por AJAX). O conteúdo
    // do avatar vem do preparo do Portal ($photoForm['avatar']); o formulário, o sorteio e o recorte ficam no modal.
    $photoForm = [
      '#type' => 'link',
      '#title' => $photoForm['preview']['avatar'] ?? ['#markup' => ''],
      '#url' => Url::fromRoute('aculta_user.foto'),
      '#attributes' => [
        'class' => ['use-ajax', 'portal-account-photo__trigger'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(['width' => 560, 'title' => (string) t('Alterar foto do perfil'), 'dialogClass' => 'aculta-photo-popup', 'modal' => TRUE, 'position' => ['my' => 'center', 'at' => 'center', 'of' => 'window']]),
        'data-aculta-photo-open' => '',
        'aria-label' => (string) t('Alterar foto de perfil de @name', ['@name' => $account->getDisplayName()]),
      ],
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax', 'aculta_user/photo-flow'],
        'drupalSettings' => ['acultaUser' => ['savedFid' => (int) ($account->get('user_picture')->target_id ?? 0)]],
      ],
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
    $account = $form_state->getFormObject()->getEntity();
    if (!$account instanceof UserInterface || (int) $account->id() !== (int) $this->currentUser->id()) {
      return;
    }
    // O painel envia só a foto. O buildEntity do Core espera o campo "roles": mantém os papéis atuais (sem apagar nenhum).
    $form['roles'] = ['#type' => 'value', '#value' => array_values($account->getRoles(TRUE))];

    $fid = (int) ($account->get('user_picture')->target_id ?? 0);
    $file = $fid > 0 ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    $form['#attached']['library'][] = 'aculta_user/photo-flow';
    $form['#attached']['drupalSettings']['acultaUser']['savedFid'] = $fid;

    if (PhotoEditorGuard::isAcervo($account, $file)) {
      $this->guard->adjustForm($form, $form_state, $fid);
      return;
    }

    // Recorte pendente (já recortado, aguardando "Salvar"): o popup da foto mostra esse arquivo.
    $pending = (int) $this->userData->get('aculta_user', (int) $account->id(), AvatarCropForm::PENDING_KEY);
    if ($pending > 0 && $pending !== $fid && isset($form['user_picture']['widget'][0])) {
      $form['user_picture']['widget'][0]['#default_value'] = [$pending];
    }

    // Foto personalizada: sem recorte inline; o recorte fica no popup próprio.
    $form['user_picture']['widget'][0]['#after_build'][] = [PhotoEditorGuard::class, 'hideInlineCrop'];
    $form['recortar'] = [
      '#type' => 'link',
      '#title' => t('Recortar foto'),
      '#url' => Url::fromRoute('aculta_user.avatar_crop'),
      '#attributes' => ['class' => ['use-ajax', 'button', 'btn', 'btn-secondary'], 'data-dialog-type' => 'modal'],
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];
  }

}
