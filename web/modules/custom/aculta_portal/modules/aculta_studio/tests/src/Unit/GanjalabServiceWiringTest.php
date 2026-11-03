<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_studio\Unit;

use Drupal\aculta_studio\Controller\GanjalabController;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Confirma, pela configuração, a ligação do controller do Ganjalab.
 *
 * Cobre a asserção que saiu de AcultaLmsInstallTest (aprovado pelo responsável):
 * o serviço existe, aponta para GanjalabController e recebe as dependências certas.
 */
#[Group('aculta_studio')]
#[CoversClass(GanjalabController::class)]
final class GanjalabServiceWiringTest extends UnitTestCase {

  /**
   * O serviço aculta_studio.ganjalab_controller está definido com a classe e os argumentos esperados.
   */
  public function testGanjalabControllerServiceIsDefined(): void {
    $file = dirname(__DIR__, 3) . '/aculta_studio.services.yml';
    $services = Yaml::parseFile($file)['services'];

    self::assertArrayHasKey('aculta_studio.ganjalab_controller', $services);
    $definition = $services['aculta_studio.ganjalab_controller'];
    self::assertSame(GanjalabController::class, $definition['class']);
    self::assertSame(
      ['@aculta_studio.experience_manager', '@current_user'],
      $definition['arguments'],
    );
  }

}
