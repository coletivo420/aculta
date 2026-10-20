<?php

/**
 * Local-only Email Confirmer / Change Mail Page integration check.
 * Uses temporary users and a mail collector double; removes all fixtures.
 */
if (!in_array(\Drupal::request()->getHost(), ['localhost', '127.0.0.1', 'default'], TRUE)) {
  throw new RuntimeException('This test is local-only.');
}

$container = \Drupal::getContainer();
$mail_manager = $container->get('plugin.manager.mail');
$sent_to = [];
$container->set('plugin.manager.mail', new class($sent_to) {
  public array $recipients = [];
  public function __construct(array &$recipients) { $this->recipients = &$recipients; }
  public function mail(...$arguments): array {
    $this->recipients[] = $arguments[2] ?? '';
    return ['result' => TRUE];
  }
});

$user_storage = \Drupal::entityTypeManager()->getStorage('user');
$confirmation_storage = \Drupal::entityTypeManager()->getStorage('aculta_mail_confirmer');
$switcher = \Drupal::service('account_switcher');
$password = bin2hex(random_bytes(20));
$suffix = bin2hex(random_bytes(8));
$old_email = 'old-' . $suffix . '@example.invalid';
$new_email = 'new-' . $suffix . '@example.invalid';
$duplicate_email = 'duplicate-' . $suffix . '@example.invalid';
$user = NULL;
$other = NULL;
$confirmation_ids = [];
$switch_depth = 0;
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
  $checks++;
};

try {
  $user = $user_storage->create([
    'name' => 'phase6-' . $suffix,
    'mail' => $old_email,
    'pass' => $password,
    'status' => 1,
    'roles' => ['authenticated'],
  ]);
  $user->save();
  $other = $user_storage->create([
    'name' => 'phase6-other-' . $suffix,
    'mail' => $duplicate_email,
    'pass' => bin2hex(random_bytes(20)),
    'status' => 1,
    'roles' => ['authenticated'],
  ]);
  $other->save();
  $switcher->switchTo($user);

  // Build the account forms on their actual Portal route contexts. The Core
  // User entity's form operation is "default"; the generic "edit" operation
  // does not exist for this entity type.
  $controller = \Drupal\aculta_portal\Controller\PortalController::create($container);
  $request_stack = $container->get('request_stack');
  $route_provider = $container->get('router.route_provider');
  foreach ([
    'aculta_portal.dashboard' => static function () use ($controller, $assert): void {
      $page = $controller->dashboard();
      $assert(($page['identity']['photo_editor']['#theme'] ?? NULL) === 'aculta_portal_photo_editor', 'The account overview owns the accessible profile-photo dialog.') ;
      $assert(($page['identity']['photo_editor']['#photo_form']['#form_id'] ?? NULL) === 'user_form', 'The overview builds the Drupal Core User form for photo updates.');
    },
    'aculta_portal.my_data' => static function () use ($controller, $assert): void {
      $page = $controller->myData();
      $assert(($page['content']['form']['#form_id'] ?? NULL) === 'profile_participante_edit_form', 'Meus Dados builds the participant Profile form.');
      $assert(!isset($page['content']['photo_form']) && !isset($page['content']['form']['field_address']) && !isset($page['content']['form']['field_city']) && !isset($page['content']['form']['field_state']), 'Basic information contains no photo or address fields.');
    },
    'aculta_portal.my_data_address' => static function () use ($controller, $assert): void {
      $page = $controller->address();
      $form = $page['content']['form'];
      $assert(($form['#form_id'] ?? NULL) === 'profile_participante_edit_form' && isset($form['field_address']), 'The Address page uses the participant Profile Address field.');
      $assert(!isset($form['field_city']) && !isset($form['field_state']), 'The Address page uses Commerce Address locality/region fields, without duplicate City/UF fields.');
      $assert(($form['field_address']['widget'][0]['address']['#default_value']['country_code'] ?? NULL) === 'BR', 'Brazil is selected as the default country for the optional participant address.');
      $assert(($form['field_address']['widget'][0]['address']['#default_value']['locality'] ?? NULL) === '', 'Address locality uses the native Commerce Address property.');
    },
    'aculta_portal.security' => static function () use ($controller, $assert): void {
      $page = $controller->security();
      $assert(($page['content']['password_section']['change_form']['#form_id'] ?? NULL) === 'user_form', 'Segurança builds Drupal Core User form with the supported default operation.');
    },
  ] as $route_name => $build_page) {
    $route_request = \Symfony\Component\HttpFoundation\Request::create($route_provider->getRouteByName($route_name)->getPath());
    $route_request->attributes->set('_route', $route_name);
    $route_request->attributes->set('_route_object', $route_provider->getRouteByName($route_name));
    $route_request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
    $request_stack->push($route_request);
    try {
      $build_page();
    }
    finally {
      $request_stack->pop();
    }
  }

  $login_state = new \Drupal\Core\Form\FormState();
  $login_state->setValue('name', $user->getAccountName());
  $assert(login_emailusername_user_login_validate([], $login_state), 'The login form accepts a valid username.');
  $login_state = new \Drupal\Core\Form\FormState();
  $login_state->setValue('name', $old_email);
  $assert(login_emailusername_user_login_validate([], $login_state) && $login_state->getValue('name') === $user->getAccountName(), 'The login form resolves a valid email to the Drupal username.');
  $login_state = new \Drupal\Core\Form\FormState();
  $login_state->setValue('name', 'missing-' . $suffix . '@example.invalid');
  $assert(!login_emailusername_user_login_validate([], $login_state), 'An unknown email is rejected by the login form validator.');
  $login_state = new \Drupal\Core\Form\FormState();
  $login_state->setValue('name', 'missing-user-' . $suffix);
  $assert(!login_emailusername_user_login_validate([], $login_state), 'An unknown username is rejected by the login form validator.');
  $user_auth = \Drupal::service('user.auth');
  $assert($user_auth->authenticate($user->getAccountName(), $password) === $user->id(), 'Drupal Core authenticates the valid local password.');
  $assert($user_auth->authenticate($user->getAccountName(), 'wrong-' . $suffix) === FALSE, 'Drupal Core rejects an incorrect local password.');
  $switch_depth++;

  $form = \Drupal\change_mail_page\Form\ChangeMailForm::create($container);
  $form_state = new \Drupal\Core\Form\FormState();
  $form_state->set('user', $user);
  $form_state->setValue('mail', $new_email);
  $form_state->setValue('current_pass', $password);
  $form_array = [];
  $form->validateForm($form_array, $form_state);
  $assert(!$form_state->getErrors(), 'Correct current password passes Change Mail Page validation.');
  $form->submitForm($form_array, $form_state);

  $user = $user_storage->load($user->id());
  $assert($user->getEmail() === $old_email, 'Drupal User keeps the current email until confirmation.');
  $pending = \Drupal::service('user.data')->get('aculta_mail_confirmer_user', $user->id(), 'email_change_new_address');
  $assert($pending === $new_email, 'Email Confirmer stores the requested address in its own pending user data.');
  $confirmations = \Drupal::service('aculta_mail_confirmer')->getConfirmations($new_email, 'pending', 0, 'aculta_mail_confirmer_user');
  $confirmation = reset($confirmations);
  $assert($confirmation && (int) $confirmation->get('uid')->target_id === (int) $user->id(), 'The native confirmation belongs to the requesting user.');
  if ($confirmation) {
    $confirmation_ids[] = $confirmation->id();
  }
  $assert(in_array($new_email, $sent_to, TRUE), 'The mail double captured delivery to the new address without network access.');

  $wrong = new \Drupal\Core\Form\FormState();
  $wrong->set('user', $user);
  $wrong->setValue('mail', 'wrong-' . $suffix . '@example.invalid');
  $wrong->setValue('current_pass', 'invalid-' . $suffix);
  $form->validateForm($form_array, $wrong);
  $assert((bool) $wrong->getErrors(), 'An incorrect current password blocks an email change.');
  $assert($user_storage->load($user->id())->getEmail() === $old_email, 'A rejected request leaves the current email unchanged.');

  $duplicate = new \Drupal\Core\Form\FormState();
  $duplicate->set('user', $user);
  $duplicate->setValue('mail', $duplicate_email);
  $duplicate->setValue('current_pass', $password);
  $form->validateForm($form_array, $duplicate);
  $assert((bool) $duplicate->getErrors(), 'An address already used by another user is rejected.');

  $switcher->switchTo($other);
  $switch_depth++;
  $assert(!$confirmation->access('view', $other, TRUE)->isAllowed(), 'Another user cannot access a private confirmation.');
  $switcher->switchBack();
  $switch_depth--;

  $assert(!$confirmation->confirm('invalid-' . $suffix), 'An invalid confirmation hash is rejected.');
  $assert($user_storage->load($user->id())->getEmail() === $old_email, 'An invalid confirmation cannot change the account email.');
  // A real confirmation arrives in a separate HTTP request, whose PHP static
  // state is clean. Recreate the contrib's in-request recursion guard here.
  $confirmation_guard =& drupal_static('aculta_mail_confirmer_user_' . $user->id());
  $confirmation_guard = TRUE;
  $assert($confirmation->confirm($confirmation->getHash()), 'The native confirmation hash is accepted.');
  $confirmation->save();
  $user_storage->resetCache([$user->id()]);
  $assert($user_storage->load($user->id())->getEmail() === $new_email, 'The new email is applied only after valid confirmation.');

  $expired = $confirmation_storage->create([
    'email' => 'expired-' . $suffix . '@example.invalid',
    'realm' => 'aculta_mail_confirmer_user',
    'uid' => $user->id(),
  ]);
  $expired->setCreatedTime(1);
  $expired->save();
  $confirmation_ids[] = $expired->id();
  $assert($expired->isExpired(), 'The module-native confirmation expiry is enforced.');
  $assert($user_storage->load($user->id())->getEmail() === $new_email, 'An expired confirmation leaves the confirmed email unchanged.');

  $new_password = bin2hex(random_bytes(20));
  $password_user = $user_storage->load($user->id());
  $password_user->setExistingPassword($password)->setPassword($new_password);
  $password_violations = $password_user->validate();
  $assert($password_violations->count() === 0, 'Core accepts a new password when the current password is supplied correctly.');
  $password_user->save();
  $assert($user_auth->authenticate($password_user->getAccountName(), $password) === FALSE, 'The previous local password stops authenticating after a Core password change.');
  $assert($user_auth->authenticate($password_user->getAccountName(), $new_password) === $password_user->id(), 'The new local password authenticates through Drupal Core.');
  $bad_password_user = $user_storage->load($user->id());
  $bad_password_user->setExistingPassword('wrong-' . $suffix)->setPassword(bin2hex(random_bytes(20)));
  $assert($bad_password_user->validate()->count() > 0, 'Core rejects password changes when the current password is incorrect.');

  \Drupal::messenger()->deleteAll();
  echo $checks . " local account email integration checks passed; no external mail or network call was made.\n";
}
finally {
  if ($user) {
    $uid = $user->id();
    \Drupal::service('user.data')->delete('aculta_mail_confirmer_user', $uid, 'email_change_new_address');
    \Drupal::service('user.data')->delete('aculta_portal', $uid, 'social_auth_password_unset');
  }
  foreach ($confirmation_ids as $confirmation_id) {
    $confirmation_storage->load($confirmation_id)?->delete();
  }
  while ($switch_depth > 0) {
    $switcher->switchBack();
    $switch_depth--;
  }
  if ($other) {
    $other->delete();
  }
  if ($user) {
    $user->delete();
  }
  \Drupal::messenger()->deleteAll();
  $container->set('plugin.manager.mail', $mail_manager);
}
