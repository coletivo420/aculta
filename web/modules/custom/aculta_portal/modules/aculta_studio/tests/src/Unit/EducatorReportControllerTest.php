<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_cgu\Scope\ReportTargetScope;
use Drupal\aculta_cgu\Service\CguReportQueue;
use Drupal\aculta_studio\Controller\EducatorReportController;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Garante que a fila do educador não abre sem a permissão do escopo LMS.
 */
#[Group('aculta_studio')]
final class EducatorReportControllerTest extends UnitTestCase {

  /**
   * Conta sem a permissão de moderação LMS recebe acesso negado.
   */
  public function testQueueDeniedWithoutLmsModerationPermission(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    // Classes finais não são mockáveis: a fila e o escopo reais são construídos sem uso.
    $queue = new CguReportQueue($entities, $account, NULL, NULL);
    $controller = new EducatorReportController(
      $queue,
      new ReportTargetScope([], $entities),
      $entities,
      $this->createMock(DateFormatterInterface::class),
      $account,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $controller->queue();
  }

}
