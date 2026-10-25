<?php

declare(strict_types=1);

namespace AcultaDeployer;

/**
 * Transformação de build de produção: remove aliases de teste e substitui o
 * sufixo de host de teste pelo de produção, somente nos arquivos do escopo.
 */
final class Transform {

  /** @param array<string, mixed> $config Conteúdo de config/deploy.json. */
  public function __construct(private readonly array $config) {}

  /** @return string[] Erros de configuração; vazio quando o escopo é válido. */
  public function validate(): array {
    $errors = [];
    foreach (['scope', 'drop'] as $key) {
      if (!isset($this->config[$key]) || !is_array($this->config[$key])) {
        $errors[] = "deploy.json: lista \"$key\" ausente";
        continue;
      }
      foreach ($this->config[$key] as $pattern) {
        if (!is_string($pattern) || @preg_match('#' . $pattern . '#', '') === false) {
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
   * Erros da política por ambiente: cada arquivo que muda entre ambientes (environment_bound) precisa de uma
   * regra em todos os perfis, e toda regra precisa apontar para um arquivo do escopo e ter padrão válido.
   *
   * @return string[]
   */
  public function validateProfiles(): array {
    $errors = [];
    $profiles = $this->config['profiles'] ?? null;
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
      $rules = is_array($profile) ? ($profile['rules'] ?? null) : null;
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
        if (@preg_match('#' . $rule['pattern'] . '#m', '') === false) {
          $errors[] = "deploy.json: perfil \"$env\" regra $i com padrão inválido";
        }
        if (!$this->inScope($rule['file'])) {
          $errors[] = "deploy.json: perfil \"$env\" regra $i aponta para fora do escopo: {$rule['file']}";
        }
        $covered[$rule['file']] = true;
      }
      foreach ($bound as $file) {
        // Na produção, o arquivo removido pela lista drop não é gerado: não precisa de regra.
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

  /** @return array<int, array{pattern: string, with: string}> Regras de um perfil para um arquivo. */
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
   * Aplica as regras do perfil ao conteúdo. Cada regra precisa casar exatamente uma linha; zero ou
   * mais de uma casa falha, para que nenhuma mudança de ambiente passe em silêncio.
   *
   * @param array<int, array{pattern: string, with: string}> $rules
   * @return string Conteúdo com as regras aplicadas.
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

  public function inScope(string $relPath): bool {
    foreach ($this->config['scope'] as $pattern) {
      if (preg_match('#' . $pattern . '#', $relPath) === 1) {
        return true;
      }
    }
    return false;
  }

  public function isDropped(string $relPath): bool {
    foreach ($this->config['drop'] as $pattern) {
      if (preg_match('#' . $pattern . '#', $relPath) === 1) {
        return true;
      }
    }
    return false;
  }

  /** Aplica as regras de host ao conteúdo. Retorna [conteúdo, nº de substituições]. */
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

  /** Conta ocorrências de hosts de teste em um conteúdo, sem alterá-lo. */
  public function countTestHosts(string $content): int {
    return $this->applyHosts($content)[1];
  }

}
