# Secrets Manager: auditoria e caracterização (SM-0)

Registro, antes da correção, do comportamento do painel `/painel-administrativo/aculta/segredos` como estava
no commit `51c85dfb6` (HEAD da PR #298). Nenhum valor real aparece aqui.

## Componentes auditados

| Componente | Papel | Achado |
| --- | --- | --- |
| `SecretsManager` | estado, valores, gravação | `status()` e `value()` usavam só o ambiente ativo, sem checar escopo |
| `SecretsFormat` | parse do arquivo NAME=value | sem achado |
| `SecretsController` | página, olho, lápis, lixeira | duas seções simultâneas a partir da mesma coleção de estado |
| `SecretEditForm` / `SecretClearForm` | popups de alterar e apagar | gravação ao ambiente ativo, sem verificar escopo da variável |
| `SecretProviderAdapter` / `SecretWriterAdapter` | ponte para consumidores | resolvem `value()` do ambiente ativo |
| `SecretsRequirementsHook` | requisitos no relatório | conta obrigatórias do ambiente ativo |

## Incompatibilidade (documentada antes da correção)

1. **Duas seções com o mesmo estado.** A seção "Produção" e a seção "Testes" eram renderizadas a partir de
   `status()`, que só conhece o ambiente ativo. No servidor de testes, a seção de produção mostrava
   "preenchida" com base nos valores de teste.
2. **Escopo não verificado no backend.** `value()`, `save()` e `clear()` aceitavam qualquer nome do contrato,
   inclusive o de outro ambiente (ex.: `TURNSTILE_KEYS_JSON`, só de produção, podia ser lido ou gravado no
   servidor de testes).
3. **Revelação sem token CSRF.** A rota `aculta_portal.secrets_show` não exigia `_csrf_token`, embora o
   comentário do controller afirmasse o contrário.
4. **Sem cabeçalho de não cache.** As respostas do painel não traziam `Cache-Control: no-store`.
5. **Alterar e apagar sem token.** As rotas `aculta_portal.secrets_edit` e `secrets_clear` também não exigiam
   token na abertura do popup.

## Correção (ver commits da PR)

- Escopo por ambiente no núcleo (`inActiveScope()`): `value()`, `save()` e `clear()` recusam variável de outro
  ambiente. Controller nega mostrar, alterar e apagar fora do escopo (403).
- Seções substituídas por duas abas; a aba do outro ambiente mostra só metadados.
- `_csrf_token` nas quatro rotas do painel; subscriber com `no-store` e `Pragma: no-cache`.

## Caracterização após a correção

Cobertura: `SecretsManagerTest`, `SecretsTabsTest`, `SecretsFallbackAndRoutesTest`,
`SecretsResponseSubscriberTest` (unit) e `SecretsRoutesKernelTest` (Kernel, banco de teste dedicado).
Verificação no navegador: `tests/browser/aculta-secrets-manager/painel-visual.py`.
