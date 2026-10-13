<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Modal de edição da bio (campo field_bio do perfil participante).
 *
 * Salvar atualiza a caixa da Minha conta por AJAX e fecha o modal. Limpar só esvazia a caixa do modal.
 * A bio só aparece no perfil público quando a pessoa escolhe exibi-la (PublicProfilePolicy).
 */
final class AccountBioForm extends FormBase {

  /**
   * Limite de caracteres da bio.
   */
  public const MAX_LENGTH = 500;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountProxyInterface $currentUserService,
    private readonly AccountBioCard $bioCard,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_user.account_bio_card'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_account_bio_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $profile = $this->profile();
    $form['#attached']['library'][] = 'aculta_user/account-bio-form';
    $form['bio'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bio'),
      '#description' => $this->t('Até @max caracteres. Aparece no seu perfil público quando você escolher exibi-la.', ['@max' => self::MAX_LENGTH]),
      '#default_value' => $profile->hasField('field_bio') ? (string) $profile->get('field_bio')->value : '',
      '#rows' => 4,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar bio'),
      '#ajax' => ['callback' => '::ajaxSave'],
    ];
    // Botão de tipo "button": não envia o formulário. O JS da biblioteca esvazia o campo.
    $form['actions']['clear'] = [
      '#type' => 'button',
      '#value' => $this->t('Limpar'),
      '#attributes' => ['data-aculta-bio-clear' => '', 'class' => ['btn', 'btn-secondary']],
      // Antes de Salvar bio.
      '#weight' => -1,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (mb_strlen(trim((string) $form_state->getValue('bio'))) > self::MAX_LENGTH) {
      $form_state->setErrorByName('bio', $this->t('A bio aceita até @max caracteres.', ['@max' => self::MAX_LENGTH]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $profile = $this->profile();
    // Texto vazio grava vazio: a caixa volta a mostrar o aviso padrão.
    $profile->set('field_bio', trim((string) $form_state->getValue('bio')));
    $profile->save();
  }

  /**
   * Resposta AJAX: substitui a caixa Bio da Minha conta pela versão salva e fecha o modal.
   */
  public function ajaxSave(array &$form, FormStateInterface $form_state): AjaxResponse {
    $card = $this->renderer->renderInIsolation($this->bioCard->build($this->currentUserService->getAccount()));
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#aculta-account-bio-card', $card));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Perfil participante da conta atual, criado se ainda não existe.
   */
  private function profile(): ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    return $storage->loadByUser($account, 'participante')
      ?? $storage->create(['type' => 'participante', 'uid' => (int) $account->id()]);
  }

}
