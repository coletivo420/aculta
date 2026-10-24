<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\GanjalabExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(StudioExperienceManager::class)]
#[Group('aculta_studio')]
final class StudioExperienceManagerTest extends UnitTestCase {

  public function testProviderMetadataAndAbsenceOfLms(): void {
    $provider = new GanjalabExperienceProvider(NULL);
    self::assertSame('ganjalab', $provider->id());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_lms.student_dashboard', $provider->routeName());
    self::assertFalse($provider->available());
    $manager = new StudioExperienceManager();
    $manager->addProvider($provider);
    self::assertSame($provider, $manager->get('ganjalab'));
    self::assertNull($manager->get('missing'));
    $account = $this->createMock(AccountInterface::class);
    self::assertFalse($manager->access('ganjalab', 'account', $account)->isAllowed());
    self::assertFalse($manager->access('ganjalab', 'wiki', $account)->isAllowed());
  }

  /**
   * Discovery preserves registration order and does not grant extra access.
   */
  public function testProviderInventoryKeepsOrderAndDeniesOtherPurpose(): void {
    $manager = new StudioExperienceManager();
    $pessoal = new \Drupal\aculta_studio\Experience\PersonalExperienceProvider();
    $ganjalab = new GanjalabExperienceProvider(NULL);
    $manager->addProvider($pessoal);
    $manager->addProvider($ganjalab);

    self::assertSame(['pessoal', 'ganjalab'], array_keys($manager->all()));
    self::assertSame($pessoal, $manager->all()['pessoal']);
    $account = $this->createMock(AccountInterface::class);
    self::assertFalse($manager->access('pessoal', 'forum', $account)->isAllowed());
    self::assertFalse($manager->access('ganjalab', 'account', $account)->isAllowed());
  }

  public function testDuplicateProviderRejected(): void {
    $manager = new StudioExperienceManager();
    $provider = new GanjalabExperienceProvider(NULL);
    $manager->addProvider($provider);
    $this->expectException(\InvalidArgumentException::class);
    $manager->addProvider($provider);
  }

}
