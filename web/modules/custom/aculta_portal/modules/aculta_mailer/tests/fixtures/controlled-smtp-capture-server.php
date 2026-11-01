<?php

/**
 * @file
 * Coletor SMTP de mensagem única para testes locais.
 */

declare(strict_types=1);

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === FALSE) {
  fwrite(STDERR, $errorMessage . "\n");
  exit(1);
}
$address = stream_socket_get_name($server, FALSE);
fwrite(STDOUT, substr(strrchr((string) $address, ':'), 1) . "\n");
fflush(STDOUT);
$client = stream_socket_accept($server, 10);
if ($client === FALSE) {
  exit(2);
}
stream_set_timeout($client, 10);
$transcript = '';
$write = static function (string $response) use ($client): void {
  fwrite($client, $response . "\r\n");
};
$write('220 localhost controlled test sink');
while (($line = fgets($client)) !== FALSE) {
  $transcript .= $line;
  $command = strtoupper(strtok(trim($line), " \r\n") ?: '');
  if ($command === 'EHLO' || $command === 'HELO') {
    $write('250-localhost');
    $write('250 SIZE 1000000');
  }
  elseif ($command === 'MAIL' || $command === 'RCPT' || $command === 'RSET' || $command === 'NOOP') {
    $write('250 accepted');
  }
  elseif ($command === 'DATA') {
    $write('354 end with <CRLF>.<CRLF>');
    while (($dataLine = fgets($client)) !== FALSE) {
      $transcript .= $dataLine;
      if ($dataLine === ".\r\n") {
        break;
      }
    }
    $write('250 queued');
  }
  elseif ($command === 'QUIT') {
    $write('221 bye');
    break;
  }
  else {
    $write('500 unsupported command');
  }
}
fclose($client);
fclose($server);
file_put_contents($argv[1], $transcript, LOCK_EX);
