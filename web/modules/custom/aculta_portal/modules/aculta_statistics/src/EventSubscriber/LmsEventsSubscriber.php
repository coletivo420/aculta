<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\EventSubscriber;

use Drupal\aculta_statistics\Recorder\StatisticsRecorder;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Conta matrículas, conclusões e certificados do LMS, agregados por curso quando há curso.
 *
 * Usa os eventos de domínio do `aculta_lms` (contratos de notificação, não registros de progresso).
 * Não grava nota, resposta, progresso nem identificador de aluno: a dimensão é só o curso.
 *
 * Conclusão e certificado podem ser publicados de novo quando o registro é salvo outra vez. Por isso
 * cada UUID é contado uma vez, numa chave com validade maior que a retenção.
 *
 * Os nomes dos eventos são textos, não constantes de classe: o módulo não depende de `aculta_lms`
 * e segue funcionando sem ele.
 */
final class LmsEventsSubscriber implements EventSubscriberInterface {

  public const METRIC_ENROLLMENTS = 'lms.enrollments.created';
  public const METRIC_COMPLETIONS = 'lms.courses.completed';
  public const METRIC_CERTIFICATES = 'lms.certificates.issued';

  /**
   * Validade da chave de deduplicação, em segundos (acima da retenção de 90 dias).
   */
  private const DEDUPE_TTL = 400 * 86400;

  private const DEDUPE_COLLECTION = 'aculta_statistics.lms';

  public function __construct(
    private readonly StatisticsRecorder $recorder,
    private readonly KeyValueExpirableFactoryInterface $keyValueFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'Drupal\aculta_lms\Event\CourseEnrollmentEvent' => 'onEnrollment',
      'Drupal\aculta_lms\Event\CourseCompletedEvent' => 'onCompleted',
      'Drupal\aculta_lms\Event\CertificateIssuedEvent' => 'onCertificate',
    ];
  }

  /**
   * Matrícula nova numa turma: conta por curso.
   */
  public function onEnrollment(object $event): void {
    $this->recorder->record(self::METRIC_ENROLLMENTS, (string) $event->courseId);
  }

  /**
   * Conclusão aprovada: conta por curso, uma vez por status.
   */
  public function onCompleted(object $event): void {
    if (!$this->firstTime('completed:' . $event->sourceStatusUuid)) {
      return;
    }
    $this->recorder->record(self::METRIC_COMPLETIONS, (string) $event->courseId);
  }

  /**
   * Certificado emitido: conta o total, uma vez por certificado.
   */
  public function onCertificate(object $event): void {
    if (!$this->firstTime('certificate:' . $event->certificateUuid)) {
      return;
    }
    $this->recorder->record(self::METRIC_CERTIFICATES);
  }

  /**
   * Marca a chave na primeira vez; devolve FALSE quando ela já existia.
   */
  private function firstTime(string $key): bool {
    return $this->keyValueFactory->get(self::DEDUPE_COLLECTION)->setWithExpireIfNotExists(
      hash('sha256', $key),
      TRUE,
      self::DEDUPE_TTL,
    );
  }

}
