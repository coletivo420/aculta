<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Modal de edição da bio (campo field_bio do perfil participante).
 *
 * Salvar é tratado pelo JS da biblioteca account-bio-form: envia para a rota JSON aculta_user.bio_save, atualiza a
 * caixa da visão geral e fecha o modal. Sem JS, o envio comum grava e redireciona para a visão geral.
 * Limpar só esvazia o campo. A bio só aparece no perfil público quando a pessoa escolhe exibi-la (PublicProfilePolicy).
 */
final class AccountBioForm extends FormBase {

  /**
   * Limite de caracteres da bio.
   */
  public const MAX_LENGTH = 500;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManagerService,
    protected readonly AccountProxyInterface $currentUserService,
    protected readonly CsrfTokenGenerator $csrfToken,
    protected readonly ?RouteMatchInterface $currentRouteMatch = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('csrf_token'),
      $container->get('current_route_match'),
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
    $studio = $this->isStudioEditor();
    $actionUrl = Url::fromRoute($studio ? 'aculta_studio.profile_bio' : 'aculta_user.bio_form');
    // Studio has a full-page Form API fallback. Existing Minha Conta modals
    // retain their owner AJAX/JSON behavior and unchanged submission route.
    $form['#action'] = $actionUrl->toString();
    $form['#attributes']['data-portal-ui'] = $studio ? 'studio-bio-form' : 'account-bio-dialog';
    if ($this->isHtmxRequest()) {
      (new Htmx())
        ->post($actionUrl)
        ->select('#' . Html::getId($this->getFormId()))
        ->swap('outerHTML')
        ->onlyMainContent()
        ->applyTo($form);
    }
    elseif (!$studio) {
      $form['#attributes']['data-aculta-bio-form'] = '';
      $form['#attached']['library'][] = 'aculta_user/account-bio-form';
      $form['#attached']['drupalSettings']['acultaUser']['bioSave'] = [
        'url' => Url::fromRoute('aculta_user.bio_save', [], ['query' => ['token' => $this->csrfToken->get('bio/salvar')]])->toString(),
      ];
    }
    $form['bio'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Bio'),
      '#description' => $this->t('Até @max caracteres. Aparece no seu perfil público quando você escolher exibi-la.', ['@max' => self::MAX_LENGTH]),
      '#default_value' => $profile->hasField('field_bio') ? (string) $profile->get('field_bio')->value : '',
      '#rows' => 4,
    ];
    // Container comum, não "actions": o Drupal move os botões de form-actions para a barra do diálogo (proxies)
    // e o clique nesses proxies não dispara o envio deste formulário.
    $form['actions'] = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'account-bio-actions']];
    // Limpar antes de Salvar bio. É do tipo botão: o JS só esvazia o campo, sem enviar.
    if (!$studio) {
      $form['actions']['clear'] = [
        '#type' => 'aculta_portal_button',
        '#props' => [
          'text' => (string) $this->t('Limpar'),
          'variant' => 'secondary',
          'is_submit' => TRUE,
        ],
        '#attributes' => ['data-aculta-bio-clear' => ''],
        '#weight' => -1,
      ];
    }
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Salvar bio'),
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
    // Texto vazio grava vazio: a visão geral volta a mostrar o aviso padrão.
    $profile->set('field_bio', trim((string) $form_state->getValue('bio')));
    $profile->save();
    $this->messenger()->addStatus($this->t('Bio salva.'));

    $targetRoute = $this->isStudioEditor()
      ? 'aculta_studio.profile'
      : 'aculta_portal.dashboard';

    if ($this->isHtmxRequest()) {
      $redirectUrl = Url::fromRoute($targetRoute)->toString();
      $form_state->setResponse(new Response('', Response::HTTP_OK, ['HX-Redirect' => $redirectUrl]));
      return;
    }

    $form_state->setRedirect($targetRoute);
  }

  /**
   * Returns whether this same owner form is serving the Studio editor.
   */
  private function isStudioEditor(): bool {
    return $this->currentRouteMatch?->getRouteName() === 'aculta_studio.profile_bio';
  }

  /**
   * Perfil participante da conta atual, criado se ainda não existe.
   */
  private function profile(): ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    return PerfilDoPapel::obter($storage, $account);
  }

}
