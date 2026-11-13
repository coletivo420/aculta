<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_user\Unit\Bio;

use Drupal\aculta_user\Bio\AccountBioCard;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\profile\ProfileStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Verifies that the account bio card composes the shared dialog SDC.
 */
#[CoversClass(AccountBioCard::class)]
#[Group('aculta_user')]
final class AccountBioCardTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')->willReturnOnConsecutiveCalls(
      '/conta/bio',
      (new GeneratedUrl())->setGeneratedUrl('/conta/bio'),
    );
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Bio editor uses the dialog SDC and keeps the HTMX content target.
   */
  public function testBuildUsesDialogComponent(): void {
    $account = $this->createMock(AccountInterface::class);
    $profile_storage = $this->createMock(ProfileStorageInterface::class);
    $profile_storage->method('loadByUser')->willReturn(NULL);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('profile')->willReturn($profile_storage);

    $build = (new AccountBioCard($entity_type_manager))->build($account);
    $dialog = $build['#slots']['content']['dialog'];

    self::assertSame('aculta420_v2:dialog', $dialog['#component']);
    self::assertSame('aculta-account-bio-dialog', $dialog['#props']['id']);
    self::assertSame('/conta/bio', $dialog['#props']['trigger_url']);
    self::assertSame('', $dialog['#slots']['content']['#attributes']['data-aculta-drawer-content']);
    self::assertSame('/conta/bio', $dialog['#attributes']['data-hx-get']);
    self::assertSame('find [data-aculta-drawer-content]', $dialog['#attributes']['data-hx-target']);
    self::assertArrayNotHasKey('data-portal-ui', $dialog['#attributes']);
  }

}
