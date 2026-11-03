# Plano: caminhos públicos de WE SATIVA e GREEN STASH

Status: **Fase A (plano)**. Sem alteração de código. **RUNTIME STATUS: DEFERRED** (sem Runtime nesta sessão).
Base: `dev` @ d6953c8 (histórico reescrito em 2026-11-02 para remover o trailer `Co-Authored-By` do commit de planejamento; árvore inalterada).

## Decisões do responsável (2026-11-02)

- WE SATIVA: entrada na **raiz do subdomínio wiki420**, sem prefixo `/studio`.
- GREEN STASH: entrada na **raiz do subdomínio loja420**, sem prefixo `/studio`. Carrinho, checkout e pagamento continuam no MAIN.
- O novo módulo (`aculta_superficies`) controla superfície, modo e slug. A política de `/studio/*` deixa de ser a fonte.

## Estado medido (código em d6953c8)

| Ponto | Onde | O que diz |
|---|---|---|
| Rota WE SATIVA (página) | `aculta_studio/aculta_studio.routing.yml` linha 184 | `path: /wesativa` |
| Rotas WE SATIVA (ação) | `aculta_wiki/aculta_wiki.routing.yml` linhas 73 e 97 | `/wesativa/verbetes/novo`, `/wesativa/propostas/{proposal}/decidir` |
| Rotas GREEN STASH | `aculta_commerce/aculta_commerce.routing.yml` linhas 2 a 46 | `/greenstash`, `/greenstash/{secao}`, `/greenstash/envios/...`, `/greenstash/produtos/...` |
| Guarda de caminho Studio | `aculta_portal/src/Domain/FrontPathPolicy.php` linhas 21 a 24 | `shop` → `/studio/green-stash`, `wiki` → `/studio/we-sativa` |
| Bloqueio `/studio` na Conta | `aculta_portal/src/EventSubscriber/DomainPurposeRequestSubscriber.php` linha 147 | nega `/studio` e `/studio/*` no purpose ACCOUNT |
| Raiz da Wiki | `FrontPathPolicy`/`PurposeFrontPathProcessor::FRONT_PATHS` | `wiki` → `/wiki` |
| Raiz da loja | `PurposeFrontPathProcessor::FRONT_PATHS` e `FrontPathPolicy::NO_DA_RAIZ` | `shop` → `/loja`; `/node/372` interno |

Achado principal: **nenhuma rota declarada no código usa `/studio/we-sativa` ou `/studio/green-stash`.** O código serve `/wesativa` e `/greenstash`, que são os caminhos antigos do ADR-038. A guarda protege `/studio/*`, que nenhuma rota serve. Ou seja, a retificação de 2026-10-30 (`/studio/*`) nunca chegou ao código de rota, e a decisão de 2026-11-02 (raiz) ainda não tem rota correspondente.

## Pontos que dependem de você (antes da Fase B)

1. **Conflito de raiz da Wiki.** `FRONT_PATHS` define `/wiki` como raiz da Wiki. A decisão quer WE SATIVA na raiz de wiki420. Opções:
   - (a) a raiz vira WE SATIVA e a Wiki atual passa para `/wiki` (sem mudança, já é o caso) — ambas coexistem;
   - (b) a raiz vira WE SATIVA e a Wiki sai da raiz para outro slug em português (você informa o slug).
2. **Conflito de raiz da loja.** `FRONT_PATHS` define `/loja` como raiz da loja e `/node/372` como nó interno da raiz. A decisão quer GREEN STASH na raiz de loja420. Mesma pergunta: a loja atual fica em `/loja`, ou sai da raiz para outro slug?
3. **Fonte dos caminhos atuais.** O código serve `/wesativa` e `/greenstash`. Os caminhos novos são raiz de subdomínio. Os antigos saem, sem redirect (antes da versão estável, o antigo responde 404, conforme decisão do responsável)?
4. **Rotas de ação.** `/wesativa/verbetes/novo`, `/wesativa/propostas/{proposal}/decidir` e as rotas `/greenstash/produtos/...`, `/greenstash/envios/...` mudam junto com a entrada, ou ficam como estão sob o novo prefixo da raiz?
5. **ADR-038.** O corpo ainda cita `/wesativa` e `/greenstash` e diz que `/studio/*` foi descartado. Atualizar o ADR-038 depende da resposta 3.

## Fases

- **Fase A (este documento):** inventário e perguntas. Concluída quando as perguntas 1 a 5 forem respondidas.
- **Fase B:** alterar rotas, controllers e `FrontPathPolicy` conforme as respostas; um commit por módulo (`aculta_studio`, `aculta_wiki`, `aculta_commerce`, `aculta_portal`). Testes unitários e Kernel dependem de `vendor/` e do Runtime: ficam DEFERRED se não rodarem.
- **Fase C:** atualizar ADR-038, `aculta_wiki/AGENTS.md`, READMEs de `aculta_studio` e `aculta_commerce`, e o `aculta_superficies/docs/PLANO-FINAL.md`.
- **Fase D:** rodar a matriz do `TESTES-3.0-ROTAS-SUPERFICIES.md` no Runtime (DEFERRED).

## Riscos

- Mudar a raiz de um subdomínio altera a entrada pública e o SEO. Produção é indexável; o servidor de testes tem `noindex`.
- `/wesativa` e `/greenstash` aparecem em testes Kernel da Wiki e em links de navegação dos módulos de loja e Studio.
- A Conta bloqueia `/studio/*` no ACCOUNT; se a Conta passar a expor o Studio, a regra precisa mudar junto.

## Fora de escopo

- Migração do resolvedor de superfícies (Fase 3 do `aculta_superficies`).
- Mudança de Domain, hosts ou variáveis de ambiente.
