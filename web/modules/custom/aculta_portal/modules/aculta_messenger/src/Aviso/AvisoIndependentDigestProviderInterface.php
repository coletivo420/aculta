<?php

declare(strict_types=1);

namespace Drupal\aculta_messenger\Aviso;

/**
 * Marks a provider whose digest opt-in is independent of the daily digest. */
interface AvisoIndependentDigestProviderInterface extends AvisoDigestProviderInterface {}
