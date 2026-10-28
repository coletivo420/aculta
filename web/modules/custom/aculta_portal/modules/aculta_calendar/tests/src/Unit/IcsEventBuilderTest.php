<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_calendar\Unit;

use Drupal\aculta_calendar\Ics\IcsEventBuilder;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Gerador .ics: quebra de linha, escape, UID, dia inteiro, fuso e dobra.
 */
#[Group('aculta_calendar')]
class IcsEventBuilderTest extends UnitTestCase {

  /**
   * Gerador sob teste.
   */
  private IcsEventBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->builder = new IcsEventBuilder();
  }

  /**
   * Início padrão: 28/10/2026 às 19h em São Paulo (UTC-3).
   */
  private function inicio(): \DateTimeImmutable {
    return new \DateTimeImmutable('2026-10-28 19:00:00', new \DateTimeZone('America/Sao_Paulo'));
  }

  /**
   * Momento de geração fixo, para o resultado ser determinístico.
   */
  private function carimbo(): \DateTimeImmutable {
    return new \DateTimeImmutable('2026-10-28 12:00:00', new \DateTimeZone('UTC'));
  }

  /**
   * Desfaz a dobra (RFC 5545 §3.1) e devolve as linhas lógicas.
   *
   * @return string[]
   */
  private function linhas(string $ics): array {
    // O bloco VTIMEZONE tem DTSTART próprio; fica fora das propriedades do evento.
    $semFuso = preg_replace('/BEGIN:VTIMEZONE.*?END:VTIMEZONE\r\n/s', '', $ics) ?? $ics;
    $desdobrado = str_replace("\r\n ", '', $semFuso);
    return explode("\r\n", rtrim($desdobrado, "\r\n"));
  }

  /**
   * Linhas que começam com o nome da propriedade informado.
   *
   * @return string[]
   */
  private function propriedade(string $ics, string $nome): array {
    return array_values(array_filter(
      $this->linhas($ics),
      static fn (string $linha): bool => str_starts_with($linha, $nome . ':') || str_starts_with($linha, $nome . ';'),
    ));
  }

  /**
   * Título com CRLF não cria propriedade extra; CR e LF viram espaço antes do escape.
   */
  public function testTituloComQuebraDeLinhaNaoCriaPropriedade(): void {
    $ics = $this->builder->build(
      'node:1',
      "Evento\r\nATTENDEE:mailto:invasor@exemplo.org",
      '',
      $this->inicio(),
      NULL,
      FALSE,
      $this->carimbo(),
    );

    $this->assertCount(1, $this->propriedade($ics, 'SUMMARY'));
    $this->assertCount(0, $this->propriedade($ics, 'ATTENDEE'));
    $this->assertSame(
      ['SUMMARY:Evento ATTENDEE:mailto:invasor@exemplo.org'],
      $this->propriedade($ics, 'SUMMARY'),
    );
  }

  /**
   * Quebra de linha isolada (LF) também não cria propriedade no local.
   */
  public function testLocalComLfNaoCriaPropriedade(): void {
    $ics = $this->builder->build(
      'node:1',
      'Oficina',
      "Sala 2\nDESCRIPTION:falsa",
      $this->inicio(),
      NULL,
      FALSE,
      $this->carimbo(),
    );

    $this->assertCount(0, $this->propriedade($ics, 'DESCRIPTION'));
    $this->assertSame(['LOCATION:Sala 2 DESCRIPTION:falsa'], $this->propriedade($ics, 'LOCATION'));
  }

  /**
   * Ponto e vírgula, vírgula e barra invertida saem escapados.
   */
  public function testPontoEVirgulaEBarraSaoEscapados(): void {
    $ics = $this->builder->build(
      'node:2',
      'A;B,C\D',
      '',
      $this->inicio(),
      NULL,
      FALSE,
      $this->carimbo(),
    );

    $this->assertSame(['SUMMARY:A\;B\,C\\\\D'], $this->propriedade($ics, 'SUMMARY'));
  }

  /**
   * Mesma entidade e mesma data geram o mesmo UID, mesmo com título e carimbo diferentes.
   */
  public function testUidEstavelParaMesmaEntidadeEData(): void {
    $primeiro = $this->builder->build('node:7', 'Título antigo', '', $this->inicio(), NULL, FALSE, $this->carimbo());
    $segundo = $this->builder->build(
      'node:7',
      'Título novo',
      'Outro local',
      $this->inicio(),
      NULL,
      FALSE,
      new \DateTimeImmutable('2030-01-01 00:00:00', new \DateTimeZone('UTC')),
    );

    $this->assertSame(
      $this->propriedade($primeiro, 'UID'),
      $this->propriedade($segundo, 'UID'),
    );
  }

  /**
   * Outra data, ou outra entidade, gera UID diferente.
   */
  public function testUidMudaComDataOuEntidade(): void {
    $base = $this->propriedade($this->builder->build('node:7', 'X', '', $this->inicio(), NULL, FALSE, $this->carimbo()), 'UID');
    $outraData = $this->propriedade($this->builder->build(
      'node:7',
      'X',
      '',
      $this->inicio()->modify('+1 day'),
      NULL,
      FALSE,
      $this->carimbo(),
    ), 'UID');
    $outraEntidade = $this->propriedade($this->builder->build('node:8', 'X', '', $this->inicio(), NULL, FALSE, $this->carimbo()), 'UID');

    $this->assertNotSame($base, $outraData);
    $this->assertNotSame($base, $outraEntidade);
    $this->assertStringEndsWith('@aculta.org', $base[0]);
  }

  /**
   * Dia inteiro: DTSTART com VALUE=DATE e DTEND exclusivo.
   */
  public function testDiaInteiroComDtendExclusivo(): void {
    $ics = $this->builder->build(
      'node:3',
      'Feira',
      '',
      new \DateTimeImmutable('2026-10-28', new \DateTimeZone('America/Sao_Paulo')),
      new \DateTimeImmutable('2026-10-29', new \DateTimeZone('America/Sao_Paulo')),
      TRUE,
      $this->carimbo(),
    );

    $this->assertSame(['DTSTART;VALUE=DATE:20261028'], $this->propriedade($ics, 'DTSTART'));
    $this->assertSame(['DTEND;VALUE=DATE:20261029'], $this->propriedade($ics, 'DTEND'));
  }

  /**
   * Dia inteiro sem fim: o DTEND é o dia seguinte.
   */
  public function testDiaInteiroSemFimUsaDiaSeguinte(): void {
    $ics = $this->builder->build(
      'node:3',
      'Feira',
      '',
      new \DateTimeImmutable('2026-12-31', new \DateTimeZone('America/Sao_Paulo')),
      NULL,
      TRUE,
      $this->carimbo(),
    );

    $this->assertSame(['DTEND;VALUE=DATE:20270101'], $this->propriedade($ics, 'DTEND'));
  }

  /**
   * Evento com horário sai no horário de Brasília (TZID), com VTIMEZONE; DTSTAMP segue em UTC.
   */
  public function testHorarioEmBrasiliaComVtimezoneEDtstampEmUtc(): void {
    $ics = $this->builder->build(
      'node:4',
      'Sessão',
      '',
      $this->inicio(),
      new \DateTimeImmutable('2026-10-28 21:00:00', new \DateTimeZone('America/Sao_Paulo')),
      FALSE,
      $this->carimbo(),
    );

    $this->assertSame(['DTSTART;TZID=America/Sao_Paulo:20261028T190000'], $this->propriedade($ics, 'DTSTART'));
    $this->assertSame(['DTEND;TZID=America/Sao_Paulo:20261028T210000'], $this->propriedade($ics, 'DTEND'));
    $this->assertSame(['DTSTAMP:20261028T120000Z'], $this->propriedade($ics, 'DTSTAMP'));
    $this->assertStringContainsString("BEGIN:VTIMEZONE\r\nTZID:America/Sao_Paulo\r\n", $ics);
    $this->assertStringContainsString("TZOFFSETFROM:-0300\r\nTZOFFSETTO:-0300\r\n", $ics);
  }

  /**
   * Horário informado em outro fuso é convertido para Brasília.
   */
  public function testHorarioDeOutroFusoEConvertidoParaBrasilia(): void {
    $ics = $this->builder->build(
      'node:4',
      'Sessão',
      '',
      new \DateTimeImmutable('2026-10-28 22:00:00', new \DateTimeZone('UTC')),
      NULL,
      FALSE,
      $this->carimbo(),
    );

    $this->assertSame(['DTSTART;TZID=America/Sao_Paulo:20261028T190000'], $this->propriedade($ics, 'DTSTART'));
    $this->assertSame([], $this->propriedade($ics, 'DTEND'));
  }

  /**
   * Linha longa é dobrada em 75 octetos, e nenhum caractere UTF-8 é cortado.
   */
  public function testLinhaLongaEDobradaEmSetentaECincoOctetos(): void {
    $titulo = str_repeat('ã', 60) . str_repeat('a', 20);
    $ics = $this->builder->build('node:5', $titulo, '', $this->inicio(), NULL, FALSE, $this->carimbo());

    $fisicas = explode("\r\n", $ics);
    $dobradas = array_values(array_filter($fisicas, static fn (string $l): bool => str_starts_with($l, 'SUMMARY:') || str_starts_with($l, ' ')));
    $this->assertGreaterThan(1, count($dobradas));

    foreach ($dobradas as $indice => $linha) {
      $this->assertLessThanOrEqual(75, strlen($linha), "Linha física $indice passa de 75 octetos.");
      $this->assertTrue(mb_check_encoding($linha, 'UTF-8'), "Linha física $indice quebra um caractere UTF-8.");
      if ($indice > 0) {
        $this->assertSame(' ', $linha[0], 'Continuação deve começar com espaço.');
      }
    }

    $this->assertSame(['SUMMARY:' . $titulo], $this->propriedade($ics, 'SUMMARY'));
  }

}
