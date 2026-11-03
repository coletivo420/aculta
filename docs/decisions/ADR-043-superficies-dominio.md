# ADR-043: superfícies de domínio no aculta_portal

- **Status:** aceito pelo responsável em 2026-11-02, como direção de modelo. A implementação segue a Fase 3 e não foi iniciada. Não altera comportamento.
- **Data:** 2026-11-02.
- **Relaciona-se com:** ADR-038 (suítes do Studio por domínio), ADR-009 (ambientes), D-50 (cookie local host-only), D-70 (worktrees e sincronização do Runtime).
- **Base do inventário:** `dev` @ 93b638a (a leitura inicial foi feita em 1034dbb; os números abaixo foram conferidos em 93b638a).

## Caminhos do Studio: sem conflito de código

Uma primeira leitura apontou conflito entre `CANONICAL_STUDIO_PATHS` (`shop:/studio/green-stash`, `wiki:/studio/we-sativa`) e o corpo do ADR-038. A leitura estava errada. A retificação do responsável em 2026-10-30, registrada em `web/modules/custom/aculta_portal/AGENTS.md`, aprova exatamente esses dois caminhos `/studio/*`; os demais `/studio/*` respondem 404. O código está conforme a retificação.

Pendência documental: o corpo do ADR-038 ainda cita `wiki:/wesativa` e `shop:/greenstash` e diz que `/studio/*` foi descartado. Isso contradiz a retificação. Corrigir o ADR-038 é decisão separada do responsável.

## Inventário medido (leitura inicial em 1034dbb)

- Domain 3.0.1 com `domain`, `domain_alias`, `domain_config`, `domain_source` (aculta_portal), `domain` (aculta_wiki, aculta_portal_sitemap) e `domain_source` (aculta_forum).
- 10 registros de domínio em `config/sync`, todos com `path_prefix: ''`. `domain.settings.yml` com `path_prefix: false`, recurso experimental do Domain desligado por padrão.
- Atribuição de purpose a rotas em três lugares:
  1. YAML: 161 declarações de `_aculta_domain_purpose` (main 46, account 48, eventos 33, wiki 11, courses 10, links 7, shop 6).
  2. Código em tempo de execução, em `RoutingEvents::ALTER`.
  3. Compartilhamento: `_aculta_domain_purposes`, definido só em código, por `aculta_calendar`.
- Mapas paralelos: `DomainPurposeManager::DOMAIN_IDS` e `PurposeFrontPathProcessor::FRONT_PATHS`.
- `DomainPurposeRequestSubscriber` (565 linhas): ~12 decisões com `'main'` e `'account'` fixos. 195 literais de purpose em `aculta_portal/src` (não testes).

## Ordem de execução das alterações de rota (`RoutingEvents::ALTER`)

No Symfony, listeners com prioridade maior executam antes. Prioridades medidas:

| Prioridade | Componente | Efeito |
|---|---|---|
| -2048 | Rename Admin Paths (Core/contrib) | renomeia caminhos administrativos |
| -2048 | `aculta_mcp` `DomainRouteSubscriber` | define `main` em `mcp_server.handle` e `_aculta_domain_purpose_authoritative` |
| -2049 | `aculta_portal` `DomainRouteSubscriber` | atribui purpose por família de rotas e traduz caminhos |
| -2050 | `aculta_messenger` `PrivateMessageAccountRouteSubscriber` | define `account` |
| -2050 | `aculta_calendar` `SharedPurposeRouteSubscriber` | define `['eventos','main']` |

Achados:

1. **A flag `_aculta_domain_purpose_authoritative` não é lida em lugar nenhum.** Ela é só definida pelo `aculta_mcp`. Nenhum subscriber a respeita.
2. **O comentário do `aculta_mcp` contradiz a prioridade.** Ele diz "aplicar depois da passagem de purpose do portal", mas usa -2048, que executa antes do -2049 do portal. Se alguma regra de família do portal casar com `mcp_server.handle`, o purpose `main` pode ser sobrescrito. Precisa de teste de rota para confirmar.
3. A ordem entre `aculta_messenger` e o portal está correta e documentada no próprio código.

## Decisão proposta

1. Domain: hosts, aliases de ambiente, configuração por domínio. Não decide superfície.
2. Núcleo do `aculta_portal`: resolvedor único de superfície, lendo a configuração com padrões iguais ao comportamento atual. Substitui `DOMAIN_IDS`, `FRONT_PATHS` e a atribuição por família de rota.
3. Submódulo `aculta_superficies` (opcional): formulário em MAIN, validações e sincronização dos registros do Domain.
4. Modo diretório: camada própria no aculta (decisão registrada).
5. Antes de migrar, a flag `_aculta_domain_purpose_authoritative` precisa ser lida pelo resolvedor (ou removida), e a prioridade do `aculta_mcp` precisa ser corrigida ou documentada.

## Esquema proposto da configuração

| Campo | Tipo | Regra |
|---|---|---|
| `purpose` | string, fixo | um dos 10 atuais; não criável pelo formulário |
| `modo` | enum | `subdominio`, `diretorio`, `desativado` |
| `slug` | string | só para `diretorio`; regras de `FRIENDLY-PORTUGUESE-SLUGS` |
| `ativa` | bool | `main` e os protegidos não podem ser `false` |
| `titulo` | string | nome da superfície |
| `indexavel` | bool | padrão atual |

Hosts não entram no esquema: vêm do Domain e de variável de ambiente via Drupal Key.

## Validações

- Dois purposes não podem ocupar o mesmo par host + caminho.
- `main`, painel administrativo, carrinho, checkout e pagamento não podem ser desativados.
- Slug segue `FRIENDLY-PORTUGUESE-SLUGS`.
- Mudança de slug antes da versão estável: caminho antigo responde 404, sem redirect.
- Desativar superfície com conteúdo: aviso de impacto, sem apagar dados.

## Riscos

- Migração de 161 declarações YAML, dois subscribers de rota, um mapa de administração e subscribers de outros submódulos. Risco alto de regressão.
- Ordem de prioridades implícita. Mudar um número pode quebrar `messenger` ou `mcp`.
- Flag de autoridade sem leitor.
- Cookie compartilhado: alterar modo altera o limite de confiança. O formulário precisa avisar.
- Literais `'main'` e `'account'` em decisões de acesso.

## Pendências

- Teste de rota para `mcp_server.handle` com a ordem atual de subscribers.
- Mapear as ~12 decisões fixas do `DomainPurposeRequestSubscriber`, uma a uma.
- Confirmar a chave do recurso experimental do Domain (não bloqueante na opção b).
- Lista de purposes: premissa corrigida em 2026-11-02. O `AGENTS.md` do repositório não a lista; a lista de 7 está nas instruções do projeto no claude.ai, fora do repositório, e precisa passar a 10.
