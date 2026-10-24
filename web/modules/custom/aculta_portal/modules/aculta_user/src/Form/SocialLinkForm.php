<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\aculta_user\Social\SocialNetworks;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\aculta_portal\Profile\PerfilDoPapel;

/**
 * Modal de edição do link de uma rede social (Minha conta). Salva pela rota JSON aculta_user.redes_save.
 */
final class SocialLinkForm extends FormBase {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManagerService,
    private readonly AccountProxyInterface $currentUserService,
    private readonly SocialNetworks $networks,
    private readonly CsrfTokenGenerator $csrfToken,
    private readonly ?RouteMatchInterface $routeMatch = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aculta_user.social_networks'),
      $container->get('csrf_token'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'aculta_user_social_link_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $rede = ''): array {
    if (!isset(SocialNetworks::NETWORKS[$rede])) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    $definition = SocialNetworks::NETWORKS[$rede];
    $profile = $this->profile();
    $current = $this->networks->linksOf($profile)[$rede] ?? '';

    $studio = $this->isStudioEditor();
    $form['#action'] = Url::fromRoute(
      $studio ? 'aculta_studio.profile_social' : 'aculta_user.redes_form',
      ['rede' => $rede],
    )->toString();
    $form['#attributes']['data-portal-ui'] = $studio ? 'studio-social-form' : 'social-link-dialog';
    // Minha Conta keeps its modal/JSON workflow. The Studio full-page form
    // uses native Form API for validation, CSRF, and a no-JavaScript fallback.
    if (!$studio) {
      $form['#aculta_json_save'] = TRUE;
      $form['#attributes']['data-aculta-json-form'] = 'social';
      $form['#attached']['library'][] = 'aculta_user/account-bio-form';
      $form['#attached']['drupalSettings']['acultaUser']['jsonForms']['social'] = [
        'url' => Url::fromRoute('aculta_user.redes_save', [], ['query' => ['token' => $this->csrfToken->get('redes/salvar')]])->toString(),
      ];
    }
    $form['rede'] = ['#type' => 'hidden', '#value' => $rede];
    $form['link'] = [
      '#type' => 'url',
      '#title' => $definition['label'],
      '#description' => $this->t('Deixe vazio para remover. Use o endereço completo da sua página.'),
      '#default_value' => $current,
      '#maxlength' => 255,
    ];
    $form['actions'] = ['#type' => 'container', '#attributes' => ['data-portal-ui' => 'account-bio-actions']];
    if (!$studio) {
      $form['actions']['clear'] = [
        '#type' => 'button',
        '#value' => $this->t('Limpar'),
        '#attributes' => ['data-aculta-clear' => '', 'data-portal-ui' => 'button-secondary'],
        '#weight' => -1,
      ];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rede = (string) $form_state->getValue('rede');
    try {
      $uri = $this->networks->normalize($rede, (string) $form_state->getValue('link'));
    }
    catch (\InvalidArgumentException $e) {
      $form_state->setErrorByName('link', $e->getMessage());
      return;
    }
    $profile = $this->profile();
    $this->networks->setLink($profile, $rede, $uri);
    $profile->save();
    $form_state->setRedirect($this->isStudioEditor()
      ? 'aculta_studio.profile'
      : 'aculta_portal.dashboard');
  }

  /**
   * Returns whether this same owner form serves a full-page Studio screen.
   */
  private function isStudioEditor(): bool {
    return $this->routeMatch?->getRouteName() === 'aculta_studio.profile_social';
  }

  /**
   * Perfil participante da conta atual, criado se ainda não existe.
   */
  private function profile(): \Drupal\profile\Entity\ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    return PerfilDoPapel::obter($storage, $account);
  }

}
