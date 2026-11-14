<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Learning;

use Drupal\aculta_learning_contracts\Learning\AccountCoursesReaderInterface as ContractAccountCoursesReaderInterface;

/**
 * Reads the authorized course summaries visible to one account.
 *
 * Extende o contrato neutro do Portal; implementações continuam devolvendo
 * DTOs neutros, sem expor entidades ou render arrays do provedor.
 */
interface AccountCoursesReaderInterface extends ContractAccountCoursesReaderInterface {
}
