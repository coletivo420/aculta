<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_messenger\Functional;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\BrowserTestBase;
use Drupal\aculta_user\Identity\PublicMemberIdentityInterface;
use Drupal\domain\Entity\Domain;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the Messenger can install without site-global editorial config.
 */
#[Group('aculta_messenger')]
#[RunTestsInSeparateProcesses]
final class MessengerModuleInstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['aculta_messenger'];

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    Domain::create([
      'id' => 'conta_aculta_org',
      'hostname' => '127.0.0.1',
      'name' => 'Conta',
      'scheme' => 'http',
      'status' => TRUE,
      'weight' => 0,
    ])->save();
    if ($this->container->get('module_handler')->moduleExists('captcha')) {
      $this->config('captcha.settings')->set('default_challenge', '')->save();
      foreach ($this->container->get('entity_type.manager')->getStorage('captcha_point')->loadMultiple() as $point) {
        $point->delete();
      }
    }
    $public_files = DRUPAL_ROOT . '/' . $this->publicFilesDirectory;
    self::assertDirectoryExists($public_files);
    self::assertTrue(chmod($public_files, 02770));
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSettings() {
    parent::prepareSettings();
    $file = DRUPAL_ROOT . '/' . $this->siteDirectory . '/settings.php';
    file_put_contents($file, "\n\$settings['aculta_domain_purpose_test_relaxed'] = TRUE;\n", FILE_APPEND);
  }

  /**
   * {@inheritdoc}
   */
  protected function initSettings() {
    parent::initSettings();
    $file = DRUPAL_ROOT . '/' . $this->siteDirectory . '/settings.php';
    $permissions = fileperms($file) & 0777;
    chmod($file, 0666);
    file_put_contents($file, <<<'PHP'

if ($database_url = getenv('SIMPLETEST_DB')) {
  $databases['default']['default'] = \Drupal\Core\Database\Database::convertDbUrlToConnectionInfo($database_url, include_test_drivers: TRUE);
  $databases['default']['default']['prefix'] ??= '';
  $databases['default']['default']['prefix'] .= drupal_valid_test_ua();
}
PHP, FILE_APPEND);
    chmod($file, $permissions);
  }

  /**
   * The target module and its dependencies install in an isolated site.
   */
  public function testModuleAndDependenciesInstall(): void {
    self::assertTrue($this->container->get('module_handler')->moduleExists('aculta_messenger'));
  }

  /**
   * An authenticated member creates a conversation and replies over HTTP.
   */
  public function testAuthenticatedMemberCanComposePrivateConversationOverHttp(): void {
    $member = $this->drupalCreateUser([
      'access user profiles',
      'use private messaging system',
    ]);
    $recipient = $this->drupalCreateUser([
      'access user profiles',
      'use private messaging system',
    ]);
    $recipientTwo = $this->drupalCreateUser([
      'access user profiles',
      'use private messaging system',
    ]);
    $entityTypeManager = $this->container->get('entity_type.manager');
    foreach ([
      'field_nickname' => ['type' => 'string', 'label' => 'Apelido'],
      'field_perfil_publico' => ['type' => 'boolean', 'label' => 'Perfil público'],
    ] as $fieldName => $field) {
      self::assertNotNull($entityTypeManager->getStorage('field_storage_config')->load('profile.' . $fieldName));
      if (!$entityTypeManager->getStorage('field_config')->load('profile.authenticated.' . $fieldName)) {
        FieldConfig::create([
          'field_name' => $fieldName,
          'entity_type' => 'profile',
          'bundle' => 'authenticated',
          'label' => $field['label'],
        ])->save();
      }
    }
    $this->createPublicRecipientProfile($recipient);
    $this->createPublicRecipientProfile($recipientTwo);
    self::assertSame(
      'membro-' . $recipient->id(),
      $this->container->get(PublicMemberIdentityInterface::class)
        ->publicDisplayName($recipient),
    );
    self::assertInstanceOf(UserInterface::class, $member);
    self::assertInstanceOf(UserInterface::class, $recipient);
    $this->loginAs($member);

    $this->drupalGet('/mensagens/nova');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Nova conversa');
    $this->assertSession()->fieldExists('message');

    $threadStorage = $this->container->get('entity_type.manager')->getStorage('private_message_thread');
    $messageStorage = $this->container->get('entity_type.manager')->getStorage('private_message');
    $threadIdsBefore = array_keys($threadStorage->loadMultiple());
    $messageIdsBefore = array_keys($messageStorage->loadMultiple());
    $this->drupalGet(Url::fromRoute('aculta_messenger.new_conversation_group', [], [
      'query' => ['members' => [(int) $recipient->id()]],
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('message');
    $this->assertSession()->pageTextContains('membro-' . $recipient->id());
    $body = 'Mensagem funcional inicial do ACULTA Messenger.';
    $this->getSession()->getPage()->fillField('message', $body);
    $this->getSession()->getPage()->find('css', 'form[data-aculta-new-conversation-form] input[type="submit"]')->click();

    $threadIdsAfter = array_keys($threadStorage->loadMultiple());
    $newThreadIds = array_values(array_diff($threadIdsAfter, $threadIdsBefore));
    self::assertCount(1, $newThreadIds, sprintf(
      'The HTTP submission creates one private-message thread; status=%d, response=%s',
      $this->getSession()->getStatusCode(),
      mb_substr($this->getSession()->getPage()->getText(), 0, 400),
    ));
    $thread = $threadStorage->load(reset($newThreadIds));
    $newMessageIds = array_values(array_diff(array_keys($messageStorage->loadMultiple()), $messageIdsBefore));
    self::assertCount(1, $newMessageIds, sprintf(
      'The HTTP submission persists one private message; status=%d, response=%s',
      $this->getSession()->getStatusCode(),
      mb_substr($this->getSession()->getPage()->getText(), 0, 400),
    ));
    self::assertSame(
      [(int) $member->id(), (int) $recipient->id()],
      array_map('intval', $thread->getMembersId()),
    );
    $messages = $thread->getMessages(TRUE);
    self::assertCount(1, $messages);
    self::assertSame($body, reset($messages)->getMessage());

    $this->drupalGet(Url::fromRoute('aculta_messenger.drawer_conversation', [
      'private_message_thread' => $thread->id(),
    ]), [], ['X-Requested-With' => 'XMLHttpRequest']);
    self::assertSame(
      200,
      $this->getSession()->getStatusCode(),
      mb_substr($this->getSession()->getPage()->getText(), 0, 400),
    );
    $replyForm = $this->getSession()->getPage()->find('css', 'form[data-aculta-reply-form]');
    self::assertNotNull($replyForm, 'An authorized member sees the shared reply form.');
    $replyAction = $replyForm->getAttribute('action');
    $replyToken = $replyForm->find('css', 'input[name="form_token"]')?->getValue();
    self::assertIsString($replyAction);
    self::assertNotSame('', $replyToken);
    self::assertNotSame('', parse_url($replyAction, PHP_URL_QUERY), 'The POST URL includes Drupal route CSRF protection.');

    $replyBody = 'Resposta funcional autenticada do ACULTA Messenger.';
    parse_str((string) parse_url($replyAction, PHP_URL_QUERY), $replyQuery);
    $replyUrl = $this->buildUrl((string) parse_url($replyAction, PHP_URL_PATH), ['query' => $replyQuery]);
    $response = $this->getHttpClient()->request('POST', $replyUrl, [
      'cookies' => $this->getSessionCookies(),
      'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
      'form_params' => [
        'message' => [['value' => $replyBody]],
        'reply_token' => $replyToken,
      ],
      'http_errors' => FALSE,
    ]);
    self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
    $threadStorage->resetCache([$thread->id()]);
    $thread = $threadStorage->loadUnchanged($thread->id());
    $messages = $thread->getMessages(TRUE);
    self::assertCount(2, $messages, (string) $response->getBody());
    self::assertSame($replyBody, end($messages)->getMessage());
    self::assertStringContainsString($replyBody, (string) $response->getBody());

    $this->assertNoJavaScriptGroupComposition($recipient, $recipientTwo);
  }

  /**
   * A member can send a group message without JavaScript.
   */
  private function assertNoJavaScriptGroupComposition(UserInterface $firstRecipient, UserInterface $secondRecipient): void {
    $entityTypeManager = $this->container->get('entity_type.manager');
    $threadStorage = $entityTypeManager->getStorage('private_message_thread');
    $messageStorage = $entityTypeManager->getStorage('private_message');
    $threadIdsBefore = array_keys($threadStorage->loadMultiple());
    $messageIdsBefore = array_keys($messageStorage->loadMultiple());

    $this->drupalGet(Url::fromRoute('aculta_messenger.new_conversation', [], [
      'query' => ['q' => 'membro-' . $firstRecipient->id()],
    ]));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('membro-' . $firstRecipient->id());
    $this->clickLink('membro-' . $firstRecipient->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('membro-' . $firstRecipient->id());

    $searchForm = $this->getSession()->getPage()->find('css', 'form[data-aculta-nojs-search]');
    self::assertNotNull($searchForm, 'The native GET search is available without JavaScript.');
    $searchForm->find('css', 'input[name="q"]')->setValue('membro-' . $secondRecipient->id());
    $searchForm->find('css', 'input[type="submit"]')->click();
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('membro-' . $secondRecipient->id());
    $this->clickLink('membro-' . $secondRecipient->id());
    $this->assertSession()->statusCodeEquals(200);

    $form = $this->getSession()->getPage()->find('css', 'form[data-aculta-new-conversation-form]');
    self::assertNotNull($form);
    $selectedMembers = $form->findAll('css', 'input[name="members[]"]');
    self::assertCount(2, $selectedMembers);
    self::assertSame(
      [(int) $firstRecipient->id(), (int) $secondRecipient->id()],
      array_map(static fn($field): int => (int) $field->getAttribute('value'), $selectedMembers),
    );
    $this->assertSession()->pageTextContains('membro-' . $firstRecipient->id());
    $this->assertSession()->pageTextContains('membro-' . $secondRecipient->id());

    $body = 'Mensagem funcional de grupo sem JavaScript.';
    $this->getSession()->getPage()->fillField('message', $body);
    $form->find('css', 'input[type="submit"]')->click();

    $threadIdsAfter = array_keys($threadStorage->loadMultiple());
    $newThreadIds = array_values(array_diff($threadIdsAfter, $threadIdsBefore));
    self::assertCount(1, $newThreadIds);
    $thread = $threadStorage->load(reset($newThreadIds));
    self::assertSame(
      [(int) $this->loggedInUser->id(), (int) $firstRecipient->id(), (int) $secondRecipient->id()],
      array_map('intval', $thread->getMembersId()),
    );
    $newMessageIds = array_values(array_diff(array_keys($messageStorage->loadMultiple()), $messageIdsBefore));
    self::assertCount(1, $newMessageIds);
    $messages = $thread->getMessages(TRUE);
    self::assertCount(1, $messages);
    self::assertSame($body, reset($messages)->getMessage());

    $messageCount = count($messages);
    $outsider = $this->drupalCreateUser([
      'access user profiles',
      'use private messaging system',
    ]);
    $this->drupalResetSession();
    $this->getSession()->getDriver()->getClient()->restart();
    $this->drupalLogin($outsider);
    $this->drupalGet(Url::fromRoute('aculta_messenger.drawer_conversation', [
      'private_message_thread' => $thread->id(),
    ]), [], ['X-Requested-With' => 'XMLHttpRequest']);
    self::assertContains($this->getSession()->getStatusCode(), [403, 404]);
    self::assertStringNotContainsString($body, $this->getSession()->getPage()->getText());
    $threadStorage->resetCache([$thread->id()]);
    $thread = $threadStorage->loadUnchanged($thread->id());
    self::assertCount($messageCount, $thread->getMessages(TRUE));
  }

  /**
   * Adds public profile fields used for eligible recipients.
   */
  private function createPublicRecipientProfile(UserInterface $recipient): void {
    Profile::create([
      'type' => 'authenticated',
      'uid' => (int) $recipient->id(),
      'status' => TRUE,
      'is_default' => TRUE,
      'field_nickname' => 'membro-' . $recipient->id(),
      'field_perfil_publico' => TRUE,
    ])->save();
  }

  /**
   * Establishes the authenticated browser session using the Core test helper.
   */
  private function loginAs(UserInterface $account): void {
    $this->drupalLogin($account);
  }

}
