<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\aculta_portal\AccountCoursesManager;
use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\change_mail_page\Form\ChangeMailForm;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\commerce_payment\Form\PaymentMethodAddForm;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\email_confirmer\EmailConfirmerManagerInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Private user account area. */
final class PortalController extends ControllerBase {

  /** Social Auth stores the network plugin ID, not its URL short name. */
  private const GOOGLE_SOCIAL_AUTH_PLUGIN_ID = 'social_auth_google';

  /** Transporte do Mailer Plus usado pelo Portal (ver docs/portal/EMAIL-TRANSPORT.md). */
  private const MAIL_TRANSPORT = 'smtp2go';

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFormBuilderInterface $forms,
    private readonly FormBuilderInterface $accountFormBuilder,
    private readonly AccountCoursesManager $accountCourses,
    private readonly DomainPurposeManager $domainPurposeManager,
    private readonly UserDataInterface $userData,
    private readonly AuthIntegrationManager $authIntegrationManager,
    private readonly BlockManagerInterface $blockManager,
    private readonly EmailConfirmerManagerInterface $emailConfirmer,
    private readonly RouteMatchInterface $routeMatch,
    private readonly AccountInterface $account,
    private readonly ModuleHandlerInterface $modules,
    private readonly ConfigFactoryInterface $configs,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly KeyRepositoryInterface $keys,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('form_builder'),
      $container->get('aculta_portal.account_courses'),
      $container->get('aculta_portal.domain_purpose'),
      $container->get('user.data'),
      $container->get('aculta_portal.auth_integration'),
      $container->get('plugin.manager.block'),
      $container->get('email_confirmer'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('key.repository'),
    );
  }

  /**
   * Keeps Core's legacy user.page route compatible without exposing its UI.
   */
  public function legacyUserPageRedirect(): TrustedRedirectResponse {
    $accountRoot = $this->domainPurposeManager->pathUrl('account', '/');
    return new TrustedRedirectResponse(
      $accountRoot?->toString() ?? Url::fromRoute('<front>')->toString(),
    );
  }

  public function dashboard(): array {
    $account = $this->entities->getStorage('user')->load($this->account->id());
    $profile = $this->loadParticipantProfile($account);
    $nickname = $profile && $profile->hasField('field_nickname') && !$profile->get('field_nickname')->isEmpty() ? $profile->get('field_nickname')->value : '';
    $first_name = $profile && !$profile->get('field_first_name')->isEmpty() ? $profile->get('field_first_name')->value : '';
    $display_name = $nickname ?: ($first_name ?: $account->getDisplayName());
    $file = $account->hasField('user_picture') && !$account->get('user_picture')->isEmpty() ? $account->get('user_picture')->entity : NULL;
    $photo = $file ? [
      '#theme' => 'image_style',
      '#style_name' => 'aculta_avatar',
      '#uri' => $file->getFileUri(),
      '#alt' => $this->t('Foto de perfil de @name', ['@name' => $display_name]),
      '#attributes' => ['class' => ['portal-account__avatar']],
    ] : [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => mb_strtoupper(mb_substr($display_name, 0, 1)),
      '#attributes' => ['class' => ['portal-account__avatar', 'portal-account__avatar--empty'], 'aria-hidden' => 'true'],
    ];

    return [
      'identity' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['portal-account__identity']],
        'photo_editor' => [
          '#theme' => 'aculta_portal_photo_editor',
          '#photo' => $photo,
          '#photo_form' => $this->buildPortalAccountForm($account),
          '#display_name' => $display_name,
        ],
        'details' => [
          '#type' => 'container',
          'name' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $display_name],
          'email' => ['#plain_text' => $account->getEmail()],
        ],
      ],
      'intro' => [
        '#plain_text' => $this->t('Use o menu para acompanhar seu apoio, seus cursos, atualizar seus dados e gerenciar sua conta.'),
      ],
      'courses_summary' => $this->buildCoursesSummary($account),
      '#cache' => ['contexts' => ['user'], 'tags' => $account->getCacheTags(), 'max-age' => 0],
    ];
  }

  /** Builds a compact learning summary for the account overview. */
  private function buildCoursesSummary(AccountInterface $account): array {
    $count = $this->accountCourses->countCourses($account);
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['portal-account-courses-summary']],
      'title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('Cursos')],
      'summary' => [
        '#plain_text' => $count === 1
          ? $this->t('Você participa de 1 curso.')
          : $this->t('Você participa de @count cursos.', ['@count' => $count]),
      ],
    ];
    if ($count > 0) {
      $build['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Ver meus cursos'),
        '#url' => Url::fromRoute('aculta_portal.account_courses'),
      ];
    }
    else {
      $catalog = $this->accountCourses->catalogUrl();
      if ($catalog) {
        $build['link'] = [
          '#type' => 'link',
          '#title' => $this->t('Ver cursos disponíveis'),
          '#url' => $catalog,
        ];
      }
    }
    return $build;
  }

  public function myData(): array {
    return $this->buildDataSection('basics');
  }

  public function address(): array {
    return $this->buildDataSection('address');
  }

  /** Caminho aposentado (página do acordo substituída pelo popup de aceite): responde 404. */
  public function retiredNotFound(): never {
    throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
  }

  /**
   * Lista e cadastro de endereços do Commerce foram substituídos pelo formulário de Meus Dados (CEP).
   * Todos os caminhos antigos redirecionam para lá (302). Ver docs/portal/REDIRECTS.md.
   */
  public function addressRedirect(): TrustedRedirectResponse {
    $target = $this->domainPurposeManager->routeUrl('account', 'aculta_portal.my_data_address');
    return new TrustedRedirectResponse($target?->toString() ?? Url::fromRoute('aculta_portal.my_data')->toString(), 302);
  }

  /** Meus pedidos: compras da pessoa logada (rascunhos de carrinho ficam fora). */
  public function orders(): array {
    $storage = $this->entities->getStorage('commerce_order');
    $ids = $storage->getQuery()->accessCheck(TRUE)
      ->condition('uid', $this->account->id())
      ->condition('state', 'draft', '<>')
      ->sort('placed', 'DESC')
      ->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $total = $order->getTotalPrice();
      $rows[] = [
        '#' . $order->getOrderNumber(),
        $order->getPlacedTime() ? $this->dateFormatter->format($order->getPlacedTime(), 'short') : '',
        $total ? $total->getNumber() . ' ' . $total->getCurrencyCode() : '',
        $order->getState()->getLabel(),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['portal-account__commerce', 'portal-account__commerce--orders']],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Pedido'), $this->t('Data'), $this->t('Total'), $this->t('Situação')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não tem pedidos.'),
      ],
    ];
  }

  /** Meus pagamentos: métodos de pagamento da pessoa logada, somente leitura. */
  public function payments(): array {
    $storage = $this->entities->getStorage('commerce_payment_method');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('uid', $this->account->id())->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $method) {
      $rows[] = [
        $method->label(),
        $method->bundle(),
        $method->isDefault() ? $this->t('Sim') : $this->t('Não'),
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['portal-account__commerce', 'portal-account__commerce--payments']],
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Método'), $this->t('Tipo'), $this->t('Padrão')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não tem métodos de pagamento salvos.'),
      ],
    ];
  }

  public function connections(): array {
    $account = $this->entities->getStorage('user')->load($this->account->id());
    $google_ready = $this->authIntegrationManager->isGoogleConfigured();
    $links = [];
    if ($this->modules->moduleExists('social_auth') && $this->entities->hasDefinition('social_auth')) {
      $links = $this->entities->getStorage('social_auth')->loadByProperties([
        'user_id' => $account->id(),
        'plugin_id' => self::GOOGLE_SOCIAL_AUTH_PLUGIN_ID,
      ]);
    }

    $items = [
      'description' => ['#plain_text' => $this->t('Gerencie as contas externas conectadas à sua conta.')],
      'google_title' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => 'Google'],
    ];
    if ($links) {
      $social_auth = reset($links);
      $provider_email = $social_auth->getAdditionalData()['provider_email'] ?? NULL;
      if (is_string($provider_email) && filter_var($provider_email, FILTER_VALIDATE_EMAIL)) {
        $items['google_status'] = ['#plain_text' => $this->t('Conta conectada')];
        $items['google_account'] = [
          '#plain_text' => $this->t('Conta Google: @email', ['@email' => $provider_email]),
        ];
      }
      else {
        $items['google_status'] = ['#plain_text' => $this->t('Conta conectada')];
        $items['google_account'] = ['#plain_text' => $this->t('O endereço da conta Google ainda não está disponível nesta conexão.')];
        $items['google_reconnect'] = [
          '#type' => 'link',
          '#title' => $this->t('Atualizar conexão Google'),
          '#url' => Url::fromRoute('social_auth.network.redirect', ['network' => 'google']),
          '#attributes' => ['class' => ['button', 'button--secondary']],
        ];
      }
      if ($this->hasUserChosenPassword($account)) {
        $items['disconnect'] = [
          '#type' => 'link',
          '#title' => $this->t('Desconectar Google'),
          '#url' => Url::fromRoute('entity.social_auth.delete_form', ['social_auth' => $social_auth->id()]),
          '#attributes' => ['class' => ['button', 'button--secondary']],
          '#access' => $social_auth->access('delete'),
        ];
      }
      else {
        $items['password_notice'] = ['#plain_text' => $this->t('Defina uma senha para sua conta antes de desconectar o Google.')];
        $items['password_link'] = ['#type' => 'link', '#title' => $this->t('Gerenciar segurança da conta'), '#url' => Url::fromRoute('aculta_portal.security')];
      }
    }
    elseif ($google_ready) {
      $items['google_status'] = ['#plain_text' => $this->t('Nenhuma conta Google está conectada.')];
      $items['google_login'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-auth-provider']],
        'provider' => $this->blockManager
          ->createInstance('social_auth_login', [])
          ->build(),
      ];
    }
    else {
      $items['google_status'] = ['#plain_text' => $this->t('A conexão com o Google estará disponível quando a configuração institucional estiver concluída.')];
    }
    $items['#cache'] = ['contexts' => ['user'], 'tags' => $account->getCacheTags(), 'max-age' => 0];
    return $items;
  }

  public function security(): array {
    $account = $this->entities->getStorage('user')->load($this->account->id());
    $email_ready = $this->transactionalMailReady();
    $content = [
      'intro' => ['#plain_text' => $this->t('Gerencie como você acessa sua conta.')],
      'email_section' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-security-card']],
        'heading' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('E-mail de acesso')],
        'current_label' => ['#type' => 'html_tag', '#tag' => 'h4', '#value' => $this->t('E-mail atual')],
        'current_email' => ['#plain_text' => $account->getEmail()],
      ],
      'password_section' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['aculta-security-card']],
        'heading' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $this->t('Senha')],
        'description' => ['#plain_text' => $this->t('Mantenha uma senha segura para acessar sua conta.')],
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions'], 'tags' => $account->getCacheTags(), 'max-age' => 0],
    ];

    if ($email_ready && $this->modules->moduleExists('email_confirmer_user') && $this->modules->moduleExists('change_mail_page')) {
      $content['email_section']['change_form'] = $this->accountFormBuilder->getForm(ChangeMailForm::class, $account);
      $pending_email = $this->pendingEmail($account->id());
      if ($pending_email !== NULL) {
        $content['email_section']['pending'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['aculta-security-pending'], 'aria-live' => 'polite'],
          'label' => ['#type' => 'html_tag', '#tag' => 'h4', '#value' => $this->t('Alteração pendente')],
          'email' => ['#plain_text' => $pending_email],
          'message' => ['#plain_text' => $this->t('Aguardando confirmação no novo endereço.')],
        ];
      }
    }
    else {
      $content['email_section']['mail_notice'] = ['#plain_text' => $this->t('A alteração de e-mail estará disponível após a ativação do serviço de mensagens da conta.')];
    }

    if ($this->hasUserChosenPassword($account)) {
      $content['password_section']['change_form'] = $this->buildPortalAccountForm($account);
    }
    else {
      $content['password_section']['social_notice'] = ['#plain_text' => $this->t('Sua conta utiliza acesso externo. Quando o fluxo de definição de senha local estiver homologado, ele poderá ser oferecido aqui.')];
    }

    return [
      'content' => $content,
    ];
  }

  /** Returns TRUE only when the account has a user-chosen local password. */
  private function hasUserChosenPassword(UserInterface $account): bool {
    $password = $account->getPassword();
    return is_string($password)
      && $password !== ''
      && !(bool) $this->userData->get('aculta_portal', $account->id(), 'social_auth_password_unset');
  }

  /**
   * Mailer Plus (mailer_override) envia pelo transporte SMTP2GO. As credenciais vêm de Keys
   * (override nativo do módulo Key), nunca da configuração exportada.
   */
  private function transactionalMailReady(): bool {
    $site_mail = trim((string) $this->configs->get('system.site')->get('mail'));
    return $this->modules->moduleExists('mailer_override')
      && $this->configs->get('mailer_transport.settings')->get('default_transport') === self::MAIL_TRANSPORT
      && $this->keyHasValue('smtp2go_username')
      && $this->keyHasValue('smtp2go_password')
      && $site_mail !== '';
  }

  private function keyHasValue(string $id): bool {
    $key = $this->keys->getKey($id);
    return $key !== NULL && trim((string) $key->getKeyValue()) !== '';
  }

  private function pendingEmail(int|string $uid): ?string {
    if (!$this->modules->moduleExists('email_confirmer_user')) {
      return NULL;
    }
    $pending = $this->userData->get('email_confirmer_user', $uid, 'email_change_new_address');
    if (!is_string($pending) || $pending === '') {
      return NULL;
    }
    $confirmations = $this->emailConfirmer->getConfirmations($pending, 'pending', 0, 'email_confirmer_user');
    foreach ($confirmations as $confirmation) {
      if ((int) $confirmation->get('uid')->target_id === (int) $uid) {
        return $pending;
      }
    }
    return NULL;
  }

  private function buildDataSection(string $section): array {
    $account = $this->entities->getStorage('user')->load($this->account->id());
    $profile = $section === 'address'
      ? $this->loadCommerceAddressProfile($account)
      : $this->loadParticipantProfile($account);
    $route = $section === 'address' ? 'aculta_portal.my_data_address' : 'aculta_portal.my_data';
    $title = $section === 'address' ? $this->t('Endereço') : $this->t('Informações básicas');
    $links = [];
    foreach ([
      'basics' => ['title' => $this->t('Informações básicas'), 'route' => 'aculta_portal.my_data'],
      'address' => ['title' => $this->t('Endereço'), 'route' => 'aculta_portal.my_data_address'],
    ] as $key => $tab) {
      $link = [
        '#type' => 'link',
        '#title' => $tab['title'],
        '#url' => Url::fromRoute($tab['route']),
        '#attributes' => ['data-portal-account-data-link' => 'true', 'class' => ['portal-account-data__link']],
      ];
      if ($section === $key) {
        $link['#attributes']['aria-current'] = 'page';
        $link['#attributes']['class'][] = 'is-active';
      }
      $links[] = $link;
    }
    return [
      'description' => ['#plain_text' => $this->t('Mantenha suas informações pessoais e de contato atualizadas.')],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['portal-account-data__nav'], 'aria-label' => $this->t('Seções de meus dados'), 'data-portal-account-data-nav' => 'true'],
        'links' => $links,
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['portal-account-data__content'], 'data-portal-account-data-content' => 'true', 'aria-busy' => 'false'],
        'heading' => ['#type' => 'html_tag', '#tag' => 'h3', '#value' => $title, '#attributes' => ['tabindex' => '-1']],
        'account_email' => $section === 'basics' ? [
          '#type' => 'container',
          '#attributes' => ['class' => ['portal-account-data__email']],
          'label' => ['#type' => 'html_tag', '#tag' => 'h4', '#value' => $this->t('E-mail')],
          'value' => ['#plain_text' => $account->getEmail()],
        ] : [],
        'form' => $this->forms->getForm($profile, 'edit'),
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'route'], 'tags' => $account->getCacheTags(), 'max-age' => 0],
    ];
  }

  private function loadParticipantProfile(UserInterface $account) {
    $profiles = $this->entities->getStorage('profile')->loadByUser($account, 'participante');
    return $profiles ?: $this->entities->getStorage('profile')->create([
      'type' => 'participante',
      'uid' => $account->id(),
      'status' => TRUE,
      'is_default' => TRUE,
    ]);
  }

  /**
   * Loads or prepares the account's canonical Commerce customer address.
   *
   * An unsaved Profile entity lets the standard Commerce Address field form
   * create the customer profile through normal Form API submission.
   */
  private function loadCommerceAddressProfile(UserInterface $account) {
    $storage = $this->entities->getStorage('profile');
    $existing = $storage->loadByUser($account, 'customer');
    if (!$existing) {
      $profiles = $storage->loadByProperties(['uid' => $account->id(), 'type' => 'customer']);
      $existing = $profiles ? reset($profiles) : NULL;
    }
    return $existing ?: $storage->create([
      'type' => 'customer',
      'uid' => $account->id(),
      'status' => TRUE,
      'is_default' => TRUE,
    ]);
  }

  /**
   * Builds Core's user form safely inside the Portal route.
   *
   * Social Auth's generic user-form alter expects a `user` route parameter,
   * which account pages do not have. Temporarily supply the current account
   * during form construction, then remove Social Auth's separate account
   * management section because the Portal has a dedicated Connections page.
   */
  private function buildPortalAccountForm(UserInterface $account): array {
    $parameters = $this->routeMatch->getParameters();
    $had_user_parameter = $parameters->has('user');
    $previous_user_parameter = $parameters->get('user');
    $parameters->set('user', $account);
    try {
      $form = $this->forms->getForm($account, 'default');
      unset($form['social_auth']);
      return $form;
    }
    finally {
      if ($had_user_parameter) {
        $parameters->set('user', $previous_user_parameter);
      }
      else {
        $parameters->remove('user');
      }
    }
  }

}
