<?php

declare(strict_types=1);

namespace Drupal\aculta_mailer\Monitor;

/**
 * Mensagem da caixa maior que o teto de download.
 *
 * O monitor trata esta exceção como mensagem descartada: avança o cursor e segue,
 * para que uma mensagem grande não bloqueie a fila de UIDs.
 */
final class ImapMessageTooLargeException extends \RuntimeException {}
