<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Transformação de conteúdo para cada ambiente.
 *
 * Remove aliases de teste e substitui hosts somente nos arquivos do escopo.
 */
final class Transform {

  /**
   * Cria a transformação com as regras do deploy.
   *
   * @param array<string, mixed> $config
   *   Conteúdo de config/deploy.json.
   */
  public function __construct(private readonly array $config) {}

  /**
   * Valida a configuração básica da transformação.
   *
   * @return string[]
   *   Erros de configuração; vazio quando o escopo é válido.
   */
  public function validate(): array {
    $errors = [];
    foreach (['scope', 'drop'] as $key) {
      if (!isset($this->config[$key]) || !is_array($this->config[$key])) {
        $errors[] = "deploy.json: lista \"$key\" ausente";
        continue;
      }
      foreach ($this->config[$key] as $pattern) {
        if (!is_string($pattern) || @preg_match('#' . $pattern . '#', '') === FALSE) {
          $errors[] = "deploy.json: padrão inválido em \"$key\": " . json_encode($pattern);
        }
      }
    }
    if (empty($this->config['host_rules']) || !is_array($this->config['host_rules'])) {
      $errors[] = 'deploy.json: host_rules ausente';
    }
    foreach ($this->config['host_rules'] ?? [] as $i => $rule) {
      foreach (['from', 'to'] as $f) {
        if (!isset($rule[$f]) || !is_string($rule[$f]) || $rule[$f] === '') {
          $errors[] = "deploy.json: host_rules[$i] sem \"$f\"";
        }
      }
    }
    return $errors;
  }

  /**
   * Valida regras declaradas para arquivos dependentes do ambiente.
   *
   * Arquivos no escopo precisam de regra em cada perfil. Toda regra deve
   * apontar para um arquivo do escopo e usar um padrão válido.
   *
   * @return string[]
   *   Erros encontrados; vazio quando todos os perfis são válidos.
   */
  public function validateProfiles(): array {
    $errors = [];
    $profiles = $this->config['profiles'] ?? NULL;
    if (!is_array($profiles) || $profiles === []) {
      return ['deploy.json: perfis por ambiente ausentes ("profiles")'];
    }
    $bound = [];
    foreach ($this->config['environment_bound'] ?? [] as $i => $entry) {
      if (!is_array($entry) || !isset($entry['file']) || !is_string($entry['file'])) {
        $errors[] = "deploy.json: environment_bound[$i] sem \"file\"";
        continue;
      }
      $bound[] = $entry['file'];
    }
    if ($bound === []) {
      $errors[] = 'deploy.json: environment_bound vazio (nenhum arquivo declarado como dependente de ambiente)';
    }
    foreach ($profiles as $env => $profile) {
      $rules = is_array($profile) ? ($profile['rules'] ?? NULL) : NULL;
      if (!is_array($rules)) {
        $errors[] = "deploy.json: perfil \"$env\" sem lista \"rules\"";
        continue;
      }
      $covered = [];
      foreach ($rules as $i => $rule) {
        if (!is_array($rule) || !isset($rule['file'], $rule['pattern'], $rule['with']) || !is_string($rule['file']) || !is_string($rule['pattern']) || !is_string($rule['with'])) {
          $errors[] = "deploy.json: perfil \"$env\" regra $i incompleta (file, pattern, with)";
          continue;
        }
        if (@preg_match('#' . $rule['pattern'] . '#m', '') === FALSE) {
          $errors[] = "deploy.json: perfil \"$env\" regra $i com padrão inválido";
        }
        if (!$this->inScope($rule['file'])) {
          $errors[] = "deploy.json: perfil \"$env\" regra $i aponta para fora do escopo: {$rule['file']}";
        }
        $covered[$rule['file']] = TRUE;
      }
      foreach ($bound as $file) {
        // Arquivos fora do escopo são observados por outras políticas, mas não
        // passam pela transformação de perfis durante o build.
        if (!$this->inScope($file)) {
          continue;
        }
        // Arquivos removidos no build de produção não precisam de regra.
        if ($env === 'production' && $this->isDropped($file)) {
          continue;
        }
        if (!isset($covered[$file])) {
          $errors[] = "deploy.json: perfil \"$env\" sem regra para arquivo dependente de ambiente: $file";
        }
      }
    }
    return $errors;
  }

  /**
   * Retorna as regras de perfil associadas a um arquivo.
   *
   * @return array<int, array{pattern: string, with: string}>
   *   Regras de substituição para o arquivo.
   */
  public function profileRules(string $target, string $relPath): array {
    $rules = [];
    foreach ($this->config['profiles'][$target]['rules'] ?? [] as $rule) {
      if ($rule['file'] === $relPath) {
        $rules[] = ['pattern' => $rule['pattern'], 'with' => $rule['with']];
      }
    }
    return $rules;
  }

  /**
   * Aplica regras do perfil ao conteúdo, exigindo correspondência única.
   *
   * Zero ou múltiplas correspondências causam falha para que mudanças de
   * ambiente não passem em silêncio.
   *
   * @param string $content
   *   Conteúdo de origem.
   * @param array<int, array{pattern: string, with: string}> $rules
   *   Regras de substituição a aplicar.
   * @param string $relPath
   *   Caminho usado na mensagem de erro.
   *
   * @return string
   *   Conteúdo com as regras aplicadas.
   */
  public function applyProfile(string $content, array $rules, string $relPath): string {
    foreach ($rules as $rule) {
      $pattern = '#' . $rule['pattern'] . '#m';
      $matches = preg_match_all($pattern, $content);
      if ($matches !== 1) {
        throw new \RuntimeException("regra de perfil casou $matches vez(es) em $relPath: {$rule['pattern']}");
      }
      $with = $rule['with'];
      $content = (string) preg_replace_callback($pattern, static fn(): string => $with, $content, 1);
    }
    return $content;
  }

  /**
   * Informa se o caminho está dentro do escopo de transformação.
   *
   * @param string $relPath
   *   Caminho relativo à raiz do repositório.
   *
   * @return bool
   *   TRUE quando o caminho pertence ao escopo.
   */
  public function inScope(string $relPath): bool {
    foreach ($this->config['scope'] as $pattern) {
      if (preg_match('#' . $pattern . '#', $relPath) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Informa se o caminho será removido do build de produção.
   *
   * @param string $relPath
   *   Caminho relativo à raiz do repositório.
   *
   * @return bool
   *   TRUE quando o caminho corresponde a uma regra de remoção.
   */
  public function isDropped(string $relPath): bool {
    foreach ($this->config['drop'] as $pattern) {
      if (preg_match('#' . $pattern . '#', $relPath) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Aplica as regras de host ao conteúdo. Retorna [conteúdo, nº de substituições]. */
  public function applyHosts(string $content): array {
    $total = 0;
    foreach ($this->config['host_rules'] as $rule) {
      $from = preg_quote((string) $rule['from'], '#');
      // Host boundary: no letter, digit, hyphen or '.label' may follow, so
      // 'aculta.toca.net.br.evil.com' is not rewritten.
      $pattern = '#(?<![A-Za-z0-9.-])((?:[a-z0-9-]+\.)*)' . $from . '(?![A-Za-z0-9-]|\.[A-Za-z0-9-])#i';
      $content = preg_replace($pattern, '${1}' . $rule['to'], $content, -1, $count);
      $total += $count;
    }
    return [$content, $total];
  }

  /**
   * Conta ocorrências de hosts de teste em um conteúdo, sem alterá-lo. */
  public function countTestHosts(string $content): int {
    return $this->applyHosts($content)[1];
  }

}
