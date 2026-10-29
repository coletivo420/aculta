<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Kernel;

use Drupal\aculta_calendar\Item\CalendarItem;
use Drupal\aculta_calendar\Item\CalendarItemProviderInterface;
use Drupal\aculta_calendar\Item\CalendarItemSource;
use Drupal\aculta_calendar\Item\CalendarQuery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Um provedor com falha não impede os demais.
 */
#[Group('aculta_calendar')]
#[RunTestsInSeparateProcesses]
final class CalendarItemSourceKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  /**
   * Modules enabled by this Kernel test.
   *
   * @var string[]
   */
  protected static $modules = ['system'];

  /**
   * O provedor que lança exceção é ignorado; o saudável continua.
   */
  public function testProvedorComFalhaNaoDerrubaOsDemais(): void {
    $falha = new class implements CalendarItemProviderInterface {

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'falha';
      }

      /**
       * {@inheritdoc}
       */
      public function items(CalendarQuery $query): iterable {
        throw new \RuntimeException('sem tabela');
      }

      /**
       * {@inheritdoc}
       */
      public function filtros(): array {
        throw new \RuntimeException('sem tabela');
      }

      /**
       * {@inheritdoc}
       */
      public function cacheTags(): array {
        throw new \RuntimeException('sem tabela');
      }

      /** {@inheritdoc} */
      public function cacheContexts(): array {
        throw new \RuntimeException('sem tabela');
      }

      /** {@inheritdoc} */
      public function cacheMaxAge(): int {
        throw new \RuntimeException('sem tabela');
      }

    };
    $ok = new class implements CalendarItemProviderInterface {

      /**
       * {@inheritdoc}
       */
      public function id(): string {
        return 'ok';
      }

      /**
       * {@inheritdoc}
       */
      public function items(CalendarQuery $query): iterable {
        $fuso = new \DateTimeZone('America/Sao_Paulo');
        return [new CalendarItem(
          id: 'teste:1',
          titulo: 'Oficina',
          inicio: new \DateTimeImmutable('2030-05-10 19:00:00', $fuso),
          fim: new \DateTimeImmutable('2030-05-10 21:00:00', $fuso),
          origem: 'teste',
          ),
        ];
      }

      /**
       * {@inheritdoc}
       */
      public function filtros(): array {
        return ['tipo' => ['label' => 'Tipo', 'opcoes' => [1 => 'Oficina']]];
      }

      /**
       * {@inheritdoc}
       */
      public function cacheTags(): array {
        return ['teste_list'];
      }

      /** {@inheritdoc} */
      public function cacheContexts(): array {
        return ['user'];
      }

      /** {@inheritdoc} */
      public function cacheMaxAge(): int {
        return 0;
      }

    };

    $fonte = new CalendarItemSource([$falha, $ok]);
    $fuso = new \DateTimeZone('America/Sao_Paulo');
    $query = new CalendarQuery(
      new \DateTimeImmutable('2030-05-01 00:00:00', $fuso),
      new \DateTimeImmutable('2030-05-31 23:59:59', $fuso),
      new \DateTimeImmutable('2030-05-01 00:00:00', $fuso),
    );

    $itens = $fonte->items($query);
    $this->assertCount(1, $itens);
    $this->assertSame('teste:1', $itens[0]->id);
    $this->assertArrayHasKey('tipo', $fonte->filtros());
    $this->assertSame(['teste_list'], $fonte->cacheTags());
    $this->assertSame(['user'], $fonte->cacheContexts());
    $this->assertSame(0, $fonte->cacheMaxAge());
  }

}
