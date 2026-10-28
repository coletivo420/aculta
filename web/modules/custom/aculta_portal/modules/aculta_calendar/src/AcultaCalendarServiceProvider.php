<?php

declare(strict_types=1);

namespace Drupal\aculta_calendar;

use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\aculta_calendar\Import\IcsCalendarFileReader;
use Drupal\aculta_calendar\Import\CalendarImportRepository;
use Drupal\aculta_calendar\Import\CalendarEventNormalizer;
use Drupal\aculta_calendar\Import\CalendarImportProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/** Registers the import repository without editing parallel calendar services. */
final class AcultaCalendarServiceProvider extends ServiceProviderBase {

  /** {@inheritdoc} */
  public function alter(ContainerBuilder $container): void {
    $container->register('aculta_calendar.import_repository', CalendarImportRepository::class)
      ->addArgument(new Reference('entity_type.manager'));
    $container->register('aculta_calendar.import_reader', IcsCalendarFileReader::class);
    $container->register('aculta_calendar.import_normalizer', CalendarEventNormalizer::class);
    $container->register('aculta_calendar.import_processor', CalendarImportProcessor::class)
      ->addArgument(new Reference('aculta_calendar.import_repository'));
  }

}
