<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

/**
 * Immutable JSON object stored for one ACULTA answer attempt.
 */
final class AnswerPayload implements \JsonSerializable {

  /**
   * Plugin-specific answer data.
   *
   * @var array<string, mixed>
   */
  private readonly array $data;

  public function __construct(array $data) {
    try {
      $json = json_encode((object) $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $decoded = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('Payload de resposta não pode ser serializado como JSON.', 0, $exception);
    }

    $this->data = $decoded;
  }

  /**
   * Creates a payload from its persisted JSON object.
   */
  public static function fromJson(string $json): self {
    try {
      $root = json_decode($json, FALSE, 512, JSON_THROW_ON_ERROR);
      if (!$root instanceof \stdClass) {
        throw new \InvalidArgumentException('Payload de resposta deve ser um objeto JSON.');
      }
      $data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $exception) {
      throw new \InvalidArgumentException('Payload de resposta contém JSON inválido.', 0, $exception);
    }

    return new self($data);
  }

  /**
   * Returns the plugin-specific payload map.
   *
   * @return array<string, mixed>
   *   The payload data.
   */
  public function toArray(): array {
    return $this->data;
  }

  /**
   * Returns the managed file referenced by a file-upload answer, when present.
   *
   * The payload stores references only. Download access is checked against the
   * owning answer and its current course permissions at request time.
   */
  public function fileId(): ?int {
    $value = $this->data['file_upload']['fids'][0] ?? $this->data['file_upload'][0] ?? NULL;
    if ($value === NULL) {
      return NULL;
    }
    if (!is_numeric($value) || (int) $value <= 0) {
      throw new \UnexpectedValueException('Referência de arquivo da resposta inválida.');
    }
    return (int) $value;
  }

  /**
   * Returns a payload that references one managed file.
   */
  public function withFileId(int $fileId): self {
    if ($fileId <= 0) {
      throw new \InvalidArgumentException('Identificador de arquivo deve ser positivo.');
    }
    return new self([...$this->data, 'file_upload' => ['fids' => [$fileId]]]);
  }

  /**
   * Serializes the payload as a JSON object.
   */
  public function jsonSerialize(): object {
    return (object) $this->data;
  }

  /**
   * Returns the stable JSON representation persisted on an attempt.
   */
  public function toJson(): string {
    try {
      return json_encode($this->jsonSerialize(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    catch (\JsonException $exception) {
      throw new \LogicException('Payload validado deixou de ser serializável.', 0, $exception);
    }
  }

}
