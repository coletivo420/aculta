<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_maps\Unit;

use Drupal\aculta_maps\RouteLinkBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests map directions links.
 */
#[Group('aculta_maps')]
final class RouteLinkBuilderTest extends UnitTestCase {

  /**
   * Directions links encode user supplied destinations and open safely.
   */
  public function testBuildEncodesDestinationAndOpensInNewTab(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('route_url_template')->willReturn('https://www.google.com/maps/dir/?api=1&destination={destino}');
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->with('aculta_maps.settings')->willReturn($settings);
    $url = (new RouteLinkBuilder($config))->build('Rua 10, Goiânia/GO');

    $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=Rua%2010%2C%20Goi%C3%A2nia%2FGO', $url->getUri());
    $this->assertSame('_blank', $url->getOption('attributes')['target']);
    $this->assertSame('noopener noreferrer', $url->getOption('attributes')['rel']);
  }

  /**
   * An unapproved configured host falls back to the canonical template.
   */
  public function testUnapprovedHostIsRejected(): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('route_url_template')->willReturn('https://attacker.example/maps/dir/?destination={destino}');
    $config = $this->createMock(ConfigFactoryInterface::class);
    $config->method('get')->with('aculta_maps.settings')->willReturn($settings);

    $this->assertSame(
      'https://www.google.com/maps/dir/?api=1&destination=Place',
      (new RouteLinkBuilder($config))->build('Place')->getUri(),
    );
  }

}
