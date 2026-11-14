<?php

declare(strict_types=1);

namespace Drupal\aculta_portal\Domain;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Lê a configuração de superfícies (aculta_portal.superficies) com padrões.
 *
 * Fase 3.4, parcial: a configuração existe e é validada, mas as rotas ainda
 * declaram purpose em YAML. A atribuição por configuração entra depois de
 * teste de rota (DEFERRED).
 */
final class SurfaceConfigReader {

  public const CONFIG_NAME = 'aculta_portal.superficies';

  public const MODOS = ['subdominio', 'diretorio', 'desativado'];

  /**
   * Títulos padrão por purpose (rótulos do formulário). */
  public const TITULOS = [
    'main' => 'Principal',
    'account' => 'Conta',
    'support' => 'Apoio',
    'magazine' => 'Revista',
    'wiki' => 'Wiki',
    'shop' => 'Loja',
    'courses' => 'Cursos',
    'forum' => 'Fórum',
    'links' => 'LinkWeed',
    'eventos' => 'Eventos',
  ];

  /**
   * Slugs que não podem ser usados por superfície. */
  public const RESERVADOS = [
    'painel-administrativo',
    'integracoes',
    'studio',
    'conta-legada',
    'minha-conta',
    'node',
    'user',
  ];

  /**
   * Superfícies que podem ir para diretório (Fase 3.5). MAIN fica de fora.
   */
  public const DIRETORIO_PERMITIDO = [
    'courses',
    'magazine',
    'account',
    'wiki',
    'shop',
    'forum',
    'support',
    'links',
    'eventos',
  ];

  public const PADRAO_SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  /**
   * Interruptor da leitura por configuração nas rotas (Fase 3.4).
   *
   * Desligado por padrão. Ligar exige o teste de rota da 3.0 no Runtime.
   * Enquanto estiver desligado, nenhum comportamento muda.
   */
  public function leituraAtiva(): bool {
    return (bool) ($this->configFactory->get(self::CONFIG_NAME)->get('leitura_ativa') ?? FALSE);
  }

  /**
   * Caminho de diretório configurado (/slug), sem olhar o interruptor.
   *
   * Usado pelo formulário e pela conferência. Retorna NULL se a superfície
   * não estiver em modo diretório, estiver desativada ou tiver slug inválido.
   */
  public function caminhoDiretorioConfigurado(string $purpose): ?string {
    $linha = $this->superficies()[$purpose] ?? NULL;
    if ($linha === NULL || $purpose === DomainSurfaceMap::MAIN || !$linha['ativa'] || $linha['modo'] !== 'diretorio') {
      return NULL;
    }
    if (!in_array($purpose, self::DIRETORIO_PERMITIDO, TRUE)) {
      return NULL;
    }
    $slug = $linha['slug'];
    if (!preg_match(self::PADRAO_SLUG, $slug) || in_array($slug, self::RESERVADOS, TRUE)) {
      return NULL;
    }
    return '/' . $slug;
  }

  /**
   * Caminho de diretório em vigor (Fase 3.5).
   *
   * Só devolve o caminho com leitura_ativa ligada. Com o padrão (desligada),
   * retorna NULL e nenhum comportamento muda. Hoje a resolução de /slug no
   * MAIN ainda não existe: ver docs do submódulo aculta_paths (DEFERRED).
   */
  public function caminhoDiretorioAtivo(string $purpose): ?string {
    if (!$this->leituraAtiva()) {
      return NULL;
    }
    return $this->caminhoDiretorioConfigurado($purpose);
  }

  /**
   * Superfícies por purpose, com padrões onde a configuração não define.
   *
   * @return array<string, array{modo: string, ativa: bool, slug: string, titulo: string}>
   *   Superfícies por purpose, com os padrões aplicados.
   */
  public function superficies(): array {
    $gravado = $this->configFactory->get(self::CONFIG_NAME)->get('superficies') ?? [];
    $saida = [];
    foreach (array_keys(DomainSurfaceMap::DOMAIN_IDS) as $purpose) {
      $linha = is_array($gravado[$purpose] ?? NULL) ? $gravado[$purpose] : [];
      $modo = (string) ($linha['modo'] ?? 'subdominio');
      $saida[$purpose] = [
        'modo' => in_array($modo, self::MODOS, TRUE) ? $modo : 'subdominio',
        'ativa' => (bool) ($linha['ativa'] ?? TRUE),
        'slug' => (string) ($linha['slug'] ?? ''),
        'titulo' => (string) $linha['titulo'],
      ];
    }
    return $saida;
  }

  /**
   * Regras que a configuração precisa cumprir. Retorna erros por purpose.
   *
   * @param array<string, array{modo: string, ativa: bool, slug: string, titulo: string}> $superficies
   *   Superfícies a validar, indexadas por purpose.
   *
   * @return array<string, string>
   *   Mensagens de erro por purpose.
   */
  public static function validar(array $superficies): array {
    $erros = [];
    $slugs = [];
    foreach ($superficies as $purpose => $linha) {
      if ($purpose === DomainSurfaceMap::MAIN && $linha['ativa'] !== TRUE) {
        $erros[$purpose] = 'O principal não pode ser desativado.';
        continue;
      }
      if ($linha['modo'] !== 'diretorio') {
        continue;
      }
      if (!in_array($purpose, self::DIRETORIO_PERMITIDO, TRUE)) {
        $erros[$purpose] = 'Esta superfície não pode ir para diretório.';
        continue;
      }
      $slug = $linha['slug'];
      if (!preg_match(self::PADRAO_SLUG, $slug)) {
        $erros[$purpose] = 'Slug inválido: use minúsculas, números e hífen.';
      }
      elseif (in_array($slug, self::RESERVADOS, TRUE)) {
        $erros[$purpose] = 'Slug reservado.';
      }
      elseif (isset($slugs[$slug])) {
        $erros[$purpose] = 'Slug já usado por outra superfície.';
      }
      $slugs[$slug] = $purpose;
    }
    return $erros;
  }

}
