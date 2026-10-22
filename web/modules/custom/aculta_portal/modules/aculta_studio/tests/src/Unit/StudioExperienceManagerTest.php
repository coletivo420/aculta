<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Experience\GanjaLab420ExperienceProvider;
use Drupal\aculta_studio\Experience\StudioExperienceManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(StudioExperienceManager::class)]
#[Group('aculta_studio')]
final class StudioExperienceManagerTest extends UnitTestCase {

  public function testProviderMetadataAndAbsenceOfLms(): void {
    $provider = new GanjaLab420ExperienceProvider(NULL);
    self::assertSame('ganjalab420', $provider->id());
    self::assertSame('account', $provider->purpose());
    self::assertSame('aculta_lms.student_dashboard', $provider->routeName());
    self::assertFalse($provider->available());
    $manager = new StudioExperienceManager();
    $manager->addProvider($provider);
    self::assertSame($provider, $manager->get('ganjalab420'));
    self::assertNull($manager->get('missing'));
    $account = $this->createMock(AccountInterface::class);
    self::assertFalse($manager->access('ganjalab420', 'account', $account)->isAllowed());
    self::assertFalse($manager->access('ganjalab420', 'wiki', $account)->isAllowed());
  }

  public function testDuplicateProviderRejected(): void {
    $manager = new StudioExperienceManager();
    $provider = new GanjaLab420ExperienceProvider(NULL);
    $manager->addProvider($provider);
    $this->expectException(\InvalidArgumentException::class);
    $manager->addProvider($provider);
  }

}
