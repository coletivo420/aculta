<?php

declare(strict_types=1);

namespace Drupal\Tests\aculta_forum_lms\Kernel;

use Drupal\Tests\aculta_lms\Kernel\AcultaLmsDependencyHarnessTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the LMS services used by partial Forum Kernel tests.
 */
trait LmsForumDependencyHarnessTrait {

  use AcultaLmsDependencyHarnessTrait;
  use LmsGroupTypesSyncTrait;

  /**
   * {@inheritdoc} */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $this->registerLmsDependencyServices($container);
  }

}
