<?php

declare(strict_types=1);

namespace Drupal\aculta_qrcode\Service;

use Drupal\aculta_qrcode\Entity\QrCode;
use Drupal\aculta_qrcode\Exception\InvalidQrCodeException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Criação, alteração e busca de códigos QR dinâmicos.
 *
 * Toda gravação passa por este serviço: valida o destino e a janela de
 * validade, gera código público único e cria revisão com registro
 * administrativo.
 */
final class QrCodeManager {

  /**
   * Tentativas de geração de código antes de desistir.
   */
  public const CODE_ATTEMPTS = 5;

  /**
   * Alfabeto do código público: minúsculas e dígitos.
   */
  private const CODE_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

  /**
   * Cria o serviço.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QrCodePayloadValidator $payloadValidator,
    private readonly QrCodePresetRegistry $presets,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Cria um código dinâmico publicado, com o usuário atual como dono.
   *
   * @param string $title
   *   Nome administrativo.
   * @param string $targetUrl
   *   Destino `http` ou `https`.
   * @param string|null $presetId
   *   Preset visual; padrão quando NULL.
   * @param int|null $validFrom
   *   Início da validade (timestamp) ou NULL.
   * @param int|null $validUntil
   *   Fim da validade (timestamp) ou NULL.
   * @param string|null $log
   *   Mensagem da revisão.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  public function create(string $title, string $targetUrl, ?string $presetId = NULL, ?int $validFrom = NULL, ?int $validUntil = NULL, ?string $log = NULL): QrCode {
    $this->validateWindow($validFrom, $validUntil);
    $entity = $this->storage()->create([
      'title' => $title,
      'uid' => $this->currentUser->id(),
      'code' => $this->generateCode(),
      'target_url' => [['value' => $this->payloadValidator->validateUrl($targetUrl)]],
      'preset' => $this->presets->get($presetId ?? $this->presets->default()->id)->id,
      'valid_from' => self::toStorage($validFrom),
      'valid_until' => self::toStorage($validUntil),
      'status' => TRUE,
    ]);
    return $this->save($entity, $log);
  }

  /**
   * Altera o destino do código sem mudar o código público impresso.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  public function updateTarget(QrCode $entity, string $targetUrl, ?string $log = NULL): QrCode {
    $entity->set('target_url', [['value' => $this->payloadValidator->validateUrl($targetUrl)]]);
    return $this->save($entity, $log);
  }

  /**
   * Ativa ou desativa o código. Desativado responde 404 no redirecionamento.
   */
  public function setPublished(QrCode $entity, bool $published, ?string $log = NULL): QrCode {
    // O setPublished() do Core não recebe argumento e sempre grava TRUE.
    $entity->set('status', $published);
    return $this->save($entity, $log);
  }

  /**
   * Busca o código público apenas se estiver publicado e dentro da validade.
   *
   * Consulta com `accessCheck(FALSE)` de propósito: o redirecionamento é
   * público e a decisão de exibir o destino é a janela de validade, não a
   * permissão.
   */
  public function findActive(string $code, int $now): ?QrCode {
    if (preg_match('/^[a-z0-9]{12}$/', $code) !== 1) {
      return NULL;
    }
    $ids = $this->storage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('code', $code)
      ->condition('status', TRUE)
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      return NULL;
    }
    $entity = $this->storage()->load(reset($ids));
    if (!$entity instanceof QrCode || !$entity->isPublished()) {
      return NULL;
    }
    $from = $entity->getValidFrom();
    $until = $entity->getValidUntil();
    if (($from !== NULL && $now < $from) || ($until !== NULL && $now >= $until)) {
      return NULL;
    }
    return $entity;
  }

  /**
   * Gera um código público que ainda não existe.
   *
   * @throws \RuntimeException
   */
  public function generateCode(): string {
    $alphabet_size = strlen(self::CODE_ALPHABET);
    for ($attempt = 0; $attempt < self::CODE_ATTEMPTS; $attempt++) {
      $code = '';
      for ($i = 0; $i < 12; $i++) {
        $code .= self::CODE_ALPHABET[random_int(0, $alphabet_size - 1)];
      }
      $exists = $this->storage()->getQuery()
        ->accessCheck(FALSE)
        ->condition('code', $code)
        ->count()
        ->execute();
      if ((int) $exists === 0) {
        return $code;
      }
    }
    throw new \RuntimeException('Não foi possível gerar um código QR único.');
  }

  /**
   * Valida a janela de validade (fim posterior ao início).
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  public function validateWindow(?int $from, ?int $until): void {
    if ($from !== NULL && $until !== NULL && $until <= $from) {
      throw new InvalidQrCodeException('O fim da validade deve ser posterior ao início.');
    }
  }

  /**
   * Valida, cria revisão e grava.
   *
   * @throws \Drupal\aculta_qrcode\Exception\InvalidQrCodeException
   */
  private function save(QrCode $entity, ?string $log): QrCode {
    $violations = $entity->validate();
    if ($violations->count() > 0) {
      $violation = $violations->get(0);
      throw new InvalidQrCodeException($violation->getPropertyPath() . ': ' . $violation->getMessage());
    }
    $entity->setNewRevision();
    $entity->setRevisionLogMessage($log ?? '');
    $entity->save();
    return $entity;
  }

  /**
   * Converte timestamp Unix em valor de campo `datetime` (UTC).
   */
  private static function toStorage(?int $timestamp): ?string {
    return $timestamp === NULL ? NULL : gmdate('Y-m-d\TH:i:s', $timestamp);
  }

  /**
   * Storage tipado do código QR dinâmico.
   */
  private function storage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('aculta_qr_code');
  }

}
