<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\DiscussionReplyUrlBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DiscussionReplyUrlBuilder::class)]
#[Group('aculta_forum')]
final class DiscussionReplyUrlBuilderTest extends UnitTestCase {

  public function testBuildHostReplyUrlGeneral(): void {
    $host = $this->createMock(EntityInterface::class);
    $url = $this->createMock(Url::class);
    $url->expects($this->once())
      ->method('setOption')
      ->with('fragment', 'compositor-discussao');

    $host->method('toUrl')->with('canonical')->willReturn($url);

    $builder = new DiscussionReplyUrlBuilder();
    $result = $builder->buildHostReplyUrl($host);

    $this->assertSame($url, $result);
  }

  public function testBuildHostReplyUrlWithParentAndMode(): void {
    $host = $this->createMock(EntityInterface::class);
    $url = $this->createMock(Url::class);

    $matcher = $this->exactly(2);
    $url->expects($matcher)
      ->method('setOption')
      ->willReturnCallback(function ($key, $val) use ($matcher) {
        if ($matcher->numberOfInvocations() === 1) {
          $this->assertSame('query', $key);
          $this->assertSame(['resposta' => 42, 'modo' => 'citar'], $val);
        }
        elseif ($matcher->numberOfInvocations() === 2) {
          $this->assertSame('fragment', $key);
          $this->assertSame('comentario-resposta-42', $val);
        }
        return $this->createMock(Url::class);
      });

    $host->method('toUrl')->with('canonical')->willReturn($url);

    $builder = new DiscussionReplyUrlBuilder();
    $result = $builder->buildHostReplyUrl($host, 42, 'citar');

    $this->assertSame($url, $result);
  }

}
