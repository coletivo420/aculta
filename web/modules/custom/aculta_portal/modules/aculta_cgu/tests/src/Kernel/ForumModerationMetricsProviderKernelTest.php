<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_cgu\Kernel;

use Drupal\aculta_cgu\Entity\CguReport;
use Drupal\aculta_cgu\Service\ReportService;
use Drupal\aculta_cgu\Statistics\ForumModerationMetricsProvider;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Indicadores de moderação calculados a partir da denúncia UGC.
 */
#[Group('aculta_ugc')]
#[RunTestsInSeparateProcesses]
final class ForumModerationMetricsProviderKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'aculta_cgu',
  ];

  /**
   * Provedor sob teste.
   */
  private ForumModerationMetricsProvider $provider;

  /**
   * Denunciante.
   */
  private User $reporter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_ugc_report');
    $this->reporter = User::create(['name' => 'denunciante', 'status' => 1]);
    $this->reporter->save();
    $this->provider = new ForumModerationMetricsProvider($this->container->get('database'));
  }

  /**
   * Denúncias abertas deduplicam por conteúdo e ignoram outro purpose.
   */
  public function testOpenReportsDeduplicateByTargetAndPurpose(): void {
    $now = time();
    $this->report('node', 10, ReportService::STATUS_PENDING, $now, NULL);
    $this->report('node', 10, ReportService::STATUS_REVIEW, $now, NULL);
    $this->report('comment', 20, ReportService::STATUS_PENDING, $now, NULL);
    $this->report('node', 10, ReportService::STATUS_PENDING, $now, NULL, 'wiki');

    $day = intdiv($now, 86400);
    self::assertSame(2, $this->provider->value('forum.moderation.reports_open', $day));
  }

  /**
   * Decisões contam no dia e a mediana usa created -> decided_at.
   */
  public function testResolvedCountAndMedian(): void {
    $now = time();
    $this->report('node', 10, ReportService::STATUS_UPHELD, $now - 1800, $now);
    $this->report('node', 11, ReportService::STATUS_REJECTED, $now - 5400, $now);
    $this->report('node', 12, ReportService::STATUS_PENDING, $now, NULL);
    $this->report('node', 13, ReportService::STATUS_REJECTED, $now - 1800, $now, 'wiki');

    $day = intdiv($now, 86400);
    self::assertSame(2, $this->provider->value('forum.moderation.reports_resolved', $day));
    self::assertSame(60, $this->provider->value('forum.moderation.resolution_median_minutes', $day));
  }

  /**
   * Cria uma denúncia UGC de propósito forum por padrão.
   */
  private function report(string $targetType, int $targetId, string $status, int $created, ?int $decidedAt, string $purpose = 'forum'): void {
    CguReport::create([
      'target_type' => $targetType,
      'target_id' => $targetId,
      'uid' => $this->reporter->id(),
      'motivo' => 'spam',
      'purpose' => $purpose,
      'status' => $status,
      'created' => $created,
      'decided_at' => $decidedAt,
    ])->save();
  }

}
