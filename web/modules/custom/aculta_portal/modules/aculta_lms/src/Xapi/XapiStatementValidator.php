<?php

declare(strict_types=1);

namespace Drupal\aculta_lms\Xapi;

/**
 * Valida uma declaração xAPI 2.0 antes de o LRS aceitá-la (fase F1).
 *
 * Função pura, sem Drupal. Segue o texto da especificação xAPI 2.0 (Data)
 * para os pontos de validação, com estas escolhas de ingestão, que falham
 * fechado:
 * - propriedades desconhecidas são recusadas, não descartadas;
 * - grupos, SubStatement e attachments são recusados nesta fase;
 * - o tamanho total da declaração tem teto.
 *
 * Campos atribuídos pelo LRS (stored, authority) não são aceitos vindos do
 * cliente, porque o LRS os define.
 */
final class XapiStatementValidator {

  /**
   * Tamanho máximo da declaração serializada, em bytes.
   */
  public const MAX_STATEMENT_BYTES = 65536;

  /**
   * Propriedades de topo permitidas no envio do cliente.
   */
  private const TOP_LEVEL = ['id', 'actor', 'verb', 'object', 'result', 'context', 'timestamp'];

  /**
   * Identificadores permitidos para um Agent (exatamente um).
   */
  private const IFI = ['mbox', 'mbox_sha1sum', 'openid', 'account'];

  /**
   * Propriedades permitidas em result.
   */
  private const RESULT = ['score', 'success', 'completion', 'response', 'duration', 'extensions'];

  /**
   * Propriedades permitidas em result.score.
   */
  private const SCORE = ['scaled', 'raw', 'min', 'max'];

  /**
   * Propriedades permitidas em context.
   */
  private const CONTEXT = ['registration', 'instructor', 'team', 'contextActivities', 'revision', 'platform', 'language', 'statement', 'extensions'];

  /**
   * Valida e devolve a declaração normalizada.
   *
   * @param array<string, mixed> $statement
   *   Declaração já decodificada do JSON recebido.
   *
   * @return array<string, mixed>
   *   Declaração normalizada e validada.
   *
   * @throws \UnexpectedValueException
   *   Quando a declaração não atende à especificação ou aos limites.
   */
  public function validate(array $statement): array {
    $encoded = json_encode($statement);
    if ($encoded === FALSE || strlen($encoded) > self::MAX_STATEMENT_BYTES) {
      throw new \UnexpectedValueException('Declaração acima do tamanho máximo.');
    }
    $this->onlyAllowed($statement, self::TOP_LEVEL, 'declaração');

    foreach (['actor', 'verb', 'object'] as $required) {
      if (!array_key_exists($required, $statement)) {
        throw new \UnexpectedValueException("A declaração exige {$required}.");
      }
    }

    if (array_key_exists('id', $statement) && !$this->isUuid($statement['id'])) {
      throw new \UnexpectedValueException('id deve ser um UUID.');
    }
    if (array_key_exists('timestamp', $statement) && !$this->isIso8601($statement['timestamp'])) {
      throw new \UnexpectedValueException('timestamp deve estar no formato ISO 8601.');
    }

    $statement['actor'] = $this->actor($statement['actor']);
    $statement['verb'] = $this->verb($statement['verb']);
    $statement['object'] = $this->object($statement['object']);

    if (array_key_exists('result', $statement)) {
      $statement['result'] = $this->result($statement['result']);
    }
    if (array_key_exists('context', $statement)) {
      $this->context($statement['context']);
    }

    return $statement;
  }

  /**
   * Actor: exatamente um identificador inverso funcional, e somente Agent.
   */
  private function actor(mixed $actor): array {
    if (!is_array($actor) || array_is_list($actor)) {
      throw new \UnexpectedValueException('actor deve ser um objeto.');
    }
    if (isset($actor['objectType']) && $actor['objectType'] !== 'Agent') {
      throw new \UnexpectedValueException('Grupos não são aceitos nesta fase.');
    }
    $present = array_values(array_intersect(self::IFI, array_keys($actor)));
    if (count($present) !== 1) {
      throw new \UnexpectedValueException('actor deve ter exatamente um identificador inverso funcional.');
    }
    $this->onlyAllowed($actor, array_merge(self::IFI, ['objectType', 'name']), 'actor');

    $ifi = $present[0];
    $value = $actor[$ifi];
    match ($ifi) {
      'mbox' => $this->requireMatch($value, '/^mailto:[^\s@]+@[^\s@]+$/', 'mbox deve ser um mailto IRI'),
      'mbox_sha1sum' => $this->requireMatch($value, '/^[0-9a-f]{40}$/i', 'mbox_sha1sum deve ser um SHA1 hexadecimal'),
      'openid' => $this->requireIri($value, 'openid'),
      'account' => $this->account($value),
    };
    return $actor;
  }

  /**
   * Account: homePage (IRI) e name (texto), ambos obrigatórios.
   */
  private function account(mixed $account): void {
    if (!is_array($account) || !isset($account['homePage'], $account['name'])) {
      throw new \UnexpectedValueException('account exige homePage e name.');
    }
    $this->requireIri($account['homePage'], 'account.homePage');
    if (!is_string($account['name']) || $account['name'] === '') {
      throw new \UnexpectedValueException('account.name deve ser texto não vazio.');
    }
  }

  /**
   * Verb: id IRI obrigatório; display opcional.
   */
  private function verb(mixed $verb): array {
    if (!is_array($verb) || array_is_list($verb)) {
      throw new \UnexpectedValueException('verb deve ser um objeto.');
    }
    $this->onlyAllowed($verb, ['id', 'display'], 'verb');
    if (!isset($verb['id'])) {
      throw new \UnexpectedValueException('verb exige id.');
    }
    $this->requireIri($verb['id'], 'verb.id');
    if (isset($verb['display']) && !is_array($verb['display'])) {
      throw new \UnexpectedValueException('verb.display deve ser um objeto de idiomas.');
    }
    return $verb;
  }

  /**
   * Object: Activity ou StatementRef. Outros tipos são recusados nesta fase.
   */
  private function object(mixed $object): array {
    if (!is_array($object) || array_is_list($object)) {
      throw new \UnexpectedValueException('object deve ser um objeto.');
    }
    $type = $object['objectType'] ?? 'Activity';
    if ($type === 'StatementRef') {
      $this->onlyAllowed($object, ['objectType', 'id'], 'object');
      if (!isset($object['id']) || !$this->isUuid($object['id'])) {
        throw new \UnexpectedValueException('StatementRef exige id em UUID.');
      }
      return $object;
    }
    if ($type !== 'Activity') {
      throw new \UnexpectedValueException('Apenas Activity e StatementRef são aceitos nesta fase.');
    }
    $this->onlyAllowed($object, ['objectType', 'id', 'definition'], 'object');
    if (!isset($object['id'])) {
      throw new \UnexpectedValueException('Activity exige id.');
    }
    $this->requireIri($object['id'], 'object.id');
    if (isset($object['definition']) && !is_array($object['definition'])) {
      throw new \UnexpectedValueException('object.definition deve ser um objeto.');
    }
    return $object;
  }

  /**
   * Result: score, success, completion, response, duration e extensions.
   */
  private function result(mixed $result): array {
    if (!is_array($result) || array_is_list($result)) {
      throw new \UnexpectedValueException('result deve ser um objeto.');
    }
    $this->onlyAllowed($result, self::RESULT, 'result');

    if (isset($result['score'])) {
      $this->score($result['score']);
    }
    foreach (['success', 'completion'] as $flag) {
      if (isset($result[$flag]) && !is_bool($result[$flag])) {
        throw new \UnexpectedValueException("result.{$flag} deve ser booleano.");
      }
    }
    if (isset($result['response']) && !is_string($result['response'])) {
      throw new \UnexpectedValueException('result.response deve ser texto.');
    }
    if (isset($result['duration'])) {
      $this->requireMatch($result['duration'], '/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d+)?S)?)?$/', 'result.duration deve ser uma duração ISO 8601');
    }
    if (isset($result['extensions']) && !is_array($result['extensions'])) {
      throw new \UnexpectedValueException('result.extensions deve ser um objeto.');
    }
    return $result;
  }

  /**
   * Score: scaled entre -1 e 1; min menor que max; raw entre min e max.
   */
  private function score(mixed $score): void {
    if (!is_array($score) || array_is_list($score)) {
      throw new \UnexpectedValueException('result.score deve ser um objeto.');
    }
    $this->onlyAllowed($score, self::SCORE, 'result.score');
    foreach (self::SCORE as $key) {
      if (isset($score[$key]) && !is_numeric($score[$key])) {
        throw new \UnexpectedValueException("result.score.{$key} deve ser numérico.");
      }
    }
    if (isset($score['scaled']) && ($score['scaled'] < -1 || $score['scaled'] > 1)) {
      throw new \UnexpectedValueException('result.score.scaled deve estar entre -1 e 1.');
    }
    if (isset($score['min'], $score['max']) && $score['min'] >= $score['max']) {
      throw new \UnexpectedValueException('result.score.min deve ser menor que max.');
    }
    if (isset($score['raw'])) {
      if (isset($score['min']) && $score['raw'] < $score['min']) {
        throw new \UnexpectedValueException('result.score.raw abaixo de min.');
      }
      if (isset($score['max']) && $score['raw'] > $score['max']) {
        throw new \UnexpectedValueException('result.score.raw acima de max.');
      }
    }
  }

  /**
   * Context: só as propriedades da especificação, com objeto ou lista quando for o caso.
   */
  private function context(mixed $context): void {
    if (!is_array($context) || array_is_list($context)) {
      throw new \UnexpectedValueException('context deve ser um objeto.');
    }
    $this->onlyAllowed($context, self::CONTEXT, 'context');
  }

  /**
   * Recusa chaves fora da lista permitida.
   *
   * @param array<string, mixed> $data
   *   Estrutura a validar.
   * @param string[] $allowed
   *   Chaves permitidas.
   * @param string $where
   *   Nome do contexto usado na mensagem de erro.
   */
  private function onlyAllowed(array $data, array $allowed, string $where): void {
    $unknown = array_diff(array_keys($data), $allowed);
    if ($unknown !== []) {
      throw new \UnexpectedValueException("Propriedade não permitida em {$where}: " . implode(', ', $unknown) . '.');
    }
  }

  /**
   * Indica se o valor é um UUID válido.
   *
   * @param mixed $value
   *   Valor a verificar.
   *
   * @return bool
   *   TRUE quando o valor é um UUID.
   */
  private function isUuid(mixed $value): bool {
    return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
  }

  /**
   * Indica se o valor é uma data ISO 8601 válida.
   *
   * @param mixed $value
   *   Valor a verificar.
   *
   * @return bool
   *   TRUE quando o valor é uma data ISO 8601.
   */
  private function isIso8601(mixed $value): bool {
    return is_string($value)
      && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})$/', $value) === 1
      && strtotime($value) !== FALSE;
  }

  /**
   * Exige que o valor seja um IRI com esquema.
   *
   * @param mixed $value
   *   Valor a validar.
   * @param string $field
   *   Nome do campo usado na mensagem de erro.
   */
  private function requireIri(mixed $value, string $field): void {
    if (!is_string($value) || preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\S+$/', $value) !== 1) {
      throw new \UnexpectedValueException("{$field} deve ser um IRI com esquema.");
    }
  }

  /**
   * Exige que o valor corresponda ao padrão informado.
   *
   * @param mixed $value
   *   Valor a validar.
   * @param string $pattern
   *   Expressão regular esperada.
   * @param string $message
   *   Mensagem base da exceção.
   */
  private function requireMatch(mixed $value, string $pattern, string $message): void {
    if (!is_string($value) || preg_match($pattern, $value) !== 1) {
      throw new \UnexpectedValueException($message . '.');
    }
  }

}
