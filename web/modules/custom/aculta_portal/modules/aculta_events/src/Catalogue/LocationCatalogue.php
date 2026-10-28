<?php

declare(strict_types=1);

namespace Drupal\aculta_events\Catalogue;

use Drupal\aculta_events\Entity\EventLocation;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Lista pública de locais (/locais), com filtro por tipo (F4).
 *
 * Privacidade (MODELO-DE-DADOS.md): local com precisão "aproximado" mostra só bairro e município;
 * a rua e o CEP não saem. Endereço completo só para local exato.
 */
final class LocationCatalogue {

  /**
   * Construtor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Locais, opcionalmente de um tipo, em ordem alfabética.
   *
   * @return array<int, array{local: \Drupal\aculta_events\Entity\EventLocation, endereco: string}>
   */
  public function busca(?int $tipo): array {
    $storage = $this->entityTypeManager->getStorage('aculta_event_location');
    $consulta = $storage->getQuery()->sort('title')->accessCheck(FALSE);
    if ($tipo !== NULL) {
      $consulta->condition('field_tipo.target_id', $tipo);
    }
    $saida = [];
    foreach ($storage->loadMultiple($consulta->execute()) as $local) {
      if ($local instanceof EventLocation) {
        $saida[] = ['local' => $local, 'endereco' => $this->enderecoPublico($local)];
      }
    }
    return $saida;
  }

  /**
   * Endereço para exibição, conforme a precisão do local (privacidade do modelo).
   */
  public function enderecoPublico(EventLocation $local): string {
    $item = $local->get('field_endereco')->first();
    if ($item === NULL) {
      return '';
    }
    $e = $item->getValue();
    $bairroMunicipio = array_filter([
      (string) ($e['dependent_locality'] ?? ''),
      (string) ($e['locality'] ?? ''),
      (string) ($e['administrative_area'] ?? ''),
    ]);
    if ((string) $local->get('field_precisao')->value === 'aproximado') {
      return implode(' — ', $bairroMunicipio);
    }
    $cep = preg_replace('/\D/', '', (string) ($e['postal_code'] ?? ''));
    $cepFormatado = strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep;
    $partes = array_filter([
      (string) ($e['address_line1'] ?? ''),
      (string) ($e['address_line2'] ?? ''),
      implode(' — ', $bairroMunicipio),
      $cepFormatado !== '' ? 'CEP ' . $cepFormatado : '',
    ]);
    return implode(', ', $partes);
  }

}
