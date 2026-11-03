# Plano final: superfícies do aculta_portal

Status: **consolidado, não aceito formalmente**. Base histórica: `dev` @ 1034dbb. A seção 2 registra fatos medidos naquela revisão; para o estado atual, consulte o README e o changelog do módulo. Testes de Runtime: **DEFERRED**.

## 1. Decisões registradas

| # | Decisão | Fonte |
|---|---|---|
| D1 | O módulo Domain fica com hosts, aliases de ambiente e configuração por domínio. Não decide superfície. | responsável |
| D2 | O aculta controla superfície, modo (`subdominio`, `diretorio`, `desativado`) e slug. | responsável |
| D3 | Modo diretório por **camada própria** no aculta, sem delegar a resolução ao `path_prefix` do Domain. | responsável |
| D4 | Remoção do Domain foi revista: o módulo é mantido. | responsável |
| D5 | A regra "contrib primeiro" deixou de valer para o mapa de domínios. Não se aplica ao AGENTS.md inteiro sem PR própria. | responsável |
| D6 | Resolvedor no núcleo `aculta_portal`; configuração de superfície no núcleo, com padrões iguais ao comportamento atual. | análise + ADR |
| D7 | Submódulo `aculta_superficies`: formulário, validações e sincronização do Domain. Opcional. | responsável |
| D8 | A flag `_aculta_domain_purpose_authoritative` é **lida** pelo resolvedor. | responsável |
| D9 | Trabalho em `dev`, sem branch nova e sem commit até pedido explícito. | responsável |

## 2. Fatos medidos no código (dev @ 1034dbb)

Esta fotografia antecede a atualização do contrib Domain e não descreve a versão instalada atualmente.

- 10 purposes: main, account, support, magazine, wiki, shop, courses, forum, links, eventos.
- 10 registros de domínio em `config/sync`, todos com `path_prefix: ''`.
- Domain 3.0.1 na revisão histórica; `path_prefix` era tratado como recurso experimental naquele contexto.
- 161 declarações YAML de `_aculta_domain_purpose`.
- Atribuição em código por `RoutingEvents::ALTER`: `aculta_mcp` (-2048), `aculta_portal` (-2049), `aculta_messenger` (-2050), `aculta_calendar` (-2050).
- Cinco mapas por purpose em código: `DOMAIN_IDS`, `FRONT_PATHS`, `CANONICAL_STUDIO_PATHS`, `NO_DA_RAIZ`, `CAMINHOS_LEGADOS`.
- 12 literais de decisão `'main'` e `'account'` no `DomainPurposeRequestSubscriber`.
- Rota MCP `/integracoes/mcp`: sem sobrescrita hoje, por coincidência de valor com a regra de `/integracoes`.
- Flag `_aculta_domain_purpose_authoritative`: sem leitor no código atual.

## 3. Modelo da configuração

Uma entrada por superfície, em configuração do núcleo (`aculta_portal.superficies`):

| Campo | Tipo | Regra |
|---|---|---|
| `purpose` | string fixa | um dos 10; não criável |
| `modo` | enum | `subdominio`, `diretorio`, `desativado` |
| `slug` | string | só para `diretorio`; regras de `FRIENDLY-PORTUGUESE-SLUGS` |
| `ativa` | bool | `main` não pode ser `false` |
| `titulo` | string | nome da superfície |
| `indexavel` | bool | padrão atual |

Hosts não entram na configuração: vêm do Domain e de variável de ambiente via Drupal Key.

## 4. Validações obrigatórias

- Dois purposes não podem ocupar o mesmo par host + caminho.
- `main`, painel administrativo, carrinho, checkout e pagamento não podem ser desativados.
- Slug segue `FRIENDLY-PORTUGUESE-SLUGS`.
- Mudança de slug antes da versão estável: caminho antigo responde 404, sem redirect.
- Desativar superfície com conteúdo: aviso de impacto, nenhum dado apagado.

## 5. Etapas de implementação

Cada etapa vira PR própria, com lista de entregas, limites e `RUNTIME STATUS: DEFERRED` enquanto não houver teste no servidor.

**3.0 Congelar comportamento.** Testes de rota para cada purpose, host e caminho, registrando o resultado atual (200, 404, 403, redirect). Referência para todas as etapas seguintes. **Depende de Runtime.**

**3.1 Unificar os cinco mapas**, sem mudar valores. Configuração de superfície com os padrões atuais. Gate: testes de 3.0 continuam verdes.

**3.2 Ler a flag de autoridade.** O resolvedor não sobrescreve purpose de rota marcada como autoritativa. Corrigir o comentário do `aculta_mcp` sobre a ordem de execução.

**3.3 Substituir os literais de decisão.** `'main'` vira "superfície protegida"; `'account'` vira "superfície de Conta". A exceção de reset de senha (`entity.user.edit_form`) fica fixa e documentada.

**3.4 Gerar a atribuição de purpose por rota** a partir da configuração, substituindo as 161 declarações YAML e os subscribers de rota. Não muda a ordem de prioridades sem teste.

**3.5 Modo diretório** (camada própria), somente depois de 3.1 a 3.4.

**3.6 Submódulo `aculta_superficies`**: formulário, validações e sincronização do Domain.

**3.7 Configuração exportada.** Verificar antes da importação que nenhum registro é apagado.

## 6. Riscos

- Ordem dos subscribers: mudar uma prioridade pode quebrar `messenger`, `calendar` ou `mcp`.
- Cookie compartilhado: mudar o modo muda o limite de confiança entre subdomínios.
- Conta com três caminhos (`/conta`, `/conta-legada`, dashboard): mexer aqui pode deslogar ou redirecionar usuários.
- Literais de rota (`aculta_portal.dashboard`, `aculta_studio.account`) também precisam de substituto.
- Slug antes da versão estável: 404 no caminho antigo.

## 7. Pendências antes de implementar

1. **Acesso ao Runtime** para 3.0. Sem ele, nenhuma etapa é validada de verdade.
2. **Confirmar a chave** do recurso experimental do Domain (não bloqueia D3).
3. **Lista de purposes (premissa corrigida em 2026-11-02):** o `AGENTS.md` do repositório não lista purposes. A lista de 7 itens está nas instruções do projeto no claude.ai, fora do repositório. O responsável deve atualizá-la para 10 purposes lá. Nada a mudar no repositório.
4. **Classificar a versão** de cada etapa (PATCH, MINOR ou MAJOR).
5. **Corrigir o comentário** do `aculta_mcp` (ordem de execução).

## 8. Fora de escopo

- Migração de conteúdo.
- Alteração de hosts de produção.
- Qualquer commit, branch ou merge sem pedido explícito.
- Mudança em `web/core/`, `web/modules/contrib/` ou `web/themes/contrib/`.
