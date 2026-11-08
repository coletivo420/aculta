<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\Core\Cache\CacheableMetadata;

/**
 * Authorized learning summary without provider entities or render arrays.
 */
final readonly class StudentLearningSummary {

  /**
   * Constrói o resumo de aprendizagem da conta.
   *
   * @param array<int, \Drupal\aculta_lms\Learning\StudentLearningCourse> $courses
   *   Cursos visíveis para esta conta.
   * @param \Drupal\aculta_lms\Learning\StudentLearningCourse|null $continueCourse
   *   Curso a continuar, ou NULL quando não houver.
   * @param array<int, \Drupal\aculta_lms\Learning\StudentLearningCertificate> $certificates
   *   Certificados visíveis para esta conta.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Metadados de cache do resumo.
   */
  public function __construct(
    public array $courses,
    public ?StudentLearningCourse $continueCourse,
    public array $certificates,
    public CacheableMetadata $cache,
  ) {}

}
