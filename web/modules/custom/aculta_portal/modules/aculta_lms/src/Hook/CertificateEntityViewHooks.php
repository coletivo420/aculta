<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Hook;

use Drupal\aculta_lms\Entity\CourseCertificate;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Builds the certificate snapshot consistently for browser and PDF output. */
final class CertificateEntityViewHooks {

  public function __construct(
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  #[Hook('entity_view')]
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $viewMode): void {
    if (!$entity instanceof CourseCertificate) {
      return;
    }
    $build = [
      '#type' => 'container',
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => new TranslatableMarkup('Certificado de conclusão'),
      ],
      'intro' => ['#plain_text' => new TranslatableMarkup('Certificamos que')],
      'name' => ['#plain_text' => (string) $entity->get('certificate_name')->value],
      'course_intro' => ['#plain_text' => new TranslatableMarkup('concluiu com aprovação o curso')],
      'course' => ['#plain_text' => (string) $entity->get('course_title')->value],
      'hours' => ['#plain_text' => new TranslatableMarkup('Carga horária: @hours horas', ['@hours' => (int) $entity->get('course_hours')->value])],
      'completed' => ['#plain_text' => new TranslatableMarkup('Conclusão: @date', ['@date' => $this->dateFormatter->format((int) $entity->get('completed')->value, 'long')])],
      'issued' => ['#plain_text' => new TranslatableMarkup('Emitido em: @date', ['@date' => $this->dateFormatter->format((int) $entity->get('issued')->value, 'long')])],
      'verification' => ['#plain_text' => new TranslatableMarkup('Código de verificação: @code', ['@code' => $entity->get('verification_code')->value])],
      '#cache' => ['max-age' => 0],
    ];
    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($entity)
      ->applyTo($build);
  }

}
