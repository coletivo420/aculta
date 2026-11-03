## 0.1.0-dev.4 — aviso de correção de deploy ao mudar modo ou slug

- O formulário avisa quando uma superfície muda de modo ou slug e indica o registro de correção de deploy (canonical e sitemap) a criar com a CLI do aculta_deployer. O Portal não grava no registro.
- Testes: DEFERRED.

## 0.1.0-dev.3 — estado real das fases e documentação

- Fase 3.1 (mapa único), 3.2 (flag autoritativa), 3.3 (constantes de purpose), 3.4 parcial (configuração, schema, leitor, interruptor desligado), 3.5 parcial (regra de diretório na validação), 3.6 (formulário no MAIN), 3.7 parcial (YAML exportado).
- Não implementado: subscriber da 3.4, URLs de diretório da 3.5, conferência de importação da 3.7. Dependem de teste de rota no Runtime.
- Testes: escritos, DEFERRED.

## 0.1.0-dev.2 — formulário de superfícies no MAIN (Fase 3.6)

- `SuperficiesForm` em `/painel-administrativo/superficies`, permissão `administrar superficies`, rota só no MAIN.
- Salva `aculta_portal.superficies` com validação do `SurfaceConfigReader`. Não altera rotas nesta versão.
- Testes e Runtime: DEFERRED.

# Changelog: ACULTA Superfícies

## 0.1.0-dev.1 — esqueleto e planejamento

- Pasta do submódulo criada com `info.yml`, `README.md` e este changelog.
- Nenhum código PHP, rota, formulário ou configuração foi escrito nesta versão.
- Decisões registradas no plano:
  - resolvedor no núcleo `aculta_portal`, não neste submódulo;
  - modo diretório por camada própria (não `path_prefix` experimental do Domain);
  - flag `_aculta_domain_purpose_authoritative` lida pelo resolvedor;
  - cinco mapas de purpose unificados em uma configuração (Fase 3.1).
- Testes: **DEFERRED**. Sem acesso ao Runtime.
- Versão de referência: MINOR do `aculta_portal` quando houver implementação. Confirmar na classificação de versionamento antes de marcar a versão final.
