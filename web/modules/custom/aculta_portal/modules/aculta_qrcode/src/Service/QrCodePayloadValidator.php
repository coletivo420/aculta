<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;

/**
 * Valida o conteúdo codificado em um QR Code estático.
 *
 * Só URLs `http` e `https` e texto simples são aceitos. Esquemas executáveis
 * (`javascript:`, `data:`, `file:`) e credenciais embutidas na URL são
 * rejeitados. Nenhum conteúdo sai do servidor para validação externa.
 */
final class QrCodePayloadValidator {

  /**
   * Tamanho máximo, em bytes, de uma URL codificada.
   */
  public const MAX_URL_LENGTH = 2048;

  /**
   * Tamanho máximo, em caracteres, de um texto codificado.
   */
  public const MAX_TEXT_LENGTH = 1000;

  /**
   * Valida e normaliza uma URL absoluta `http` ou `https`.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  public function validateUrl(string $url): string {
    $url = trim($url);
    if ($url === '' || strlen($url) > self::MAX_URL_LENGTH) {
      throw new InvalidQrCodeException('URL vazia ou longa demais para um QR Code.');
    }
    $parts = parse_url($url);
    if ($parts === FALSE || !isset($parts['scheme'], $parts['host'])) {
      throw new InvalidQrCodeException('A URL precisa ser absoluta, com esquema e host.');
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], TRUE)) {
      throw new InvalidQrCodeException('Somente URLs http e https são aceitas.');
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
      throw new InvalidQrCodeException('URL com credenciais embutidas não é aceita.');
    }
    return $url;
  }

  /**
   * Valida um texto simples, sem caracteres de controle.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  public function validateText(string $text): string {
    if (trim($text) === '' || mb_strlen($text) > self::MAX_TEXT_LENGTH) {
      throw new InvalidQrCodeException('Texto vazio ou longo demais para um QR Code.');
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text) === 1) {
      throw new InvalidQrCodeException('O texto contém caracteres de controle.');
    }
    return $text;
  }

}
