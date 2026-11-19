<?php

declare(strict_types=1);

namespace Drupal\aculta_user\Form;

use Drupal\profile\Entity\ProfileInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
    protected readonly EntityTypeManagerInterface $entityTypeManagerService,
    protected readonly AccountProxyInterface $currentUserService,
    protected readonly SocialNetworks $networks,
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
   *
   * @param array<array-key, mixed> $form
   *   Estrutura do formulário.
   *
   * @return array<array-key, mixed>
   *   Estrutura do formulário.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $rede = ''): array {
    if (!isset(SocialNetworks::NETWORKS[$rede])) {
      throw new NotFoundHttpException();
    }
    $definition = SocialNetworks::NETWORKS[$rede];
    $profile = $this->profile();
    $current = $this->networks->linksOf($profile)[$rede] ?? '';

    $studio = $this->isStudioEditor();
    $form['#action'] = Url::fromRoute(
      $studio ? 'aculta_studio.profile_social' : 'aculta_user.redes_form',
      ['rede' => $rede],
    )->toString();
    // Atributos e anexos são lidos e gravados de volta como arrays, sem escrita aninhada em valores não tipados.
    $attributes = is_array($form['#attributes'] ?? NULL) ? $form['#attributes'] : [];
    $attributes['data-portal-ui'] = $studio ? 'studio-social-form' : 'social-link-dialog';
    // Minha Conta keeps its modal/JSON workflow. The Studio full-page form
    // uses native Form API for validation, CSRF, and a no-JavaScript fallback.
    if (!$studio) {
      $form['#aculta_json_save'] = TRUE;
      $attributes['data-aculta-json-form'] = 'social';
      $attached = is_array($form['#attached'] ?? NULL) ? $form['#attached'] : [];
      $libraries = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
      $libraries[] = 'aculta_user/account-bio-form';
      $attached['library'] = $libraries;
      $settings = is_array($attached['drupalSettings'] ?? NULL) ? $attached['drupalSettings'] : [];
      $acultaUser = is_array($settings['acultaUser'] ?? NULL) ? $settings['acultaUser'] : [];
      $jsonForms = is_array($acultaUser['jsonForms'] ?? NULL) ? $acultaUser['jsonForms'] : [];
      $jsonForms['social'] = [
        'url' => Url::fromRoute('aculta_user.redes_save', [], ['query' => ['token' => $this->csrfToken->get('redes/salvar')]])->toString(),
      ];
      $acultaUser['jsonForms'] = $jsonForms;
      $settings['acultaUser'] = $acultaUser;
      $attached['drupalSettings'] = $settings;
      $form['#attached'] = $attached;
    }
    $form['#attributes'] = $attributes;
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
      // SDC com is_submit: o envio é capturado pelo JS (data-aculta-clear).
      $form['actions']['clear'] = [
        '#type' => 'aculta_portal_button',
        '#props' => [
          'text' => (string) $this->t('Limpar'),
          'variant' => 'secondary',
          'is_submit' => TRUE,
        ],
        '#attributes' => ['data-aculta-clear' => ''],
        '#weight' => -1,
      ];
    }
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Salvar')];
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<array-key, mixed> $form
   *   Estrutura do formulário.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $rede = $this->textValue($form_state->getValue('rede'));
    try {
      $uri = $this->networks->normalize($rede, $this->textValue($form_state->getValue('link')));
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
    return $this->currentRouteMatch?->getRouteName() === 'aculta_studio.profile_social';
  }

  /**
   * Perfil participante da conta atual, criado se ainda não existe.
   */
  private function profile(): ProfileInterface {
    $storage = $this->entityTypeManagerService->getStorage('profile');
    $account = $this->currentUserService->getAccount();
    return PerfilDoPapel::obter($storage, $account);
  }

  /**
   * Texto de um valor de formulário ou de campo; vazio quando o valor não é escalar.
   */
  private function textValue(mixed $value): string {
    return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
  }

}
