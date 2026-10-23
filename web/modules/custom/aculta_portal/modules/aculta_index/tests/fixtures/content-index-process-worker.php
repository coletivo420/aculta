<?php

/**
 * @file
 * Executes one Content Index work-manager action in an independent process.
 */

declare(strict_types=1);

use Drupal\aculta_index\Provider\ContentIndexProviderRegistry;
use Drupal\aculta_index\Queue\ContentIndexWorkManager;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Database\Database;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\Site\Settings;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

if ($argc !== 7) {
  fwrite(STDERR, "Invalid process worker arguments.\n");
  exit(2);
}

require $argv[1];

try {
  $configuration = json_decode((string) file_get_contents($argv[2]), TRUE, 512, JSON_THROW_ON_ERROR);
  Database::addConnectionInfo('default', 'default', $configuration['connection']);
  $database = Database::getConnection('default', 'default');
  $database->query('SET SESSION innodb_lock_wait_timeout = 5');

  $queue = new class implements QueueInterface {

    /**
     * {@inheritdoc}
     */
    public function createItem($data) {
      return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function numberOfItems() {
      return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function claimItem($lease_time = 3600) {
      return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($item) {}

    /**
     * {@inheritdoc}
     */
    public function releaseItem($item) {
      return FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function createQueue() {}

    /**
     * {@inheritdoc}
     */
    public function deleteQueue() {}

  };
  $queueFactory = new class($queue) implements QueueFactoryInterface {

    /**
     * Constructs the no-op queue factory for this test process.
     */
    public function __construct(private readonly QueueInterface $queue) {}

    /**
     * {@inheritdoc}
     */
    public function get($name) {
      return $this->queue;
    }

  };
  $serviceLocator = new class($queueFactory) implements ContainerInterface {

    /**
     * Constructs the service locator used by Drupal's QueueFactory.
     */
    public function __construct(private readonly QueueFactoryInterface $queueFactory) {}

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed {
      if ($id === 'queue.database') {
        return $this->queueFactory;
      }
      throw new \RuntimeException('Unknown process-test service.');
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool {
      return $id === 'queue.database';
    }

  };
  $manager = new ContentIndexWorkManager(
    $database,
    new QueueFactory(new Settings([]), $serviceLocator),
    new Time(),
    new ContentIndexProviderRegistry(),
    new NullLogger(),
  );

  $barrierPath = $argv[3];
  $barrierDeadline = microtime(TRUE) + 30;
  while (!is_file($barrierPath)) {
    if (microtime(TRUE) >= $barrierDeadline) {
      throw new \RuntimeException('Timed out waiting for the process-test barrier.');
    }
    usleep(10_000);
  }

  $action = $argv[4];
  $iterations = (int) $argv[5];
  $workId = (int) $argv[6];
  if ($action === 'schedule') {
    $sequences = [];
    for ($iteration = 0; $iteration < $iterations; $iteration++) {
      $sequences[] = $manager->schedule('process_stress', 'node', 'process-stress-1', 'pt-br');
    }
    echo json_encode(['sequences' => $sequences], JSON_THROW_ON_ERROR);
  }
  elseif ($action === 'claim') {
    echo json_encode(['claim' => $manager->claim($workId)], JSON_THROW_ON_ERROR);
  }
  else {
    throw new \InvalidArgumentException('Unknown process-test action.');
  }
}
catch (\Throwable $exception) {
  fwrite(STDERR, $exception::class . "\n");
  exit(1);
}
