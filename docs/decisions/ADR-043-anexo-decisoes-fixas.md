# Anexo ao ADR (rascunho v3): decisões fixas de purpose no DomainPurposeRequestSubscriber

Base: branch `dev` @ 1034dbb, lido em worktree somente leitura. Não commitado. Aguarda decisão do responsável.

## Mapa das 12 ocorrências de literais `'main'` e `'account'` (linhas do arquivo)

| # | Linhas | Literal | O que decide | Como fica no resolvedor |
|---|---|---|---|---|
| 1 | 112 | `account` | URL da raiz da Conta, para redirect | superfície de Conta (`pathUrl` da superfície) |
| 2 | 123, 202, 217 | `account` + `user.login` | destino de login da Conta (logout, dashboard anônimo, ação na raiz) | superfície de Conta + rota de login |
| 3 | 186-191 | `main` | recusa de mutação em purpose errado, quando existe candidato de rota `main` ou admin | "superfície protegida" em vez de `main` |
| 4 | 246-248 | `main` | rota administrativa ou canônica `main` negada fora do MAIN | mesma regra, com a superfície protegida |
| 5 | 348-350 | `main` | igual ao item 4, no caminho de `onRequest` | mesma regra |
| 6 | 397 | `main` | redirect de mutação em host errado: não reexecuta, responde 404 | superfície protegida |
| 7 | 436 | `main` | normalização de destino de redirect para o MAIN | superfície protegida |
| 8 | 484 | `account` + `entity.user.edit_form` | exceção do reset de senha do Core | superfície de Conta + nome de rota (exceção fixa) |

Literais de rota relacionados, que também precisam de substituto (não são purpose):

- `aculta_portal.dashboard` (linha ~214): rota de entrada da Conta.
- `FrontPathPolicy::acaoDaContaNaRaiz`: decide ação na raiz da Conta com base no purpose atual e no nome da rota.

## Leitura

- Os itens 1 e 2 e o item 8 dependem da superfície **Conta**. Se a Conta puder mudar de modo ou slug, essas decisões precisam ler a configuração.
- Os itens 3 a 7 dependem da superfície **protegida** (MAIN). Ela não pode ser desativada, então a regra pode ficar fixa, desde que seja expressa como "superfície protegida", não como o literal `'main'`.
- O item 8 é exceção de nome de rota. Mantê-la fixa é aceitável, mas ela precisa de teste.

## Ordem de execução dos subscribers (resumo do v3)

- `aculta_mcp` -2048, `aculta_portal` -2049, `aculta_messenger` -2050, `aculta_calendar` -2050.
- Rota MCP: a regra de `/integracoes` no portal define o mesmo `main`. Sem sobrescrita hoje, por coincidência de valor.
- A flag `_aculta_domain_purpose_authoritative` não tem leitor. Precisa ser lida ou removida.

## Pendências

- Teste funcional do endpoint `/integracoes/mcp` no Runtime: DEFERRED (sem acesso).
- Confirmar `FrontPathPolicy` e `aculta_portal.dashboard`, que não foram lidos completamente nesta fase.
