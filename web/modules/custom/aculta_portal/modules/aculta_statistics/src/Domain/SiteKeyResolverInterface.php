<?php

declare(strict_types=1);

namespace Drupal\aculta_statistics\Domain;

/**
 * Purpose (identificador estável de domínio) da requisição atual.
 */
interface PurposeResolverInterface {

  /**
   * Purpose atual, por exemplo `main`, ou NULL quando não há purpose conhecido.
   */
  public function currentPurpose(): ?string;

}
