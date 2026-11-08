<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Url;

/**
 * Authorized course data for the account's learning section.
 *
 * The status is normalized by the provider to one of the ACULTA status
 * constants below. Description remains source text and format metadata so the
 * presentation layer can render it through Drupal's processed_text element.
 */
final readonly class AccountCourseSummary {

  public const STATUS_NOT_STARTED = 'not_started';

  public const STATUS_IN_PROGRESS = 'in_progress';

  public const STATUS_COMPLETED = 'completed';

  public const STATUS_FAILED = 'failed';

  public const STATUS_AWAITING_EVALUATION = 'awaiting_evaluation';

  /**
   * Constrói um resumo de curso autorizado para a seção de aprendizagem.
   *
   * @param string $id
   *   Identificador do curso.
   * @param string $label
   *   Rótulo exibido do curso.
   * @param string $description
   *   Descrição do curso em texto de origem.
   * @param string $descriptionFormat
   *   Formato de texto da descrição.
   * @param string $status
   *   Status normalizado do curso.
   * @param int|float|null $score
   *   Nota obtida no curso, quando houver.
   * @param bool $finished
   *   Indica se o curso foi concluído.
   * @param \Drupal\Core\Url|null $url
   *   URL do curso, quando houver.
   * @param list<string> $cacheTags
   *   Cache tags for the authorized course projection.
   */
  public function __construct(
    public string $id,
    public string $label,
    public string $description,
    public string $descriptionFormat,
    public string $status,
    public int|float|null $score,
    public bool $finished,
    public ?Url $url,
    public array $cacheTags = [],
  ) {}

}
