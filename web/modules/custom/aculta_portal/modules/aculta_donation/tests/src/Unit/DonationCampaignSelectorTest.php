<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_donation\Unit;

use Drupal\aculta_donation\Campaign\DonationCampaignSelector;
use Drupal\aculta_donation\Entity\DonationCampaign;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Janela de vigência das campanhas: início e término inclusivos, sobreposição e ausência.
 */
#[Group('aculta_donation')]
final class DonationCampaignSelectorTest extends UnitTestCase {

  /**
   * Cria uma campanha de doação com período e valores informados.
   *
   * @param string $id
   *   Identificador da campanha.
   * @param string $inicio
   *   Data inicial no formato AAAA-MM-DD.
   * @param string $fim
   *   Data final no formato AAAA-MM-DD.
   *
   * @return \Drupal\aculta_donation\Entity\DonationCampaign
   *   Campanha criada.
   */
  private function campanha(string $id, string $inicio, string $fim): DonationCampaign {
    $campanha = new DonationCampaign([], 'aculta_donation_campaign');
    $campanha->set('id', $id);
    $campanha->setStart($inicio)->setEnd($fim)->setAmounts([10, 20]);
    return $campanha;
  }

  /**
   * Cria o seletor de campanhas com as campanhas informadas.
   *
   * @param \Drupal\aculta_donation\Entity\DonationCampaign ...$campanhas
   *   Campanhas disponíveis para o seletor.
   *
   * @return \Drupal\aculta_donation\Campaign\DonationCampaignSelector
   *   Seletor configurado.
   */
  private function selector(DonationCampaign ...$campanhas): DonationCampaignSelector {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturn($campanhas);
    $tipos = $this->createMock(EntityTypeManagerInterface::class);
    $tipos->method('getStorage')->with('aculta_donation_campaign')->willReturn($storage);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('timezone.default')->willReturn('America/Sao_Paulo');
    $fabrica = $this->createMock(ConfigFactoryInterface::class);
    $fabrica->method('get')->with('system.date')->willReturn($config);

    return new DonationCampaignSelector($tipos, $fabrica);
  }

  /**
   * Converte uma data em um instante fixo do meio-dia no fuso de São Paulo.
   *
   * @param string $data
   *   Data no formato AAAA-MM-DD.
   *
   * @return \DateTimeImmutable
   *   Instante correspondente.
   */
  private function dia(string $data): \DateTimeImmutable {
    return new \DateTimeImmutable($data . ' 12:00:00', new \DateTimeZone('America/Sao_Paulo'));
  }

  /**
   * O início e o término do período da campanha são inclusivos.
   */
  public function testInicioETerminoSaoInclusivos(): void {
    $selector = $this->selector($this->campanha('natal', '2026-12-01', '2026-12-25'));
    $this->assertSame('natal', $selector->vigente($this->dia('2026-12-01'))?->id());
    $this->assertSame('natal', $selector->vigente($this->dia('2026-12-25'))?->id());
  }

  /**
   * Não há campanha vigente fora da janela de vigência.
   */
  public function testForaDaJanelaNaoHaCampanha(): void {
    $selector = $this->selector($this->campanha('natal', '2026-12-01', '2026-12-25'));
    $this->assertNull($selector->vigente($this->dia('2026-11-30')));
    $this->assertNull($selector->vigente($this->dia('2026-12-26')));
  }

  /**
   * Sem campanhas cadastradas não há campanha vigente.
   */
  public function testSemCampanhasNaoHaCampanha(): void {
    $this->assertNull($this->selector()->vigente($this->dia('2026-10-17')));
  }

  /**
   * Em caso de sobreposição, vence a campanha de início mais recente.
   */
  public function testSobreposicaoVenceADeInicioMaisRecente(): void {
    $selector = $this->selector(
      $this->campanha('geral', '2026-10-01', '2026-10-31'),
      $this->campanha('especial', '2026-10-15', '2026-10-20'),
    );
    $this->assertSame('especial', $selector->vigente($this->dia('2026-10-17'))?->id());
    $this->assertSame('geral', $selector->vigente($this->dia('2026-10-05'))?->id());
  }

}
