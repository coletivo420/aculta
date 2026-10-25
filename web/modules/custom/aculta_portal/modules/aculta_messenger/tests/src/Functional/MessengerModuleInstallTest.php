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
   * An authenticated member can load the actual private-message composer.
   */
  public function testAuthenticatedMemberCanOpenConversationComposer(): void {
    $member = $this->drupalCreateUser([
      'access user profiles',
      'use private messaging system',
    ]);
    $recipient = $this->drupalCreateUser([
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
    Profile::create([
      'type' => 'authenticated',
      'uid' => (int) $recipient->id(),
      'status' => TRUE,
      'is_default' => TRUE,
      'field_nickname' => 'membro-' . $recipient->id(),
      'field_perfil_publico' => TRUE,
    ])->save();
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
  }

  /**
   * Establishes the authenticated browser session using the Core test helper.
   */
  private function loginAs(UserInterface $account): void {
    $this->drupalLogin($account);
  }

}
