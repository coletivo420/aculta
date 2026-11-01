<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_image\UploadPolicy;
use Drupal\aculta_image\ImageRenderer;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\aculta_user\Avatar\AvatarPool;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulário de foto do popup do painel, em rota própria.
 *
 * Fica numa rota `_form` (e não na página do painel) para que o upload por AJAX funcione: na página do painel, a
 * resposta do upload reconstruía a página inteira e o arquivo enviado se perdia.
 *
 * - Foto do acervo: sem "remover" e sem recorte; o campo serve para enviar uma foto própria.
 * - Foto personalizada: pode ser removida (volta ao sorteio do acervo) e recortada pelo popup de recorte.
 * - Recorte pendente (aplicado e aguardando "Salvar"): é o arquivo mostrado e salvo.
 */
final class UserPictureForm extends FormBase {


  /** Pasta dos envios. Prefixo pictures/: a limpeza do cron trata esses temporários. */
  public const UPLOAD_LOCATION = 'public://pictures/uploads';

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManagerService,
    private AccountInterface $currentUserService,
    private UserDataInterface $userData,
    private CsrfTokenGenerator $csrfToken,
    private AvatarPool $avatarPool,
    private readonly ImageRenderer $imageRenderer,
    private readonly UploadPolicy $uploadPolicy,
    private readonly RendererInterface $renderer,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('csrf_token'),
      $container->get('aculta_user.avatar_pool'),
      $container->get('aculta_image.render'),
      $container->get('aculta_image.upload_policy'),
      $container->get('renderer'),
    );
  }

  public function getFormId(): string {
    return 'aculta_user_picture_form';
  }

  /** Conta atual, ou NULL. */
  private function account(): ?UserInterface {
    $account = $this->entityTypeManagerService->getStorage('user')->load($this->currentUserService->id());
    return $account instanceof UserInterface ? $account : NULL;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $account = $this->account();
    if ($account === NULL) {
      return $form;
    }
    // Abrir o modal (sem entrada do usuário) descarta um sorteio não salvo: só "Salvar" grava a escolha.
    if (empty($form_state->getUserInput())) {
      $this->avatarPool->clearPending($account);
    }
    $current = (int) ($account->get('user_picture')->target_id ?? 0);
    $isAcervo = $current > 0 && str_starts_with((string) $this->entityTypeManagerService->getStorage('file')->load($current)?->getFileUri(), AvatarPool::DIRECTORY . '/');

    // O campo de envio começa vazio nos dois estados: nome e link da foto atual não aparecem no popup (a prévia mostra a foto).

    $form['#attributes']['data-aculta-user-picture'] = '';
    $form['#attributes']['data-portal-ui'] = 'photo-dialog';

    // Mesmo conteúdo do popup anterior: prévia do avatar atual e a orientação de uso.
    $currentFile = $current > 0 ? $this->entityTypeManagerService->getStorage('file')->load($current) : NULL;
    if ($currentFile !== NULL) {
      $form['preview'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['data-portal-ui' => 'account-photo-preview'],
        'avatar' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => ['data-aculta-user-avatar' => ''],
          'image' => $this->imageRenderer->build('avatar', $currentFile->getFileUri(), (string) $this->t('Foto de perfil de @name', ['@name' => $account->getDisplayName()]), ['data-portal-ui' => 'account-avatar']),
        ],
      ];
    }
    $form['intro'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Escolha uma imagem e ajuste o enquadramento antes de salvar.'),
    ];
    $form['imagem'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Foto'),
      '#description' => $this->t('Envie uma imagem PNG, JPG, GIF ou WebP de até 10 MB.'),
      '#upload_location' => self::UPLOAD_LOCATION,
      '#upload_validators' => $this->uploadPolicy->validators(),
      '#default_value' => NULL,
      '#required' => FALSE,
      // Regra de estado (docs/FOTO-PERFIL.md): avatar do sistema nunca tem botão "Remover".
      '#aculta_acervo' => $isAcervo,
      '#after_build' => [[self::class, 'withoutRemoveForAcervo']],
    ];
    $form['#account_id'] = (int) $account->id();

    // Ações: "Sortear outro avatar" (só avatar do sistema) e "Remover foto" (só foto personalizada) ficam no mesmo lugar,
    // ao lado de "Salvar foto do perfil" (regra de estado, docs/FOTO-PERFIL.md).
    // Linha de ações da foto (sortear, remover e salvar): token próprio do tema (account-photo-actions).
    $form['actions'] = ['#type' => 'actions', '#attributes' => ['data-portal-ui' => 'account-photo-actions']];
    if ($isAcervo) {
      // Botão de envio (mesma linha de "Salvar"): o sorteio troca só a prévia do modal, por AJAX, e não grava.
      $form['actions']['sortear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Sortear novo avatar'),
        '#limit_validation_errors' => [],
        '#submit' => ['::noSubmit'],
        '#ajax' => ['callback' => '::sortearPrevia', 'event' => 'click', 'progress' => ['type' => 'none']],
      ];
    }
    if (!$isAcervo && $current > 0) {
      $form['actions']['remover'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remover foto'),
        '#limit_validation_errors' => [],
        '#submit' => ['::removePersonal'],
      ];
    }
    $form['actions']['salvar'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar foto do perfil'),
    ];
    $form['#action'] = Url::fromRoute('aculta_user.foto')->toString();
    return $form;
  }

  /** Sortear não grava nada: a escolha só vale quando o formulário é salvo (AvatarPool::applyPending). */
  public function noSubmit(array &$form, FormStateInterface $form_state): void {}

  /** Callback AJAX do sorteio: troca só a prévia dentro do modal (a visão geral muda ao salvar). */
  public function sortearPrevia(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $account = $this->account();
    $entry = $account !== NULL ? $this->avatarPool->preview($account) : NULL;
    if ($entry !== NULL) {
      $build = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['data-aculta-user-avatar' => ''],
        'image' => [
          '#theme' => 'image',
          '#uri' => $this->avatarPool->previewUrl($entry),
          '#alt' => (string) $account->label(),
          '#attributes' => ['data-portal-ui' => 'account-avatar'],
        ],
      ];
      $response->addCommand(new ReplaceCommand('.ui-dialog [data-aculta-user-avatar]', (string) $this->renderer->renderInIsolation($build)));
    }
    return $response;
  }

  /** Remove o botão "Remover" do campo quando a foto é do acervo do sistema (regra de estado). */
  public static function withoutRemoveForAcervo(array $element, FormStateInterface $form_state): array {
    if (!empty($element['#aculta_acervo'])) {
      unset($element['remove_button']);
    }
    return $element;
  }

  /**
   * Botão "Remover foto" (só foto personalizada): volta a um avatar sorteado do acervo e recarrega o painel.
   * A foto personalizada substituída é apagada pelo hook de troca de foto (PictureReplacementHooks).
   */
  public function removePersonal(array &$form, FormStateInterface $form_state): void {
    $account = $this->account();
    if ($account === NULL) {
      return;
    }
    if ($this->avatarPool->reroll($account) === NULL) {
      $this->messenger()->addError($this->t('Não foi possível sortear um avatar do sistema agora.'));
    }
    else {
      $this->messenger()->addStatus($this->t('Foto personalizada removida. Seu avatar do sistema foi sorteado.'));
    }
    $form_state->setRedirect('aculta_portal.dashboard');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $account = $this->account();
    if ($account === NULL) {
      return;
    }
    $uid = (int) $account->id();
    $current = (int) ($account->get('user_picture')->target_id ?? 0);
    $fids = array_values(array_filter((array) $form_state->getValue('imagem')));
    $new = (int) ($fids[0] ?? 0);

    $file = $new > 0 ? $this->entityTypeManagerService->getStorage('file')->load($new) : NULL;
    $isAcervo = $current > 0 && str_starts_with((string) $this->entityTypeManagerService->getStorage('file')->load($current)?->getFileUri(), AvatarPool::DIRECTORY . '/');

    // Envio tem prioridade; sem envio, aplica o avatar sorteado no modal (só agora ele vira a foto da conta).
    // Campo vazio e sem sorteio não altera a foto (a remoção é o botão "Remover foto").
    if ($new > 0 && $file !== NULL && $new !== $current) {
      $account->set('user_picture', ['target_id' => $file->id(), 'alt' => (string) $account->label()]);
      $file->setPermanent();
      $file->save();
      $this->avatarPool->clearPending($account);
    }
    elseif ($this->avatarPool->pendingEntry($account) !== NULL) {
      $this->avatarPool->applyPending($account);
    }
    $account->save();
    $this->messenger()->addStatus($this->t('Foto do perfil salva.'));
    $form_state->setRedirect('aculta_portal.dashboard');
  }

}
