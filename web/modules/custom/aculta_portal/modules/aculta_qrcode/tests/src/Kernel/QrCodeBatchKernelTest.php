<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_qrcode\Kernel;

use Drupal\aculta_qrcode\Controller\QrCodeBatchController;
use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Service\QrCodePublicUrlInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Exportação em lote: ZIP com um arquivo por código, e recusa de lote inválido ou incompleto.
 */
#[Group('aculta_qrcode')]
#[RunTestsInSeparateProcesses]
final class QrCodeBatchKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'datetime',
    'aculta_qrcode',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'user']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('aculta_qr_code');
    $administrator = User::create(['name' => 'administrador', 'status' => 1]);
    $administrator->save();
    $this->container->get('current_user')->setAccount($administrator);
  }

  /**
   * Gera ZIP com um arquivo por código, no formato pedido, com nomes qr-<codigo>.<formato>.
   *
   * A URL pública vem de um resolvedor de teste, pelo container: o domínio do purpose links depende do aculta_portal,
   * que não está habilitado neste Kernel.
   */
  public function testBatchReturnsZipWithOneFilePerCode(): void {
    $this->container->set('aculta_qrcode.url_resolver', new class implements QrCodePublicUrlInterface {

      /**
       * URL fixa de teste para o código.
       */
      public function publicUrl(QrCode $entity): ?Url {
        return Url::fromUri('https://aculta.example/qr/' . $entity->getCode());
      }

    });
    $manager = $this->container->get('aculta_qrcode.manager');
    $first = $manager->create('Cartaz A', 'https://example.invalid/a');
    $second = $manager->create('Cartaz B', 'https://example.invalid/b');

    $response = $this->controller()->batch(Request::create('/lote', 'GET', [
      'ids' => $first->id() . ',' . $second->id(),
      'formato' => 'svg',
    ]));

    self::assertSame('application/zip', $response->headers->get('Content-Type'));
    self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    $names = $this->zipNames((string) $response->getContent());
    sort($names);
    $expected = ['qr-' . $first->getCode() . '.svg', 'qr-' . $second->getCode() . '.svg'];
    sort($expected);
    self::assertSame($expected, $names);
  }

  /**
   * Nomes dos arquivos dentro de um ZIP em memória.
   *
   * @return string[]
   *   Nomes das entradas.
   */
  private function zipNames(string $bytes): array {
    $path = tempnam(sys_get_temp_dir(), 'qr-test-');
    file_put_contents($path, $bytes);
    $zip = new \ZipArchive();
    self::assertTrue($zip->open($path) === TRUE, 'ZIP inválido.');
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $names[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();
    @unlink($path);
    return $names;
  }

  /**
   * Sem URL pública (o domínio do purpose links vem do aculta_portal, fora deste Kernel), o lote recusa com 503
   * e não entrega ZIP parcial. O caminho de sucesso (ZIP com um arquivo por código) fica para o teste de integração
   * com aculta_portal habilitado.
   */
  public function testBatchWithoutPublicUrlIsUnavailable(): void {
    $manager = $this->container->get('aculta_qrcode.manager');
    $first = $manager->create('Cartaz A', 'https://example.invalid/a');
    $second = $manager->create('Cartaz B', 'https://example.invalid/b');

    $this->expectException(ServiceUnavailableHttpException::class);
    $this->controller()->batch(Request::create('/lote', 'GET', [
      'ids' => $first->id() . ',' . $second->id(),
      'formato' => 'svg',
    ]));
  }

  /**
   * Um id inexistente derruba o lote inteiro, sem entregar parte dos arquivos.
   */
  public function testMissingCodeFailsWholeBatch(): void {
    $manager = $this->container->get('aculta_qrcode.manager');
    $existing = $manager->create('Cartaz A', 'https://example.invalid/a');
    $this->expectException(NotFoundHttpException::class);
    $this->controller()->batch(Request::create('/lote', 'GET', [
      'ids' => $existing->id() . ',999999',
      'formato' => 'svg',
    ]));
  }

  /**
   * Ids e formato inválidos são recusados com 400, antes de consultar o storage.
   */
  public function testInvalidRequestIsBadRequest(): void {
    $this->expectException(BadRequestHttpException::class);
    $this->controller()->batch(Request::create('/lote', 'GET', ['ids' => '1;drop', 'formato' => 'svg']));
  }

  /**
   * Controlador com os serviços do container.
   */
  private function controller(): QrCodeBatchController {
    return $this->container->get('aculta_qrcode.batch_controller');
  }

}
