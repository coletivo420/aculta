<?php

/** Read-only local checks for account ownership, permissions, and migration. */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1', 'default'], TRUE)) {
  throw new RuntimeException('This audit is local-only.');
}

$checks = [];
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks[] = $message;
};

$modules = \Drupal::moduleHandler();
$assert($modules->moduleExists('aculta_portal'), 'aculta_portal is enabled.');
$assert(!$modules->moduleExists('aculta_apoio'), 'Former standalone aculta_apoio is not enabled.');
$assert(!$modules->moduleExists('aculta_editorial'), 'Consolidated aculta_editorial is not enabled.');
$assert(!is_dir(DRUPAL_ROOT . '/modules/custom/aculta_editorial'), 'Consolidated aculta_editorial directory has been removed.');
$assert(!\Drupal::entityTypeManager()->hasDefinition('aculta_contribution'), 'Legacy aculta_contribution entity definition is absent.');
$assert(!\Drupal::database()->schema()->tableExists('aculta_contribution'), 'Legacy contribution storage table is absent.');
$assert(!\Drupal::database()->schema()->tableExists('aculta_apoio_webhook'), 'Legacy webhook receipt table is absent.');
$assert(!is_dir(DRUPAL_ROOT . '/modules/custom/aculta_apoio'), 'No duplicate standalone support module remains in custom code.');
$assert(!is_dir(DRUPAL_ROOT . '/modules/custom/aculta_mercadopago'), 'No custom Mercado Pago gateway module exists.');
$assert($modules->moduleExists('profile') && $modules->moduleExists('address'), 'Profile and Address are available.');
$assert($modules->moduleExists('crop') && $modules->moduleExists('image_widget_crop'), 'Core image crop integration is enabled.');
$assert($modules->moduleExists('social_auth') && $modules->moduleExists('social_auth_google'), 'Social Auth providers are available.');
$assert($modules->moduleExists('captcha') && $modules->moduleExists('turnstile'), 'CAPTCHA and Turnstile integration are enabled.');
$assert($modules->moduleExists('agreement') && $modules->moduleExists('key'), 'Agreement and Key infrastructure are enabled.');
$assert(!$modules->moduleExists('user_registrationpassword'), 'Incompatible user_registrationpassword remains removed.');
$assert($modules->moduleExists('username_enumeration_prevention'), 'Username Enumeration Prevention remains enabled.');
$assert(!$modules->moduleExists('honeypot'), 'Honeypot is not enabled.');
$assert($modules->moduleExists('login_emailusername'), 'The existing Login Email or Username module is enabled.');
$assert($modules->moduleExists('email_confirmer') && $modules->moduleExists('email_confirmer_user'), 'Email Confirmer and its user integration are enabled.');
$assert($modules->moduleExists('change_mail_page'), 'Change Mail Page supplies the canonical new-address form.');
$assert(\Drupal::config('email_confirmer_user.settings')->get('user_email_change.enabled') === TRUE, 'Email Confirmer intercepts user email changes.');
// A camada de envio do Drupal controla o envio: o transporte padrão precisa de usuário e senha configurados na própria camada de envio do Drupal.
$default_transport = (string) \Drupal::config('mailer_transport.settings')->get('default_transport');
$default_raw = \Drupal::config('mailer_transport.mailer_transport.' . $default_transport)->getRawData()['configuration'] ?? [];
$assert($default_transport !== '' && !empty($default_raw['user']) && !empty($default_raw['pass']), 'O transporte padrão da camada de envio do Drupal tem credenciais configuradas na própria camada de envio do Drupal.');
// A configuração versionada não guarda credenciais.
$smtp_versioned = \Symfony\Component\Yaml\Yaml::parseFile(DRUPAL_ROOT . '/../config/sync/mailer_transport.mailer_transport.smtp.yml')['configuration'] ?? [];
$assert(($smtp_versioned['user'] ?? '') === '' && ($smtp_versioned['pass'] ?? '') === '', 'Mail transport credentials are absent from the versioned configuration.');
$login_composer = json_decode(file_get_contents(DRUPAL_ROOT . '/../composer.lock'), TRUE, 512, JSON_THROW_ON_ERROR);
$login_package = array_values(array_filter($login_composer['packages'], static fn(array $package): bool => $package['name'] === 'drupal/login_emailusername'))[0] ?? NULL;
$assert(($login_package['version'] ?? NULL) === '3.0.1', 'Login Email or Username remains at the audited 3.0.1 release.');
$confirmer_package = array_values(array_filter($login_composer['packages'], static fn(array $package): bool => $package['name'] === 'drupal/email_confirmer'))[0] ?? NULL;
$change_mail_package = array_values(array_filter($login_composer['packages'], static fn(array $package): bool => $package['name'] === 'drupal/change_mail_page'))[0] ?? NULL;
$assert(($confirmer_package['version'] ?? NULL) === '1.0.0', 'Email Confirmer remains on its stable 1.0.0 release.');
$assert(($change_mail_package['version'] ?? NULL) === '1.0.2', 'Change Mail Page remains on its stable 1.0.2 release.');
$mail_backend = \Drupal::config('system.mail')->get('interface.default');
$mail_transport = \Drupal::config('mailer_transport.settings')->get('default_transport');
$assert($mail_backend === 'symfony_mailer', 'The default mail backend is camada de envio do Drupal (symfony_mailer).');
$assert($mail_transport === 'smtp' && \Drupal::moduleHandler()->moduleExists('mailer_override'), 'camada de envio do Drupal delivers through the Hostinger (smtp) transport with mailer_override enabled.');
$assert(!\Drupal::moduleHandler()->moduleExists('smtp'), 'The legacy smtp module is not enabled (removed in M2).');
$portal_controller_source = file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Controller/PortalController.php');
$assert(str_contains($portal_controller_source, "moduleExists('mailer_override')") && str_contains($portal_controller_source, "default_transport") && !str_contains($portal_controller_source, 'keyHasValue'), 'Security only enables email change when camada de envio do Drupal, the SMTP2GO transport and its Keys are ready.');
$assert(str_contains($portal_controller_source, "getForm(\$account, 'default')") && !str_contains($portal_controller_source, "getForm(\$account, 'edit')"), 'Portal account forms use the Drupal User entity type supported default form operation.');
$assert(str_contains($portal_controller_source, "\$parameters->set('user', \$account)") && str_contains($portal_controller_source, "unset(\$form['social_auth'])") && str_contains($portal_controller_source, "\$parameters->remove('user')"), 'The Portal supplies Social Auth’s expected route parameter only while building Core User forms and removes its duplicate account section.');
$assert(\Drupal::config('system.logging')->get('error_level') === 'hide' && str_contains(file_get_contents(DRUPAL_ROOT . '/../config/sync/system.logging.yml'), 'error_level: hide'), 'Detailed errors are hidden on screen in active and synchronized configuration (hardening: nao exibir erros detalhados).');
$route_subscriber_source = file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/EventSubscriber/AccountRouteSubscriber.php');
$assert(str_contains($route_subscriber_source, "get('_raw_variables')") && str_contains($route_subscriber_source, 'isValidCorePasswordResetRequest'), 'Account route guard can identify raw user IDs before parameter conversion and delegates password-reset exception to a Core token check.');
$assert(str_contains($route_subscriber_source, "get('pass-reset-token')") && str_contains($route_subscriber_source, "get('pass_reset_" ) && str_contains($route_subscriber_source, 'hash_equals'), 'The account edit exception requires Drupal Core\'s session-bound one-time token.');
$assert(str_contains(file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Hook/EntityHooks.php'), "getRouteName() === 'entity.user.edit_form'") && str_contains(file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Hook/EntityHooks.php'), "->hasPermission('administer users')"), 'Core entity access also prevents regular users from reaching the generic account edit form.');
$portal_hooks = file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Hook/PortalHooks.php');
$assert(str_contains($portal_hooks, "^/user/([1-9][0-9]*)/edit$") && str_contains($portal_hooks, "aculta_account_edit_blocked") && str_contains($portal_hooks, "aculta_portal.security"), 'A blocked own generic account edit page offers a direct Portal Security link.');
$portal_hooks_source = file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Hook/PortalHooks.php');
$assert(str_contains($portal_hooks_source, 'Nome de usuário ou e-mail'), 'The public login label supports either username or email.');
$assert((bool) \Drupal::service('user.data')->get('aculta_portal', 999999, 'social_auth_password_unset') === FALSE, 'Social-only password marker is read per user and is not globally shared.');
$authenticated_role = \Drupal\user\Entity\Role::load('authenticated');
foreach ([
  'access administration pages', 'access toolbar', 'administer users',
  'administer account settings', 'administer permissions', 'administer roles',
  'administer key', 'administer social auth',
  'administer commerce', 'administer payment',
] as $forbidden_permission) {
  $assert(!$authenticated_role->hasPermission($forbidden_permission), 'Authenticated role has no administrative permission: ' . $forbidden_permission);
}
$assert($authenticated_role->hasPermission('access email confirmation'), 'Authenticated users can open their own private email confirmation links.');

$account_route_subscriber = \Drupal::service('aculta_portal.account_route_subscriber');
$account_switcher = \Drupal::service('account_switcher');
$route_current = \Drupal\user\Entity\User::create(['uid' => 999991, 'name' => 'portal-route-current', 'roles' => ['authenticated']]);
$route_other = \Drupal\user\Entity\User::create(['uid' => 999992, 'name' => 'portal-route-other', 'roles' => ['authenticated']]);
$route_admin = \Drupal\user\Entity\User::create(['uid' => 999993, 'name' => 'portal-route-admin', 'roles' => ['administrator']]);
$make_account_event = static function (string $route, ?object $target = NULL): \Symfony\Component\HttpKernel\Event\RequestEvent {
  $http_kernel = \Drupal::service('http_kernel');
  $request = \Symfony\Component\HttpFoundation\Request::create('/test-account-route');
  $request->attributes->set('_route', $route);
  if ($target) { $request->attributes->set('user', $target); }
  return new \Symfony\Component\HttpKernel\Event\RequestEvent($http_kernel, $request, \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
};
$account_switcher->switchTo($route_current);
try {
  $own_edit_event = $make_account_event('entity.user.edit_form', $route_current);
  $account_route_subscriber->onKernelRequest($own_edit_event);
  $domain_purpose = \Drupal::service('aculta_portal.domain_purpose');
  $security_url = $domain_purpose->routeUrl('account', 'aculta_portal.security')?->toString();
  $assert($own_edit_event->hasResponse() && $own_edit_event->getResponse()->getStatusCode() === 302 && $own_edit_event->getResponse()->headers->get('Location') === $security_url, 'A regular user is redirected from own generic user edit to Portal Security.');
  $raw_edit_event = $make_account_event('entity.user.edit_form');
  $raw_edit_event->getRequest()->attributes->set('_raw_variables', new \Symfony\Component\HttpFoundation\ParameterBag(['user' => '999991']));
  $account_route_subscriber->onKernelRequest($raw_edit_event);
  $assert($raw_edit_event->hasResponse() && $raw_edit_event->getResponse()->getStatusCode() === 302, 'The route guard redirects the own edit page when the ID is still in Drupal raw route parameters.');
  $own_profile_event = $make_account_event('entity.user.canonical', $route_current);
  $account_route_subscriber->onKernelRequest($own_profile_event);
  $account_home_url = $domain_purpose->pathUrl('account', '/')?->toString();
  $assert($own_profile_event->hasResponse() && $own_profile_event->getResponse()->headers->get('Location') === $account_home_url, 'A regular user is redirected from a public own-account profile to the Account Domain root.');
  $other_profile_event = $make_account_event('entity.user.canonical', $route_other);
  $account_route_subscriber->onKernelRequest($other_profile_event);
  $assert($other_profile_event->hasResponse() && $other_profile_event->getResponse()->getStatusCode() === 403, 'A regular user cannot view another user canonical profile.');
  $password_reset_event = $make_account_event('user.pass');
  $account_route_subscriber->onKernelRequest($password_reset_event);
  $assert(!$password_reset_event->hasResponse(), 'Core password reset routes are excluded from account redirects.');
}
finally {
$account_switcher->switchBack();
}

$reset_request = \Symfony\Component\HttpFoundation\Request::create('/user/999991/edit', 'GET', ['pass-reset-token' => 'fixture-reset-token']);
$reset_session = new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
$reset_session->set('pass_reset_999991', 'fixture-reset-token');
$reset_request->setSession($reset_session);
$assert(\Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber::isValidCorePasswordResetRequest($reset_request, 999991, 999991), 'Drupal Core valid one-time password reset token remains accepted for its own account.');
$assert(!\Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber::isValidCorePasswordResetRequest($reset_request, 999992, 999991), 'A one-time reset token cannot be used to edit another account.');
$account_switcher->switchTo($route_current);
try {
  $reset_event = $make_account_event('entity.user.edit_form');
  $reset_event->getRequest()->attributes->set('_raw_variables', new \Symfony\Component\HttpFoundation\ParameterBag(['user' => '999991']));
  $reset_event->getRequest()->query->set('pass-reset-token', 'fixture-reset-token');
  $reset_event->getRequest()->setSession($reset_session);
  $account_route_subscriber->onKernelRequest($reset_event);
  $assert(!$reset_event->hasResponse(), 'Core one-time password reset may continue to its native password form after the session token is verified.');
}
finally {
  $account_switcher->switchBack();
}
$reset_request->query->set('pass-reset-token', 'invalid-token');
$assert(!\Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber::isValidCorePasswordResetRequest($reset_request, 999991, 999991), 'An arbitrary pass-reset-token query value does not bypass account route protection.');
$reset_request->query->remove('pass-reset-token');
$assert(!\Drupal\aculta_portal\EventSubscriber\AccountRouteSubscriber::isValidCorePasswordResetRequest($reset_request, 999991, 999991), 'The generic account edit route stays protected without Core reset token.');
$account_switcher->switchTo($route_admin);
try {
  $admin_edit_event = $make_account_event('entity.user.edit_form', $route_admin);
  $account_route_subscriber->onKernelRequest($admin_edit_event);
  $assert(!$admin_edit_event->hasResponse(), 'Administrators retain the normal generic user editing route.');
}
finally {
  $account_switcher->switchBack();
}

$container = \Drupal::getContainer();
foreach (['aculta_apoio.client', 'aculta_apoio.credentials', 'aculta_apoio.manager', 'aculta_apoio.signature'] as $service) {
  $assert(!$container->has($service), 'Retired direct gateway service is absent: ' . $service);
}

$anonymous = \Drupal\user\Entity\User::create(['name' => 'access-audit-anonymous']);
// Unsaved fixture with a synthetic uid: real sessions are logged in only when
// uid > 0, which routes using _user_is_logged_in require.
$authenticated = \Drupal\user\Entity\User::create(['uid' => 999990, 'name' => 'access-audit-user', 'roles' => ['authenticated']]);
$administrator = \Drupal\user\Entity\User::create(['uid' => 1, 'name' => 'access-audit-administrator', 'roles' => ['administrator']]);
$access = \Drupal::service('access_manager');
$route_expectations = [
  'aculta_portal.dashboard' => [FALSE, TRUE, TRUE],
  'aculta_portal.support_my' => [FALSE, TRUE, TRUE],
  'aculta_portal.my_data' => [FALSE, TRUE, TRUE],
  'aculta_portal.my_data_address' => [FALSE, TRUE, TRUE],
  'aculta_portal.my_data_other' => [FALSE, TRUE, TRUE],
  'aculta_portal.connections' => [FALSE, TRUE, TRUE],
  'aculta_portal.security' => [FALSE, TRUE, TRUE],
  'aculta_portal.support_settings' => [FALSE, FALSE, TRUE],
];
foreach ($route_expectations as $route => [$anonymous_expected, $authenticated_expected, $admin_expected]) {
  $route_object = \Drupal::service('router.route_provider')->getRouteByName($route);
  $anonymous_access = $access->checkNamedRoute($route, [], $anonymous, TRUE)->isAllowed();
  $authenticated_access = $access->checkNamedRoute($route, [], $authenticated, TRUE)->isAllowed();
  $admin_access = $access->checkNamedRoute($route, [], $administrator, TRUE)->isAllowed();
  $assert($anonymous_access === $anonymous_expected, 'Anonymous route access is correct: ' . $route);
  $assert($authenticated_access === $authenticated_expected, 'Authenticated route access is correct: ' . $route);
  $assert($admin_access === $admin_expected, 'Administrator route access is correct: ' . $route);
  $assert(($route_object->getRequirements()['_access'] ?? $route_object->getRequirements()['_permission'] ?? '') !== 'TRUE' || str_starts_with($route, 'aculta_portal.support_'), 'Private route has explicit access control: ' . $route);
}
$all_routes = \Drupal::service('router.route_provider')->getAllRoutes();
$retired_portal_route_present = FALSE;
$retired_finance_route_present = FALSE;
foreach ($all_routes as $route_name => $route_definition) {
  $retired_portal_route_present = $retired_portal_route_present || $route_definition->getPath() === '/minha-conta/perfil';
  $retired_finance_route_present = $retired_finance_route_present || str_contains((string) $route_name, 'contribution') || str_contains((string) $route_name, 'webhook');
}
$assert(!$retired_portal_route_present, 'The legacy /minha-conta/perfil route is absent rather than redirected.');
$assert(!$retired_finance_route_present, 'No custom contribution or webhook route remains.');
$portal_php_files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DRUPAL_ROOT . '/modules/custom/aculta_portal', FilesystemIterator::SKIP_DOTS));
$retired_namespace_found = FALSE;
foreach ($portal_php_files as $portal_file) {
  if ($portal_file->isFile() && $portal_file->getExtension() === 'php' && str_contains(file_get_contents($portal_file->getPathname()), 'Drupal\\aculta_editorial\\')) {
    $retired_namespace_found = TRUE;
  }
}
$assert(!$retired_namespace_found, 'No runtime PHP references the retired editorial namespace.');
$assert(!class_exists('Drupal\\aculta_portal\\Entity\\Contribution'), 'No custom Contribution entity class remains.');
$assert(!is_file(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Entity/Contribution.php'), 'No duplicate financial entity implementation remains.');
$assert(!is_file(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Support/Service/LegacyContributionReader.php'), 'No legacy contribution reader remains.');
$assert(!is_file(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Support/Service/WebhookReceiptRepository.php'), 'No legacy webhook receipt repository remains.');
$assert($modules->moduleExists('aculta_portal') && !is_dir(DRUPAL_ROOT . '/modules/custom/aculta_editorial'), 'The only active editorial glue is consolidated in aculta_portal.');
$assert(\Drupal::config('core.extension')->get('module.aculta_editorial') === NULL, 'Active extension config has no aculta_editorial entry.');
$assert(\Drupal::config('core.extension')->get('module.aculta_apoio') === NULL, 'Active extension config has no aculta_apoio entry.');
$assert(!\Drupal::database()->schema()->tableExists('aculta_apoio_webhook'), 'No legacy webhook receipt storage table remains.');
$assert(!\Drupal::database()->schema()->tableExists('aculta_contribution'), 'No legacy contribution storage table remains.');
$support_classes = array_map(static fn(string $file): string => substr($file, strlen(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Support/')), glob(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Support/*/*.php') ?: []);
sort($support_classes);
$expected_support_classes = ['Controller/SupportController.php', 'Form/SettingsForm.php', 'Form/SubscriptionActionForm.php', 'Form/SupportForm.php'];
$assert($support_classes === $expected_support_classes, 'Support contains exactly the approved public, settings and subscription-action forms and the account controller: ' . implode(', ', $support_classes));
$support_config_keys = array_diff(array_keys(\Drupal::config('aculta_portal.support')->getRawData()), ['_core', 'langcode']);
sort($support_config_keys);
$assert($support_config_keys === ['amounts', 'custom_amount', 'intro'], 'Support settings contain exactly the approved keys: editorial intro and the editable donation amounts.');

// These pages configure credentials, anti-bot behavior, OAuth, mail, and legal
// acceptance. All are administrative routes, never generic access-content.
foreach ([
  'entity.key.collection',
  'entity.key_config_override.collection',
  'entity.mailer_transport.collection',
  'social_auth.integrations',
  'social_auth_google.settings_form',
  'captcha_settings',
  'turnstile.admin_settings_form',
  'entity.agreement.collection',
  'entity.commerce_payment_gateway.collection',
] as $sensitive_admin_route) {
  $admin_route = \Drupal::service('router.route_provider')->getRouteByName($sensitive_admin_route);
  $assert(!$access->checkNamedRoute($sensitive_admin_route, [], $anonymous, TRUE)->isAllowed(), 'Sensitive configuration is denied to anonymous users: ' . $sensitive_admin_route);
  $assert(!$access->checkNamedRoute($sensitive_admin_route, [], $authenticated, TRUE)->isAllowed(), 'Sensitive configuration is denied to ordinary authenticated users: ' . $sensitive_admin_route);
  $assert($access->checkNamedRoute($sensitive_admin_route, [], $administrator, TRUE)->isAllowed(), 'Sensitive configuration is available to administrators: ' . $sensitive_admin_route);
  $assert(($admin_route->getRequirements()['_permission'] ?? '') !== 'access content', 'Sensitive config route uses an administrative permission: ' . $sensitive_admin_route);
}

foreach (['aculta_portal.support_form'] as $public_route) {
  foreach ([$anonymous, $authenticated, $administrator] as $account) {
    $assert($access->checkNamedRoute($public_route, [], $account, TRUE)->isAllowed(), 'Public support information route is available: ' . $public_route);
  }
}

$authenticated_role = \Drupal\user\Entity\Role::load('authenticated');
$administrator_role = \Drupal\user\Entity\Role::load('administrator');
$restricted_permissions = [
  'administer aculta support settings',
  'administer aculta apoio',
  'view aculta contributions',
  'administer commerce_payment_gateway',
  'administer commerce_payment',
  'administer keys',
  'administer key configuration overrides',
  'administer CAPTCHA settings',
  'administer social auth',
  'administer social api authentication',
  'administer turnstile',
  'administer agreements',
  'administer agreement types',
  'administer profile types',
];
$assert((bool) $authenticated_role, 'Authenticated role exists.');
$assert((bool) $administrator_role, 'Administrator role exists.');
$assert(array_intersect($restricted_permissions, $authenticated_role->getPermissions()) === [], 'Authenticated role has no restricted platform administration permissions.');
$assert($administrator_role->isAdmin(), 'Administrator role retains administrative status.');
// Exceções aprovadas (CHANGELOG 0.4.0-dev.82): o gerente da loja acessa Formas de apoio, e só isso.
$approved_role_exceptions = ['gerente_loja' => ['administer aculta support settings']];
foreach (\Drupal\user\Entity\Role::loadMultiple() as $role) {
  if (!$role->isAdmin()) {
    $forbidden = array_diff(array_intersect($restricted_permissions, $role->getPermissions()), $approved_role_exceptions[$role->id()] ?? []);
    $assert($forbidden === [], 'Non-administrator role has no listed sensitive platform permissions beyond approved exceptions: ' . $role->id());
  }
}

$profile = \Drupal::entityTypeManager()->getStorage('profile_type')->load('participante');
// Removido de propósito (commit d29e34bf): a hierarquia de papéis substitui o tipo de perfil participante.
$assert(!$profile, 'Participant profile type stays removed (commit d29e34bf).');
// Todo campo de dados pessoais que existir em qualquer tipo de perfil precisa ser privado.
foreach (array_keys(\Drupal::entityTypeManager()->getStorage('profile_type')->loadMultiple()) as $profile_type) {
  foreach (['field_nickname', 'field_first_name', 'field_last_name', 'field_whatsapp', 'field_city', 'field_state', 'field_address'] as $field_name) {
    $field = \Drupal\field\Entity\FieldConfig::loadByName('profile', $profile_type, $field_name);
    if ($field) {
      $assert($field->getThirdPartySetting('profile', 'profile_private') === TRUE, "Personal data field is private: $profile_type.$field_name");
    }
  }
}
$assert(!\Drupal\field\Entity\FieldConfig::loadByName('profile', 'participante', 'field_phone'), 'Legacy participant phone field instance is absent.');
$assert(!\Drupal\field\Entity\FieldStorageConfig::loadByName('profile', 'field_phone'), 'Legacy participant phone field storage is absent.');
$picture_storage = \Drupal::config('field.storage.user.user_picture')->get('settings.uri_scheme');
$assert($picture_storage === 'private', 'User profile photo uses private file storage.');
$assert((bool) \Drupal::entityTypeManager()->getStorage('crop_type')->load('aculta_avatar_1x1'), 'Institutional square crop type exists.');
$assert((bool) \Drupal::entityTypeManager()->getStorage('image_style')->load('aculta_avatar'), 'Institutional avatar image style exists.');

$registration = \Drupal::config('user.settings');
// Public registration is open by decision: visitors create accounts without
// administrative approval, and every account confirms its e-mail through
// email_confirmer (realm aculta_registration), not the Core verify_mail flag
// (docs/portal/EMAIL-CONFIRMATION-POLICY.md). Closed registration (admin_only) is also accepted.
$register_mode = $registration->get('register');
$assert(in_array($register_mode, ['admin_only', 'visitors'], TRUE), 'Public registration mode is one of the reviewed values.');
$assert($registration->get('verify_mail') === FALSE, 'Core verify_mail stays off: registration is confirmed by email_confirmer (EMAIL-CONFIRMATION-POLICY).');
$assert($registration->get('notify.register_no_approval_required') === TRUE, 'Registration needs no administrative approval (decision: e-mail verification only).');

$captcha = \Drupal::config('captcha.settings');
$assert((int) $captcha->get('enable_globally') === 1, 'Turnstile is globally enabled for anonymous forms.');
// Só quem confirmou o e-mail (papel email_confirmed) pula o desafio; logado sem confirmação recebe Turnstile (6049919).
$assert(\Drupal\user\Entity\Role::load('email_confirmed')?->hasPermission('skip CAPTCHA') === TRUE, 'Confirmed-email users can skip the global CAPTCHA challenge.');
$assert(\Drupal\user\Entity\Role::load('authenticated')?->hasPermission('skip CAPTCHA') !== TRUE, 'Authenticated users without confirmed e-mail still receive the CAPTCHA challenge.');
$expected_forms = [
  'user_login_form',
  'user_register_form',
  'user_pass',
  'webform_submission_aculta_contact_add_form',
];
foreach ($expected_forms as $form_id) {
  $point = \Drupal::entityTypeManager()->getStorage('captcha_point')->load($form_id);
  $assert($point && $point->status() && $point->getCaptchaType() === 'turnstile/Turnstile', 'Turnstile is explicitly enabled on approved public form: ' . $form_id);
}
foreach (['node_activity_form', 'node_article_form', 'node_project_form', 'contact_message_personal_form'] as $excluded_form) {
  $point = \Drupal::entityTypeManager()->getStorage('captcha_point')->load($excluded_form);
  $assert(!$point || !$point->status(), 'Turnstile is not attached to excluded/internal form: ' . $excluded_form);
}
// Versionado: chave de produção (turnstile). Em runtime, TurnstileKeyOverride escolhe a chave do ambiente declarado pelo Deployer.
$turnstile_env = \Drupal\aculta_portal\Environment\DeployerEnvironment::current(dirname(DRUPAL_ROOT), 'production');
$assert(\Drupal::service('config.storage.sync')->read('turnstile.settings')['keys'] === 'turnstile', 'Versioned Turnstile key reference is the production Key.');
$assert(\Drupal::config('turnstile.settings')->get('keys') === ($turnstile_env === 'test' ? 'turnstile_test' : 'turnstile'), 'Turnstile Key reference matches the environment declared by the Deployer.');
$assert(\Drupal::config('turnstile.settings')->get('testing_secret_key') === '', 'No Turnstile test secret is saved in configuration.');

$key_storage = \Drupal::entityTypeManager()->getStorage('key');
foreach (['turnstile', 'google_oauth_client_id', 'google_oauth_client_secret'] as $key_id) {
  $key = $key_storage->load($key_id);
  $assert($key && $key->getKeyProvider()->getPluginId() === 'env', 'Secret/public integration reference is environment-backed: ' . $key_id);
}

// ConfigFactory returns effective values after Key Config Override. Inspect
// active storage directly when asserting that credentials are not persisted.
$oauth_storage = \Drupal::service('config.storage');
$oauth_sync_storage = \Drupal::service('config.storage.sync');
$oauth_raw = $oauth_storage->read('social_auth_google.settings') ?: [];
$oauth_sync = $oauth_sync_storage->read('social_auth_google.settings') ?: [];
$assert(trim((string) ($oauth_raw['client_id'] ?? '')) === '' && trim((string) ($oauth_raw['client_secret'] ?? '')) === '', 'Google OAuth credentials are absent from ordinary active configuration.');
$assert(trim((string) ($oauth_sync['client_id'] ?? '')) === '' && trim((string) ($oauth_sync['client_secret'] ?? '')) === '', 'Google OAuth credentials are absent from synchronized configuration.');

$oauth_contract = [
  'google_oauth_client_id' => [
    'environment' => 'GOOGLE_OAUTH_CLIENT_ID',
    'config_item' => 'client_id',
    'override_id' => 'google_client_id',
  ],
  'google_oauth_client_secret' => [
    'environment' => 'GOOGLE_OAUTH_CLIENT_SECRET',
    'config_item' => 'client_secret',
    'override_id' => 'google_client_secret',
  ],
];
$oauth_key_values = [];
foreach ($oauth_contract as $key_id => $contract) {
  $environment_variable = $contract['environment'];
  $key = $key_storage->load($key_id);
  $provider = $key?->getKeyProvider();
  $provider_configuration = $provider?->getConfiguration() ?? [];
  $sync_key = $oauth_sync_storage->read('key.key.' . $key_id) ?: [];
  $assert(
    $key && $provider?->getPluginId() === 'env' && ($provider_configuration['env_variable'] ?? NULL) === $environment_variable,
    'Google OAuth Key uses the expected environment provider: ' . $key_id,
  );
  $assert(
    ($sync_key['key_provider'] ?? NULL) === 'env'
      && ($sync_key['key_provider_settings']['env_variable'] ?? NULL) === $environment_variable
      && !array_key_exists('key_value', $sync_key),
    'Google OAuth Key sync contains only the environment contract: ' . $key_id,
  );
  try {
    $oauth_key_values[$key_id] = $key ? (string) $key->getKeyValue(TRUE) : '';
  }
  catch (\Throwable) {
    $oauth_key_values[$key_id] = '';
  }
  $assert($oauth_key_values[$key_id] !== '', 'Google OAuth Key resolves a runtime value: ' . $key_id);
}

$oauth_overrides = \Drupal::entityTypeManager()->getStorage('key_config_override');
$expected_oauth_overrides = [];
foreach ($oauth_contract as $key_id => $contract) {
  $expected_oauth_overrides[$contract['override_id']] = [$contract['config_item'], $key_id];
}
foreach ($expected_oauth_overrides as $override_id => [$config_item, $key_id]) {
  $override = $oauth_overrides->load($override_id);
  $data = $override?->toArray() ?? [];
  $sync_override = $oauth_sync_storage->read('key.config_override.' . $override_id) ?: [];
  $matches = static function (array $config) use ($config_item, $key_id): bool {
    return ($config['status'] ?? FALSE) === TRUE
      && ($config['config_type'] ?? NULL) === 'system.simple'
      && ($config['config_name'] ?? NULL) === 'social_auth_google.settings'
      && ($config['config_item'] ?? NULL) === $config_item
      && ($config['key_id'] ?? NULL) === $key_id;
  };
  $assert($override && $matches($data) && $matches($sync_override), 'Google OAuth Key Configuration Override is active and exact: ' . $override_id);
}

$oauth_effective = \Drupal::config('social_auth_google.settings');
foreach ($oauth_contract as $key_id => $contract) {
  $config_item = $contract['config_item'];
  $key_value = $oauth_key_values[$key_id];
  $effective_value = (string) ($oauth_effective->get($config_item) ?? '');
  $assert($effective_value !== '', 'Google OAuth effective setting is available: ' . $config_item);
  $assert($key_value !== '' && hash_equals($key_value, $effective_value), 'Google OAuth effective setting matches its Key value: ' . $config_item);
}

$support_settings = \Drupal::config('aculta_portal.support')->getRawData();
foreach (['access_token', 'client_secret', 'webhook_secret', 'pix_key', 'pix_copy_paste'] as $forbidden) {
  $assert(!array_key_exists($forbidden, $support_settings), 'No payment credential or direct Pix data exists in support settings: ' . $forbidden);
}
$doacao = \Drupal\commerce_checkout\Entity\CheckoutFlow::load('doacao');
$assert($doacao && $doacao->getPluginId() === 'fluxo_doacao', 'Donation Flow has a dedicated checkout flow.');
$donation_flow_configuration = $doacao ? $doacao->getPlugin()->getConfiguration() : [];
$assert(($donation_flow_configuration['panes']['login']['allow_guest_checkout'] ?? FALSE) === TRUE, 'Donation checkout supports guest users.');
$assert(!isset($donation_flow_configuration['panes']['shipping']), 'Donation checkout does not use shipping.');
$support_controller_source = file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Support/Controller/SupportController.php');
$assert(str_contains($support_controller_source, "loadByProperties(['uid' => \$uid])") && str_contains($support_controller_source, "bundle() === 'donation'"), 'My Support reads only the current user\'s Commerce Donation orders.');
$support_form = \Drupal::formBuilder()->getForm(\Drupal\aculta_portal\Support\Form\SupportForm::class);
$support_html = (string) \Drupal::service('renderer')->renderRoot($support_form);
// O aviso de indisponibilidade acompanha o estado real do gateway: desligado → aviso e sem /apoiar; ligado → sem aviso.
$support_notice = str_contains($support_html, 'apoio financeiro está temporariamente indisponível');
$gateway_enabled = (bool) \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->load('mercado_pago')?->status();
$assert($gateway_enabled ? !$support_notice : ($support_notice && !str_contains($support_html, '/apoiar')), 'The public support page matches the Mercado Pago gateway state (notice only while disabled).');

// Exercise the amount guard and Form API number minimum with values kept only
// in this CLI process. No Commerce entity or provider request is created.
foreach ([['single', 'custom_amount', ''], ['single', 'custom_amount', '0'], ['single', 'custom_amount', '-1'], ['recurring', 'level_1', '20']] as [$gift_type, $choice, $amount]) {
  $state = new \Drupal\Core\Form\FormState();
  $state->clearErrors();
  $state->setValue(['commerce_donation_pane', 'field_gift_type'], [['value' => $gift_type]]);
  $state->setValue(['commerce_donation_pane', 'field_donation_amount'], [['donation_level' => ['value' => $choice, 'amount' => $amount]]]);
  $form = [];
  \Drupal::service('aculta_portal.form_callbacks')->validateDonationAmount($form, $state);
  $assert($state->hasAnyErrors(), 'Donation form rejects empty, zero, negative, or recurring input.');
}
$valid_state = new \Drupal\Core\Form\FormState();
$valid_state->clearErrors();
$valid_state->setValue(['commerce_donation_pane', 'field_gift_type'], [['value' => 'single']]);
$valid_state->setValue(['commerce_donation_pane', 'field_donation_amount'], [['donation_level' => ['value' => 'custom_amount', 'amount' => '20']]]);
$valid_form = [];
\Drupal::service('aculta_portal.form_callbacks')->validateDonationAmount($valid_form, $valid_state);
$assert(!$valid_state->hasAnyErrors(), 'Donation form accepts a positive custom amount.');
$number_state = new \Drupal\Core\Form\FormState();
$number_state->clearErrors();
$number_element = ['#parents' => ['donation_amount'], '#value' => '0', '#min' => 5, '#step' => 'any', '#title' => 'Valor'];
\Drupal\Core\Render\Element\Number::validateNumber($number_element, $number_state, $valid_form);
$assert($number_state->hasAnyErrors(), 'Donation widget Form API rejects values below its R$5 minimum server-side.');
$assert(!isset($number_element['#max']), 'Donation widget has no configured maximum amount.');

$mp_variables = ['MERCADOPAGO_TEST_PUBLIC_KEY', 'MERCADOPAGO_TEST_ACCESS_TOKEN', 'MERCADOPAGO_PRODUCTION_PUBLIC_KEY', 'MERCADOPAGO_PRODUCTION_ACCESS_TOKEN'];
$previous_mp = [];
foreach ($mp_variables as $variable) {
  $previous_mp[$variable] = getenv($variable);
}
$mp_environment = \Drupal\aculta_portal\Commerce\MercadoPago\MercadoPagoCredentials::currentEnvironment(dirname(\Drupal::root()));
$mp_token_field = $mp_environment === 'test' ? 'access_token_test' : 'access_token_prod';
$gateway_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
$fake_public_key = 'TEST_MP_PUBLIC_' . bin2hex(random_bytes(12));
$fake_access_token = 'TEST_MP_ACCESS_' . bin2hex(random_bytes(24));
// Ambos os pares recebem valores falsos; o gateway deve usar o do ambiente declarado.
putenv('MERCADOPAGO_TEST_PUBLIC_KEY=' . $fake_public_key);
putenv('MERCADOPAGO_TEST_ACCESS_TOKEN=' . $fake_access_token);
putenv('MERCADOPAGO_PRODUCTION_PUBLIC_KEY=' . $fake_public_key);
putenv('MERCADOPAGO_PRODUCTION_ACCESS_TOKEN=' . $fake_access_token);
$mp_override = \Drupal::service('aculta_portal.mercado_pago_environment_override')->loadOverrides(['commerce_payment.commerce_payment_gateway.mercado_pago']);
$assert(($mp_override['commerce_payment.commerce_payment_gateway.mercado_pago']['configuration'][$mp_token_field] ?? '') === $fake_access_token, 'Runtime credentials map to the gateway plugin in memory for the declared environment.');
\Drupal::configFactory()->reset('commerce_payment.commerce_payment_gateway.mercado_pago');
$gateway_storage->resetCache(['mercado_pago']);
$runtime_gateway = \Drupal\commerce_payment\Entity\PaymentGateway::load('mercado_pago');
$assert(($runtime_gateway->getPluginConfiguration()[$mp_token_field] ?? '') === $fake_access_token, 'Commerce gateway receives the environment override at runtime.');
$admin_form_html = '';
try {
  \Drupal::currentUser()->setAccount(\Drupal\user\Entity\User::load(1));
  $gateway_for_form = $runtime_gateway;
  $gateway_form = \Drupal::service('entity.form_builder')->getForm($gateway_for_form, 'edit');
  $admin_form_html = (string) \Drupal::service('renderer')->renderRoot($gateway_form);
}
finally {
  foreach ($previous_mp as $variable => $previous) {
    putenv($previous === FALSE ? $variable : $variable . '=' . $previous);
  }
  \Drupal::configFactory()->reset('commerce_payment.commerce_payment_gateway.mercado_pago');
  $gateway_storage->resetCache(['mercado_pago']);
  \Drupal::currentUser()->setAccount(new \Drupal\Core\Session\AnonymousUserSession());
}
$assert(!str_contains($admin_form_html, $fake_access_token), 'Gateway admin HTML never reveals the runtime Access Token.');
$assert(($gateway_storage->loadMultipleOverrideFree(['mercado_pago'])['mercado_pago']->getPluginConfiguration()[$mp_token_field] ?? '') === '', 'Fake runtime credentials were not persisted.');
$gateway = \Drupal\commerce_payment\Entity\PaymentGateway::load('mercado_pago');
$assert((bool) $gateway, 'The Mercado Pago gateway config entity exists.');
// Regra de ambiente do deployer: o gateway fica ligado somente quando o ambiente tem o access token (variável de ambiente).
$mp_token_env = $mp_environment === 'test' ? 'MERCADOPAGO_TEST_ACCESS_TOKEN' : 'MERCADOPAGO_PRODUCTION_ACCESS_TOKEN';
$mp_credential_present = (string) getenv($mp_token_env) !== '';
$assert($gateway->status() === $mp_credential_present, 'The Mercado Pago gateway is enabled exactly when this environment has its access token.');
$assert($gateway->getPluginId() === 'aculta_mercadopago_checkout_pro', 'The gateway uses the installed Mercado Pago Checkout Pro plugin.');
$assert(count($gateway->getConditions()) === 3, 'The gateway is constrained by Store, order type, and currency.');
// Configuração persistida (sem os overrides de ambiente, que trazem a credencial em runtime).
$gateway_config = \Drupal::config('commerce_payment.commerce_payment_gateway.mercado_pago')->getRawData()['configuration'] ?? [];
foreach (['access_token_test', 'access_token_prod'] as $secret_key) {
  $assert(($gateway_config[$secret_key] ?? '') === '', 'Gateway secret field is empty in persisted active configuration: ' . $secret_key);
}
$gateway_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
$stored_gateway = $gateway_storage->loadMultipleOverrideFree(['mercado_pago'])['mercado_pago'];
$assert(($stored_gateway->getPluginConfiguration()['access_token_test'] ?? '') === '', 'Gateway runtime override is not persisted in active config.');
$assert(!$gateway->access('update', $administrator, TRUE)->isAllowed(), 'The contrib gateway edit form is locked to prevent credential persistence.');
$assert(\Drupal::moduleHandler()->moduleExists('aculta_donation'), 'ACULTA Donation (port do fluxo de doação) is enabled.');
$assert(!class_exists(\Composer\InstalledVersions::class) || !\Composer\InstalledVersions::isInstalled('drupal/commerce_donation_flow'), 'The contrib donation flow package is no longer installed (replaced by aculta_donation).');
$gift_storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('commerce_order_item', 'field_gift_type');
$assert($gift_storage && array_keys($gift_storage->getSetting('allowed_values')) === ['single'], 'Only one-time support is allowed by the Donation Flow gift type.');
$donation_form_display = \Drupal::config('core.entity_form_display.commerce_order_item.donation.donation');
$levels = $donation_form_display->get('content.field_donation_amount.settings.levels.single') ?? [];
$assert(($levels['level_1'] ?? NULL) === '20' && ($levels['level_2'] ?? NULL) === '50' && ($levels['level_3'] ?? NULL) === '100', 'Donation Flow presets are R$20, R$50, and R$100.');
$assert(!\Drupal::moduleHandler()->moduleExists('commerce_recurring'), 'Commerce Recurring is not enabled.');
$store = \Drupal\commerce_store\Entity\Store::load(1);
$assert((bool) $store, 'One institutional Commerce Store is provisioned locally.');
$assert($store->isDefault() && $store->getDefaultCurrencyCode() === 'BRL', 'The institutional Store is default and uses BRL.');
$assert($store->uuid() === '32f75ad2-5d45-48bb-898e-d657d47d04f8', 'The Store has the stable UUID used by gateway conditions.');
$assert(count(\Drupal::entityTypeManager()->getStorage('commerce_store')->loadMultiple()) === 1, 'There is exactly one Commerce Store.');
$assert(count(\Drupal::entityTypeManager()->getStorage('commerce_order')->loadMultiple()) === 0, 'No test or real Commerce orders remain.');
$assert(count(\Drupal::entityTypeManager()->getStorage('commerce_payment')->loadMultiple()) === 0, 'No Commerce payments exist.');

$manifest_path = dirname(__DIR__) . '/scripts/institution/PORTAL-ACCOUNT-CONFIG-MANIFEST.json';
$manifest = json_decode(file_get_contents($manifest_path), TRUE, 512, JSON_THROW_ON_ERROR);
$active_storage = \Drupal::service('config.storage');
$canonical_sync_path = realpath(dirname(DRUPAL_ROOT) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'sync');
$effective_sync_path = realpath(\Drupal\Core\Site\Settings::get('config_sync_directory'));
$assert($canonical_sync_path !== FALSE && $effective_sync_path === $canonical_sync_path, 'Drupal Configuration Sync points at the repository canonical config/sync directory.');
$sync_storage = \Drupal::service('config.storage.sync');
// user_registrationpassword was removed (commit b4b4389); its three objects are no longer in the manifest.
// M2 do e-mail: smtp.settings saiu; entraram mailer_transport.mailer_transport.smtp2go e mailer_transport.settings (+1 líquido).
$assert(count($manifest['configs']) === 57, 'The reviewed account manifest contains 57 intentionally selected config objects.');
$assert(in_array('field.storage.user.user_picture', $manifest['configs'], TRUE), 'Private User picture storage is included in the reviewed manifest.');
$sort_recursive = static function (array &$data) use (&$sort_recursive): void {
  ksort($data);
  foreach ($data as &$value) {
    if (is_array($value)) {
      $sort_recursive($value);
    }
  }
};
// Arquivos dependentes de ambiente são comparados pelo deployer (environment_bound no deploy.json).
// Diferenças conhecidas do runtime de teste estão na linha de base DT-O10 e só podem diminuir.
$deploy = json_decode((string) file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/modules/aculta_deployer/config/deploy.json'), TRUE);
$environment_bound = array_map(static fn(array $e): string => basename($e['file'], '.yml'), $deploy['environment_bound'] ?? []);
$drift_baseline = json_decode((string) file_get_contents(DRUPAL_ROOT . '/../scripts/institution/CONFIG-DRIFT-BASELINE.json'), TRUE)['names'] ?? [];
$known_reviewed_drift = $drift_baseline;
foreach ($manifest['configs'] as $name) {
  if (in_array($name, $environment_bound, TRUE)) continue;
  if (in_array($name, $known_reviewed_drift, TRUE)) continue;
  $active_data = $active_storage->read($name);
  $sync_data = $sync_storage->read($name);
  $sort_recursive($active_data);
  $sort_recursive($sync_data);
  $assert($active_data === $sync_data, 'Reviewed active configuration matches config/sync: ' . $name);
}
$active_names = $active_storage->listAll();
$sync_names = $sync_storage->listAll();
sort($active_names);
sort($sync_names);
$name_set_diff = array_values(array_unique(array_merge(array_diff($active_names, $sync_names), array_diff($sync_names, $active_names))));
$name_set_diff = array_values(array_filter($name_set_diff, static fn(string $n): bool => !in_array($n, $environment_bound, TRUE) && !in_array($n, $drift_baseline, TRUE)));
$assert(!$name_set_diff, 'The canonical config/sync object set matches the active set, except the DT-O10 baseline. Novas: ' . implode(', ', $name_set_diff));
$assert(count($active_names) >= 651, 'The complete config contains the previous baseline plus approved account configuration.');
foreach ($active_names as $name) {
  if (in_array($name, $environment_bound, TRUE) || in_array($name, $drift_baseline, TRUE)) continue;
  $active_data = $active_storage->read($name);
  $sync_data = $sync_storage->read($name);
  $sort_recursive($active_data);
  $sort_recursive($sync_data);
  $assert($active_data === $sync_data, 'Full active configuration matches config/sync: ' . $name);
}
$config_inventory_path = dirname(__DIR__) . '/scripts/institution/PORTAL-CONFIG-DIFF-INVENTORY.json';
$config_inventory = json_decode(file_get_contents($config_inventory_path), TRUE, 512, JSON_THROW_ON_ERROR);
$assert(($config_inventory['last_full_export']['differences'] ?? NULL) === 0, 'The full config inventory records zero final differences.');
$assert(($config_inventory['portal_account_manifest']['object_count'] ?? NULL) === 59, 'The account manifest remains an audit-only subset.');
$suspicious_values = [];
$inspect_secret_values = static function (mixed $value, string $path, string $file) use (&$inspect_secret_values, &$suspicious_values): void {
  if (!is_array($value)) {
    return;
  }
  foreach ($value as $key => $child) {
    $child_path = $path === '' ? (string) $key : $path . '.' . $key;
    if (preg_match('/(?:^|_)(?:access_token|client_secret|webhook_secret|smtp_password|secret_key|private_key)$/i', (string) $key) && is_scalar($child) && trim((string) $child) !== '') {
      $suspicious_values[] = $file . ':' . $child_path;
    }
    $inspect_secret_values($child, $child_path, $file);
  }
};
$sync_path = realpath($canonical_sync_path);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sync_path, FilesystemIterator::SKIP_DOTS)) as $config_file) {
  if ($config_file->isFile() && in_array(strtolower($config_file->getExtension()), ['yml', 'yaml'], TRUE)) {
    $parsed = \Symfony\Component\Yaml\Yaml::parseFile($config_file->getPathname());
    $inspect_secret_values($parsed, '', str_replace($sync_path . DIRECTORY_SEPARATOR, '', $config_file->getPathname()));
  }
}
$assert($suspicious_values === [], 'Canonical config/sync contains no non-empty sensitive credential values' . ($suspicious_values ? ': ' . implode(', ', $suspicious_values) : '.') );
$dependency_scan = static function (mixed $value) use (&$dependency_scan): bool {
  if (!is_array($value)) {
    return is_string($value) && str_contains($value, 'aculta_apoio');
  }
  foreach ($value as $item) {
    if ($dependency_scan($item)) {
      return TRUE;
    }
  }
  return FALSE;
};
foreach ($manifest['configs'] as $name) {
  $data = $active_storage->read($name);
  $assert(!$dependency_scan($data), 'Reviewed config has no dependency on retired support modules: ' . $name);
  foreach ($data['dependencies']['module'] ?? [] as $dependency) {
    $assert(\Drupal::moduleHandler()->moduleExists($dependency), 'Reviewed config dependency is installed: ' . $name . ' → ' . $dependency);
  }
}
$assert(!\Drupal::keyValue('system.schema')->get('aculta_apoio'), 'No stale schema version remains for standalone aculta_apoio.');
$assert(!\Drupal::keyValue('system.schema')->get('aculta_editorial'), 'No stale schema version remains for consolidated aculta_editorial.');
$commerce_address = \Drupal\field\Entity\FieldConfig::loadByName('profile', 'customer', 'address');
$assert((bool) $commerce_address && $commerce_address->getFieldStorageDefinition()->id() === 'profile.address', 'Address storage profile.address remains attached to the Commerce customer profile.');
$assert((bool) \Drupal::entityTypeManager()->getStorage('field_storage_config')->load('profile.field_address'), 'Participant portal address uses its separate field_address storage.');

$support_code = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Support', FilesystemIterator::SKIP_DOTS));
$payment_processor_markers = 0;
foreach ($support_code as $file) {
  if ($file->isFile() && $file->getExtension() === 'php') {
    $source = file_get_contents($file->getPathname());
    foreach (['X-Idempotency-Key', 'SITE_VERIFY_URL', 'createPreapproval', 'createPreference', 'createPayment(', 'capturePayment(', 'refundPayment(', 'GuzzleHttp\\Client', 'http_client_factory'] as $marker) {
      if (stripos($source, $marker) !== FALSE) {
        $payment_processor_markers++;
      }
    }
  }
}
$assert($payment_processor_markers === 0, 'Support contains no custom payment transport, capture/refund, token, or API client implementation.');
$has_webhook_route = FALSE;
foreach (\Drupal::service('router.route_provider')->getAllRoutes() as $route_name => $route_definition) {
  if (str_starts_with((string) $route_name, 'aculta_portal.') && str_contains((string) $route_name, 'webhook')) {
    $has_webhook_route = TRUE;
  }
}
$assert(!$has_webhook_route, 'No public custom webhook route exists in aculta_portal.');
$assert($sync_storage->read('aculta_apoio.settings') === FALSE, 'Retired standalone support configuration is absent from config/sync.');
$assert(\Drupal::service('config.storage')->listAll('aculta_apoio.') === [], 'Active config storage contains no retired aculta_apoio objects.');
$assert($sync_storage->listAll('aculta_apoio.') === [], 'Canonical config/sync contains no retired aculta_apoio objects.');
$assert($sync_storage->read('field.field.profile.participante.field_phone') === FALSE, 'Legacy participant phone field config is absent from config/sync.');
$assert($sync_storage->read('field.storage.profile.field_phone') === FALSE, 'Legacy participant phone storage config is absent from config/sync.');
$assert(($sync_storage->read('core.extension')['module']['aculta_editorial'] ?? NULL) === NULL, 'Consolidated editorial module is absent from exported extensions.');
$assert($sync_storage->read('aculta_portal.support')['intro'] === \Drupal::config('aculta_portal.support')->get('intro'), 'Support editorial text is reconciled in config/sync.');
$assert($sync_storage->read('commerce_payment.commerce_payment_gateway.mercado_pago') !== FALSE, 'The disabled gateway config is in the complete sync set.');
$assert($sync_storage->read('commerce_store.commerce_store_type.online') !== FALSE, 'The Store type config is in the complete sync set.');
$assert($sync_storage->read('commerce_price.commerce_currency.BRL') !== FALSE, 'BRL currency config is in the complete sync set.');
$assert($sync_storage->listAll('commerce_store.commerce_store.') === [], 'The Store content entity is not mistaken for configuration.');
$assert(($sync_storage->read('core.extension')['module']['aculta_apoio'] ?? NULL) === NULL, 'Retired standalone support module is absent from exported extensions.');

// Exercise the official SDK signature validator and the pre-controller guard
// with synthetic values only. The test signer below is not production code.
$webhook_key = \Drupal::service('key.repository')->getKey('mercadopago_webhook_secret');
$assert($webhook_key && $webhook_key->getKeyProvider()->getPluginId() === 'env', 'Webhook secret is referenced through the Key Environment provider.');
$assert(($webhook_key->getKeyProvider()->getConfiguration()['env_variable'] ?? NULL) === 'MERCADOPAGO_WEBHOOK_SECRET', 'Webhook Key points to the dedicated signing-secret environment variable.');
$assert(class_exists(\MercadoPago\Webhook\WebhookSignatureValidator::class) && class_exists(\MercadoPago\Exceptions\InvalidWebhookSignatureException::class), 'The installed Mercado Pago SDK provides its official webhook validator and exception.');
$assert(\Composer\InstalledVersions::getPrettyVersion('mercadopago/dx-php') === '3.16.0', 'The audited Mercado Pago SDK version is 3.16.0.');
$assert($gateway->status() === $mp_credential_present, 'The Mercado Pago gateway state matches the environment access token before webhook tests.');

$fake_webhook_secret = 'test_' . bin2hex(random_bytes(32));
$webhook_request_id = 'local-' . bin2hex(random_bytes(12));
$webhook_data_id = (string) random_int(100000, 999999999);
$sign_test_webhook = static function (string $data_id, string $request_id, string $secret): string {
  $timestamp = (string) time();
  $manifest = 'id:' . strtolower($data_id) . ';request-id:' . $request_id . ';ts:' . $timestamp . ';';
  return 'ts=' . $timestamp . ',v1=' . hash_hmac('sha256', $manifest, $secret);
};
$make_webhook_request = static function (array $query_values, array $body_values, ?string $signature, ?string $request_id, string $method = 'POST'): \Symfony\Component\HttpFoundation\Request {
  $query = http_build_query($query_values, '', '&', PHP_QUERY_RFC3986);
  $request = \Symfony\Component\HttpFoundation\Request::create(
    'http://default/payment/notify/mercado_pago' . ($query !== '' ? '?' . $query : ''),
    $method,
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json'],
    json_encode($body_values, JSON_THROW_ON_ERROR),
  );
  if ($signature !== NULL) {
    $request->headers->set('x-signature', $signature);
  }
  if ($request_id !== NULL) {
    $request->headers->set('x-request-id', $request_id);
  }
  return $request;
};
$webhook_guard = \Drupal::service('aculta_portal.mercado_pago_webhook_guard');
$valid_signature = $sign_test_webhook($webhook_data_id, $webhook_request_id, $fake_webhook_secret);

$valid_request = $make_webhook_request(
  ['data.id' => $webhook_data_id, 'type' => 'payment'],
  ['type' => 'payment', 'data' => ['id' => $webhook_data_id]],
  $valid_signature,
  $webhook_request_id,
);
$valid_response = $webhook_guard->validateAndNormalize($valid_request, TRUE, $fake_webhook_secret);
$assert($valid_response === NULL, 'A valid modern signed payment notification continues to Commerce.');
$assert($valid_request->query->all() === ['topic' => 'payment', 'id' => $webhook_data_id], 'Only the exact signed data.id is normalized to the legacy keys expected by contrib.');

$invalid_signature = substr($valid_signature, 0, -1) . (str_ends_with($valid_signature, '0') ? '1' : '0');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $invalid_signature, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 401, 'An invalid webhook signature is rejected with HTTP 401.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], NULL, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 401, 'A missing x-signature is rejected.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $valid_signature, NULL), TRUE, $fake_webhook_secret)?->getStatusCode() === 401, 'A missing x-request-id is rejected.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['type' => 'payment'], ['type' => 'payment', 'data' => []], $valid_signature, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 400, 'A missing data.id is rejected.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => '12bad', 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => '12bad']], $valid_signature, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 400, 'A malformed/non-numeric identifier unsupported by this contrib is rejected.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => (string) ((int) $webhook_data_id + 1)]], $valid_signature, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 400, 'Conflicting query and body data.id values are rejected before validation or processing.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'id' => '999999', 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $valid_signature, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 400, 'A legacy/contrib ID conflicting with the signature ID is rejected.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'refund', 'data' => ['id' => $webhook_data_id]], $valid_signature, $webhook_request_id), TRUE, $fake_webhook_secret)?->getStatusCode() === 400, 'Conflicting event types are rejected.');

$unsupported_data_id = (string) random_int(100000, 999999999);
$unsupported_request_id = 'local-' . bin2hex(random_bytes(12));
$unsupported_signature = $sign_test_webhook($unsupported_data_id, $unsupported_request_id, $fake_webhook_secret);
$unsupported_response = $webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $unsupported_data_id, 'type' => 'refund'], ['type' => 'refund', 'data' => ['id' => $unsupported_data_id]], $unsupported_signature, $unsupported_request_id), TRUE, $fake_webhook_secret);
$assert($unsupported_response?->getStatusCode() === 200, 'A signed unsupported event is acknowledged without entering the contrib handler.');
$legacy_response = $webhook_guard->validateAndNormalize($make_webhook_request(['topic' => 'payment', 'id' => $webhook_data_id], [], NULL, NULL), TRUE, $fake_webhook_secret);
$assert($legacy_response?->getStatusCode() === 401, 'Unsigned legacy IPN topic/id notification is rejected with HTTP 401 (received ' . ($legacy_response?->getStatusCode() ?? 'continue') . ').');
$signed_legacy_signature = $sign_test_webhook($webhook_data_id, $webhook_request_id, $fake_webhook_secret);
$signed_legacy_request = $make_webhook_request(['topic' => 'payment', 'id' => $webhook_data_id], [], $signed_legacy_signature, $webhook_request_id);
$assert($webhook_guard->validateAndNormalize($signed_legacy_request, TRUE, $fake_webhook_secret)?->getStatusCode() === 400, 'Legacy topic/id payload is rejected even when signed because it lacks the modern supported event structure.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $valid_signature, $webhook_request_id), FALSE, $fake_webhook_secret)?->getStatusCode() === 503, 'A disabled gateway fails closed before webhook processing.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $valid_signature, $webhook_request_id), TRUE, NULL)?->getStatusCode() === 503, 'A missing environment secret fails closed.');
$assert($webhook_guard->validateAndNormalize($make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $valid_signature, $webhook_request_id, 'GET'), TRUE, $fake_webhook_secret)?->getStatusCode() === 405, 'GET is rejected on the payment notification route.');

$subscriber = \Drupal::service('aculta_portal.mercado_pago_webhook_subscriber');
$kernel = \Drupal::service('http_kernel');
$other_gateway_request = $make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], NULL, NULL);
$other_gateway_request->attributes->set('_route', 'commerce_payment.notify');
$other_gateway_request->attributes->set('commerce_payment_gateway', 'another_gateway');
$other_gateway_event = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, $other_gateway_request, \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
$subscriber->onKernelRequest($other_gateway_event);
$assert(!$other_gateway_event->hasResponse() && $other_gateway_request->query->has('data_id'), 'Other Commerce gateways pass through untouched.');

// O teste precisa do gateway desligado. Desliga-se só durante o teste, sem chamar a API externa, e restaura-se o estado original.
$mp_config = \Drupal::configFactory()->getEditable('commerce_payment.commerce_payment_gateway.mercado_pago');
$mp_original_status = $mp_config->get('status');
try {
  $mp_config->set('status', FALSE)->save();
  \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->resetCache(['mercado_pago']);
  $disabled_gateway_request = $make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], $valid_signature, $webhook_request_id);
  $disabled_gateway_request->attributes->set('_route', 'commerce_payment.notify');
  $disabled_gateway_request->attributes->set('commerce_payment_gateway', 'mercado_pago');
  $disabled_gateway_event = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, $disabled_gateway_request, \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
  $subscriber->onKernelRequest($disabled_gateway_event);
  $assert($disabled_gateway_event->hasResponse() && $disabled_gateway_event->getResponse()->getStatusCode() === 503, 'The live native-route subscriber blocks notify while the Mercado Pago gateway entity is disabled.');
}
finally {
  \Drupal::configFactory()->getEditable('commerce_payment.commerce_payment_gateway.mercado_pago')->set('status', $mp_original_status)->save();
  \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->resetCache(['mercado_pago']);
}
$assert($subscriber::getSubscribedEvents()[\Symfony\Component\HttpKernel\KernelEvents::REQUEST][1] === 29, 'Webhook guard runs after Drupal routing at the audited request event priority.');

$other_route_request = $make_webhook_request(['data.id' => $webhook_data_id, 'type' => 'payment'], ['type' => 'payment', 'data' => ['id' => $webhook_data_id]], NULL, NULL);
$other_route_request->attributes->set('_route', 'commerce_payment.notify_other');
$other_route_request->attributes->set('commerce_payment_gateway', 'mercado_pago');
$other_route_event = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, $other_route_request, \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
$subscriber->onKernelRequest($other_route_event);
$assert(!$other_route_event->hasResponse(), 'The guard ignores all routes except commerce_payment.notify.');

$synthetic_webhook_test_secret_previous = getenv('MERCADOPAGO_WEBHOOK_SECRET');
putenv('MERCADOPAGO_WEBHOOK_SECRET=' . $fake_webhook_secret);
try {
  $assert($webhook_key->getKeyValue(TRUE) === $fake_webhook_secret, 'Key resolves a synthetic webhook secret from the expected environment variable at runtime.');
}
finally {
  $webhook_key->getKeyValue(TRUE);
  putenv($synthetic_webhook_test_secret_previous === FALSE ? 'MERCADOPAGO_WEBHOOK_SECRET' : 'MERCADOPAGO_WEBHOOK_SECRET=' . $synthetic_webhook_test_secret_previous);
  $webhook_key->getKeyValue(TRUE);
}

$assert($sync_storage->read('key.key.mercadopago_webhook_secret')['key_provider_settings']['env_variable'] === 'MERCADOPAGO_WEBHOOK_SECRET', 'Webhook secret Key export contains only the environment variable contract.');
$assert($sync_storage->read('key.key.mercadopago_webhook_secret')['key_provider'] === 'env', 'Webhook signing secret is never configured with a value provider.');
$webhook_source = file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Commerce/MercadoPago/WebhookGuard.php')
  . file_get_contents(DRUPAL_ROOT . '/modules/custom/aculta_portal/src/Commerce/MercadoPago/WebhookEventSubscriber.php');
foreach (['PreferenceClient', 'PaymentClient', 'MerchantOrderClient', 'PaymentRefundClient', 'http_client_factory', 'GuzzleHttp\\Client'] as $forbidden_webhook_client) {
  $assert(!str_contains($webhook_source, $forbidden_webhook_client), 'Webhook guard has no custom payment/API client dependency: ' . $forbidden_webhook_client);
}

echo count($checks) . " read-only local account/security/config checks passed.\n";
