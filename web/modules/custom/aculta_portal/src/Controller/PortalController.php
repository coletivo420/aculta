<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Controller;

use Drupal\Core\Template\Attribute;
use Drupal\aculta_portal\Account\AccountDocumentInterface;
use Drupal\aculta_portal\Form\AccountOtherDataForm;
use Drupal\aculta_portal\Render\PortalLinkElement;
use Drupal\aculta_portal\Auth\AuthIntegrationManager;
use Drupal\aculta_portal\Domain\DomainPurposeManager;
use Drupal\aculta_portal\Form\AccountPasswordCreateForm;
use Drupal\change_mail_page\Form\ChangeMailForm;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
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
    private readonly CurrencyFormatterInterface $currencyFormatter,
    private readonly ?AccountDocumentInterface $accountDocument,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('form_builder'),
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
      $container->get('commerce_price.currency_formatter'),
      self::accountDocumentService($container),
    );
  }

  /**
   * Resolves the canonical implementation, retaining the legacy service during migration.
   */
  private static function accountDocumentService(ContainerInterface $container): ?AccountDocumentInterface {
    foreach (['aculta_associado_id.account_document', 'aculta_id_cpf.account_document'] as $service_id) {
      if ($container->has($service_id)) {
        return $container->get($service_id);
      }
    }
    return NULL;
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
      '#attributes' => ['data-portal-ui' => 'account-avatar'],
    ] : [
      '#theme' => 'aculta_portal_element',
      '#tag' => 'span',
      '#value' => mb_strtoupper(mb_substr($display_name, 0, 1)),
      '#element_attributes' => new Attribute(['data-portal-ui' => 'account-avatar-empty', 'aria-hidden' => 'true']),
    ];

    // Extensão do editor de foto: o submódulo aculta_user adiciona o sorteio de avatar (formulário próprio).
    $photoForm = [
      'preview' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['data-portal-ui' => 'account-photo-preview'],
        'avatar' => ['#type' => 'html_tag', '#tag' => 'span', '#attributes' => ['data-aculta-user-avatar' => ''], 'image' => $photo],
      ],
      'form' => $this->buildPortalAccountForm($account),
    ];
    $this->modules->alter('aculta_portal_photo_editor', $photoForm, $account);

    $build = [
      'identity' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-identity'],
        'photo_editor' => [
          '#theme' => 'aculta_portal_photo_editor',
          // Avatar em <span> (válido dentro do botão) com atributo de alvo: o sorteio (aculta_user) troca todas as
          // ocorrências por AJAX. A visualização no popup mostra o mesmo avatar antes do envio do formulário.
          '#photo' => ['#type' => 'html_tag', '#tag' => 'span', '#attributes' => ['data-aculta-user-avatar' => ''], 'image' => $photo],
          '#photo_form' => $photoForm,
          '#display_name' => $display_name,
        ],
        'details' => [
          '#type' => 'container',
          'name' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $display_name],
          'email' => ['#plain_text' => $account->getEmail()],
        ],
      ],
      'intro' => [
        '#plain_text' => $this->t('Use o menu para acompanhar seu apoio, seus cursos, atualizar seus dados e gerenciar sua conta.'),
      ],
      '#cache' => ['contexts' => ['user'], 'tags' => $account->getCacheTags(), 'max-age' => 0],
    ];
    // Caixas extras da visão geral (ex.: Bio, do aculta_user) entram pelo alter; Cursos saiu desta visão.
    $this->modules->alter('aculta_portal_dashboard', $build, $account);
    return $build;
  }

  public function myData(): array {
    return $this->buildDataSection('basics');
  }

  public function address(): array {
    return $this->buildDataSection('address');
  }

  /** Outros Dados (CPF ou CNPJ). Sem o submódulo que implementa o documento, o caminho responde 404. */
  public function otherData(): array {
    if ($this->accountDocument === NULL) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }
    return $this->buildDataSection('other');
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
  /** Situação do pedido em português (os rótulos do workflow vêm em inglês). */
  private function orderStateLabel($order): string {
    return match ($order->getState()->getId()) {
      'completed' => (string) $this->t('Concluído'),
      'canceled' => (string) $this->t('Cancelado'),
      'draft' => (string) $this->t('Rascunho'),
      default => (string) $this->t('Em andamento'),
    };
  }

  /**
   * Caixa da Mercado Pago em "Meus pedidos": os pagamentos são processados pelo Mercado Pago, e o portal não guarda
   * meios de pagamento. O texto e o link vêm da configuração aculta_portal.settings (mercado_pago_account_url).
   */
  private function paymentNotice(): array {
    return [
      '#theme' => 'aculta_portal_payment_notice',
      '#intro' => $this->t('Todos os nossos pagamentos são processados pelo Mercado Pago.'),
      '#link_label' => $this->t('Acesse sua conta Mercado Pago'),
      '#url' => (string) $this->configs->get('aculta_portal.settings')->get('mercado_pago_account_url'),
      '#weight' => 10,
    ];
  }

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
      // data-label: rótulo de cada coluna, usado pelo tema no celular (linhas empilhadas).
      $rows[] = [
        ['data' => ['#type' => 'link', '#title' => '#' . $order->getOrderNumber(), '#url' => Url::fromRoute('entity.commerce_order.user_view', ['commerce_order' => $order->id()])], 'data-label' => (string) $this->t('Pedido')],
        ['data' => $order->getPlacedTime() ? $this->dateFormatter->format($order->getPlacedTime(), 'short') : '', 'data-label' => (string) $this->t('Data')],
        ['data' => $total ? $this->currencyFormatter->format($total->getNumber(), $total->getCurrencyCode()) : '', 'data-label' => (string) $this->t('Total')],
        ['data' => $this->orderStateLabel($order), 'data-label' => (string) $this->t('Situação')],
      ];
    }
    return [
      '#type' => 'container',
      '#attributes' => ['data-portal-ui' => 'account-commerce-orders'],
      'payment_notice' => $this->paymentNotice(),
      'table' => [
        '#type' => 'table',
        '#header' => [$this->t('Pedido'), $this->t('Data'), $this->t('Total'), $this->t('Situação')],
        '#rows' => $rows,
        '#empty' => $this->t('Você ainda não tem pedidos.'),
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
      'google_title' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => 'Google'],
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
        $items['google_reconnect'] = PortalLinkElement::build($this->t('Atualizar conexão Google'), Url::fromRoute('social_auth.network.redirect', ['network' => 'google']), ['data-portal-ui' => 'button-secondary']);
      }
      if ($this->hasUserChosenPassword($account)) {
        $items['disconnect'] = [
          '#type' => 'link',
          '#title' => $this->t('Desconectar Google'),
          '#url' => Url::fromRoute('entity.social_auth.delete_form', ['social_auth' => $social_auth->id()]),
          '#attributes' => ['data-portal-ui' => 'button-secondary'],
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
        '#attributes' => ['data-portal-ui' => 'auth-provider'],
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

  /** Segurança, aba E-mail (/seguranca): e-mail atual e troca do endereço (com confirmação). */
  public function security(): array {
    return $this->buildSecuritySection('email');
  }

  /** Segurança, aba Senha (/seguranca/senha): troca da senha (exige a senha atual). */
  public function securityPassword(): array {
    return $this->buildSecuritySection('password');
  }

  /**
   * Abas internas da Segurança, no mesmo padrão de Meus Dados: E-mail e Senha, cada uma com URL própria. O conteúdo
   * fica no shell da conta; os formulários salvam por AJAX (mensagens em português).
   */
  private function buildSecuritySection(string $section): array {
    $account = $this->entities->getStorage('user')->load($this->account->id());
    $links = [];
    foreach ([
      'email' => ['title' => $this->t('E-mail'), 'route' => 'aculta_portal.security'],
      'password' => ['title' => $this->t('Senha'), 'route' => 'aculta_portal.security_password'],
    ] as $key => $tab) {
      $link = PortalLinkElement::build($tab['title'], Url::fromRoute($tab['route']), ['data-portal-account-data-link' => 'true', 'data-portal-ui' => 'account-data-link']);
      if ($section === $key) {
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    $content = [
      'description' => ['#plain_text' => $section === 'email'
        ? $this->t('Veja o e-mail de acesso e solicite a troca dele.')
        : $this->t('Mantenha uma senha segura para acessar sua conta.')],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-nav', 'aria-label' => $this->t('Seções de segurança'), 'data-portal-account-data-nav' => 'true'],
        'links' => $links,
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'route'], 'tags' => $account->getCacheTags(), 'max-age' => 0],
    ];
    return ['content' => $content + ['section' => $this->securitySectionContent($account, $section)]];
  }

  /** Conteúdo de cada aba de Segurança (e-mail ou senha). */
  private function securitySectionContent(UserInterface $account, string $section): array {
    if ($section === 'password') {
      if ($this->hasUserChosenPassword($account)) {
        return ['change_form' => $this->buildPortalAccountForm($account)];
      }
      // Conta sem senha escolhida (criada com Social Auth): o formulário cria a primeira senha, sem pedir a atual.
      return ['create_form' => $this->accountFormBuilder->getForm(AccountPasswordCreateForm::class)];
    }
    $email_ready = $this->transactionalMailReady();
    $section_content = [
      'current_label' => ['#theme' => 'aculta_portal_element', '#tag' => 'h4', '#value' => $this->t('E-mail atual')],
      'current_email' => ['#plain_text' => $account->getEmail()],
    ];
    if ($email_ready && $this->modules->moduleExists('email_confirmer_user') && $this->modules->moduleExists('change_mail_page')) {
      $section_content['change_form'] = $this->accountFormBuilder->getForm(ChangeMailForm::class, $account);
      $pending_email = $this->pendingEmail($account->id());
      if ($pending_email !== NULL) {
        $section_content['pending'] = [
          '#type' => 'container',
          '#attributes' => ['data-portal-ui' => 'security-pending', 'aria-live' => 'polite'],
          'label' => ['#theme' => 'aculta_portal_element', '#tag' => 'h4', '#value' => $this->t('Alteração pendente')],
          'email' => ['#plain_text' => $pending_email],
          'message' => ['#plain_text' => $this->t('Aguardando confirmação no novo endereço.')],
        ];
      }
    }
    else {
      $section_content['mail_notice'] = ['#plain_text' => $this->t('A alteração de e-mail estará disponível após a ativação do serviço de mensagens da conta.')];
    }
    return $section_content;
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
    $profile = match ($section) {
      'address' => $this->loadCommerceAddressProfile($account),
      'other' => NULL,
      default => $this->loadParticipantProfile($account),
    };
    $route = match ($section) {
      'address' => 'aculta_portal.my_data_address',
      'other' => 'aculta_portal.my_data_other',
      default => 'aculta_portal.my_data',
    };
    $title = match ($section) {
      'address' => $this->t('Endereço'),
      'other' => $this->t('Outros Dados'),
      default => $this->t('Informações básicas'),
    };
    $tabs = [
      'basics' => ['title' => $this->t('Informações básicas'), 'route' => 'aculta_portal.my_data'],
      'address' => ['title' => $this->t('Endereço'), 'route' => 'aculta_portal.my_data_address'],
    ];
    if ($this->accountDocument !== NULL) {
      $tabs['other'] = ['title' => $this->t('Outros Dados'), 'route' => 'aculta_portal.my_data_other'];
    }
    $links = [];
    foreach ($tabs as $key => $tab) {
      $link = PortalLinkElement::build($tab['title'], Url::fromRoute($tab['route']), ['data-portal-account-data-link' => 'true', 'data-portal-ui' => 'account-data-link']);
      if ($section === $key) {
        // Estado da aba atual (aria-current; "is-active" é só o estado que o tema também reconhece).
        $link['#element_attributes']->setAttribute('aria-current', 'page');
        $link['#element_attributes']->addClass('is-active');
      }
      $links[] = $link;
    }
    return [
      'description' => ['#plain_text' => $this->t('Mantenha suas informações pessoais e de contato atualizadas.')],
      'tabs' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-nav', 'aria-label' => $this->t('Seções de meus dados'), 'data-portal-account-data-nav' => 'true'],
        'links' => $links,
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['data-portal-ui' => 'account-data-content', 'data-portal-account-data-content' => 'true', 'aria-busy' => 'false'],
        'heading' => ['#theme' => 'aculta_portal_element', '#tag' => 'h3', '#value' => $title, '#element_attributes' => new Attribute(['tabindex' => '-1'])],
        'form' => $section === 'other'
          ? $this->accountFormBuilder->getForm(AccountOtherDataForm::class)
          : $this->profileForm($section, $profile),
      ],
      '#cache' => ['contexts' => ['user', 'user.permissions', 'route'], 'tags' => $account->getCacheTags(), 'max-age' => 0],
    ];
  }

  /**
   * Formulário do perfil. No endereço, o número e a marca S/N saem do logradouro para os campos próprios
   * (StreetNumberField), logo depois da rua; ao gravar, são juntados de novo (PortalHooks, entity builder).
   */
  private function profileForm(string $section, $profile): array {
    if ($section !== 'address') {
      return $this->forms->getForm($profile, 'edit');
    }
    $parts = $this->savedParts($profile);
    $display = $profile;
    if (($parts['number'] !== '' || $parts['sn']) && !$profile->get('address')->isEmpty()) {
      $display = clone $profile;
      $address = $display->get('address')->first();
      $value = $address->getValue();
      $value['address_line1'] = $parts['street'];
      $address->setValue($value);
    }
    // O campo Número é criado no hook de alteração do formulário (PortalHooks::formAlter), antes do processamento.
    return $this->forms->getForm($display, 'edit');
  }

  /** Rua, número e S/N salvos no logradouro. */
  private function savedParts($profile): array {
    if (!$profile->hasField('address') || $profile->get('address')->isEmpty()) {
      return ['street' => '', 'number' => '', 'sn' => FALSE];
    }
    return \Drupal\aculta_portal\Account\StreetNumberField::split((string) $profile->get('address')->first()->getAddressLine1());
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
