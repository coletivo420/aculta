<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
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

  /** Tamanho máximo do upload, em bytes (2 MB, como o texto de ajuda do campo). */
  public const MAX_SIZE = 2097152;

  /** Pasta dos envios. Prefixo pictures/: a limpeza do cron trata esses temporários. */
  public const UPLOAD_LOCATION = 'public://pictures/uploads';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountInterface $currentUserService,
    private readonly UserDataInterface $userData,
    private readonly CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('user.data'),
      $container->get('csrf_token'),
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
    $current = (int) ($account->get('user_picture')->target_id ?? 0);
    $pending = (int) $this->userData->get('aculta_user', (int) $account->id(), AvatarCropForm::PENDING_KEY);
    $isAcervo = $current > 0 && str_starts_with((string) $this->entityTypeManagerService->getStorage('file')->load($current)?->getFileUri(), AvatarPool::DIRECTORY . '/');

    // Foto do acervo: sem arquivo inicial (o envio fica disponível) e sem recorte pendente, que é de foto personalizada.
    $default = $isAcervo ? 0 : ($pending > 0 && $pending !== $current ? $pending : $current);

    $form['#attributes']['data-aculta-user-picture'] = '';
    $form['imagem'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Foto'),
      '#description' => $this->t('Envie uma imagem PNG, JPG, GIF ou WebP de até 2 MB.'),
      '#upload_location' => self::UPLOAD_LOCATION,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png jpg jpeg gif webp'],
        'FileSizeLimit' => ['fileLimit' => self::MAX_SIZE],
      ],
      '#default_value' => $default > 0 ? [$default] : NULL,
      '#required' => FALSE,
    ];
    $form['#account_id'] = (int) $account->id();

    // Recortar o arquivo enviado (ou a foto atual, se for personalizada) mesmo sem o disparo automático.
    $cropFid = (int) (($form_state->getValue('imagem')[0] ?? NULL) ?: $default);
    if ($cropFid > 0 && !$isAcervo) {
      $form['recortar'] = [
        '#type' => 'link',
        '#title' => $this->t('Recortar foto'),
        '#url' => Url::fromRoute('aculta_user.avatar_crop', [], ['query' => ['fid' => $cropFid]]),
        '#attributes' => ['class' => ['use-ajax', 'button', 'btn', 'btn-secondary'], 'data-dialog-type' => 'modal'],
        '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      ];
    }

    // Sortear fica sempre disponível, junto com o envio (o sorteio troca a foto e descarta o recorte pendente).
    $form['sortear'] = [
      '#type' => 'link',
      '#title' => $this->t('Sortear outro avatar'),
      '#url' => Url::fromRoute('aculta_user.avatar_reroll', [], ['query' => ['csrf_token' => $this->csrfToken->get('/avatar/sortear')]]),
      '#attributes' => ['class' => ['use-ajax', 'button', 'btn', 'btn-secondary']],
      '#attached' => ['library' => ['core/drupal.ajax']],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['salvar'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar foto do perfil'),
    ];
    $form['#action'] = Url::fromRoute('aculta_user.foto')->toString();
    return $form;
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

    if ($new === 0) {
      // Nada enviado: a foto do acervo continua; foto personalizada removida volta ao sorteio do acervo.
      if (!$isAcervo && $current > 0) {
        $account->set('user_picture', NULL);
      }
    }
    elseif ($file !== NULL && $new !== $current) {
      $account->set('user_picture', ['target_id' => $file->id(), 'alt' => (string) $account->label()]);
      $file->setPermanent();
      $file->save();
    }
    $account->save();
    // O recorte pendente foi consumido por este salvamento.
    $this->userData->delete('aculta_user', $uid, AvatarCropForm::PENDING_KEY);
    $this->messenger()->addStatus($this->t('Foto do perfil salva.'));
    $form_state->setRedirect('aculta_portal.dashboard');
  }

}
