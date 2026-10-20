<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Provisionamento de segredos e do webhook do Brevo por ambiente, de forma idempotente.
 *
 * Lógica pura, sem rede: decide o que falta e grava segredos novos sem tocar nos existentes. A CLI faz as chamadas à
 * API do Brevo e lê o arquivo de segredos. Valores de segredo nunca são impressos.
 */
final class Provision {

  /**
   * Segredos gerados pelo deployer quando faltam: nome => formato.
   */
  public const GENERATED = [
    'MAILER_PAYLOAD_KEY' => 'base64-32',
    'BREVO_WEBHOOK_TOKEN' => 'hex-32',
  ];

  /**
   * Nomes de segredos que faltam no arquivo.
   *
   * @param string[] $present
   *   Nomes presentes no arquivo.
   * @param string[] $required
   *   Nomes exigidos pelo ambiente.
   *
   * @return string[]
   *   Nomes que faltam.
   */
  public static function missing(array $present, array $required): array {
    return array_values(array_diff($required, $present));
  }

  /**
   * Valor novo para um segredo gerado.
   */
  public static function generate(string $name): string {
    $format = self::GENERATED[$name] ?? NULL;
    if ($format === NULL) {
      throw new \InvalidArgumentException("Segredo sem gerador: $name");
    }
    return $format === 'base64-32' ? base64_encode(random_bytes(32)) : bin2hex(random_bytes(32));
  }

  /**
   * Ações necessárias, sem executá-las.
   *
   * @param string[] $missingSecrets
   *   Segredos que faltam e serão gerados.
   * @param array|null $webhook
   *   Webhook já cadastrado com a URL do ambiente (ou NULL).
   * @param bool $tokenChanged
   *   TRUE quando o token do webhook foi gerado agora (exige atualizar o cadastro existente).
   * @param int|null $testWebhookId
   *   Cadastro de teste a remover depois que o de produção existe (ou NULL).
   *
   * @return string[]
   *   Ações em ordem de execução.
   */
  public static function plan(array $missingSecrets, ?array $webhook, bool $tokenChanged, ?int $testWebhookId): array {
    $actions = [];
    foreach ($missingSecrets as $name) {
      $actions[] = "gerar e gravar $name (faltava no arquivo)";
    }
    if ($webhook === NULL) {
      $actions[] = 'cadastrar webhook transacional com a URL do ambiente';
    }
    elseif ($tokenChanged) {
      $actions[] = 'atualizar o token do webhook existente (token novo)';
    }
    else {
      $actions[] = 'webhook já cadastrado; nada a fazer';
    }
    if ($testWebhookId !== NULL) {
      $actions[] = "remover o cadastro de teste $testWebhookId";
    }
    return $actions;
  }

  /**
   * Acrescenta segredos novos ao arquivo, sem alterar linhas existentes nem as permissões.
   *
   * @param string $path
   *   Caminho do arquivo de segredos.
   * @param array<string, string> $values
   *   Nome => valor, somente para nomes que faltam.
   */
  public static function append(string $path, array $values): void {
    $handle = fopen($path, 'c+');
    if ($handle === FALSE) {
      throw new \RuntimeException('Arquivo de segredos inacessível para escrita.');
    }
    try {
      flock($handle, LOCK_EX);
      fseek($handle, 0, SEEK_END);
      $size = ftell($handle);
      if ($size > 0) {
        fseek($handle, -1, SEEK_END);
        if (fgetc($handle) !== "\n") {
          fwrite($handle, "\n");
        }
      }
      foreach ($values as $name => $value) {
        fwrite($handle, $name . '=' . $value . "\n");
      }
      fflush($handle);
      flock($handle, LOCK_UN);
    }
    finally {
      fclose($handle);
    }
  }

  /**
   * Nomes de variáveis definidos no arquivo (sem valores).
   *
   * @return string[]
   *   Nomes definidos no arquivo.
   */
  public static function namesIn(string $path): array {
    $names = [];
    foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [] as $line) {
      if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $m) === 1) {
        $names[] = $m[1];
      }
    }
    return array_values(array_unique($names));
  }

  /**
   * Valor de uma variável do arquivo, lido só para uso em memória. NULL quando ausente.
   */
  public static function valueOf(string $path, string $name): ?string {
    foreach (preg_split('/\r?\n/', (string) file_get_contents($path)) ?: [] as $line) {
      if (str_starts_with($line, $name . '=')) {
        return substr($line, strlen($name) + 1);
      }
    }
    return NULL;
  }

}
