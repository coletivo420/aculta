<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum\Unit;

use Drupal\aculta_forum\Service\DiscussionReplyIdempotencyManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DiscussionReplyIdempotencyManager::class)]
#[Group('aculta_forum')]
final class DiscussionReplyIdempotencyManagerTest extends UnitTestCase {

  public function testGenerateIntentionKeyIsDeterministic(): void {
    $database = $this->createMock(Connection::class);
    $manager = new DiscussionReplyIdempotencyManager($database);

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);

    $key1 = $manager->generateIntentionKey($account, 'node', '12', 5, '  Exemplo de resposta   com espaços   ');
    $key2 = $manager->generateIntentionKey($account, 'node', '12', 5, 'Exemplo de resposta com espaços');

    $this->assertSame($key1, $key2);

    $keyDifferent = $manager->generateIntentionKey($account, 'node', '12', 5, 'Outra resposta');
    $this->assertNotSame($key1, $keyDifferent);
  }

}
