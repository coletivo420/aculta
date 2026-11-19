# HTMX no tema V2: status (ADR-050)

Status da migração para HTMX, extraído do roadmap em 2026-11-05. Evidências em `../../../../../docs/htmx-confirmacao.md`. O estado geral das fases fica em `roadmap.md`.

## HTMX — ADR-050 (F0 e F1 concluídas)

Core 11.5 `Htmx`, `core/drupal.htmx`, o atributo de conteúdo principal, a
renovação de `form_build_id` e o caminho CSRF foram inspecionados. Testes oficiais
Core e fixture ACULTA de popup/form dentro de `<dialog>` passaram em Firefox e
Chromium headless, incluindo token adulterado rejeitado. **F0 e F1 CONCLUÍDAS**; F2 depende de F1a e F3 segue a ordem de módulos do ADR-050. Evidência em
[`docs/htmx-confirmacao.md`](../../../../../docs/htmx-confirmacao.md).

**Atualização 2026-11-05 — F1 parcial:** o Portal responde ao login popup por
HTMX, com fragmento, `HX-Redirect` após autenticação e fallback de navegação;
no tema V2, o login do shell e as gavetas de mensagens/avisos usam
`core/drupal.htmx`. A regra de engenharia está registrada: AJAX Drupal é legado
e HTMX é o padrão obrigatório para novas interações assíncronas do tema. Gates
SDC, foundations, shell contract, browser validators e render isolado dos 35
SDCs passaram nesta entrega. O Firefox usado para a fixture foi sintético e
não valida autenticação real no Runtime.

**Atualização 2026-11-05 — busca e cadastro migrados:** a busca do shell deixou
o Fetch. O gatilho abre o diálogo por `hx-get` e o campo de prévia usa `hx-get`
com debounce de 300 ms, `hx-sync="this:replace"` e a rota `/busca/previa`; o
formulário nativo e o link continuam válidos sem JavaScript. O template ganhou
o `id="aculta-search-content"`, que faltava para o alvo HTMX. O Portal responde
ao cadastro popup com fragmento e `HX-Redirect`, mantendo o caminho AJAX só no
fluxo sem HTMX. Login real com a conta de teste respondeu `HX-Redirect` e criou
sessão; o POST inválido do cadastro devolveu o fragmento com erro sem criar
conta. A suíte `tests/browser/aculta-htmx-f1/` passou 43/43 checks no Runtime
de desenvolvimento (busca, login e gaveta de avisos autenticada em 320, 390,
768, 959, 960 e 1440 px, claro e escuro, zoom 200%, prévia HTMX, teclado e
fallback sem JavaScript).

**Estado de F1:** busca, login e gavetas de mensagens/avisos usam HTMX, com
foco, `aria-live` e teclado conferidos; a gaveta de avisos autenticada foi
capturada em seis larguras e dois modos, com os dois grupos `details`. Duas
decisões do responsável em 2026-11-05 fecharam as pendências: (1) as gavetas
mantêm a exceção documentada — dependem de JavaScript e não têm link nativo
para a página completa, porque o Portal não fornece `all_href`; (2) o gatilho do
cadastro no checkout de doação foi autorizado e migrado para o diálogo HTMX do
shell, com o `href` como fallback. F1 está coberta; F5 continua responsável
pelo restante do checkout e do pagamento. F2 permanece dependente de F1a; F3
segue a ordem de dependências de módulos definida na ADR-050.

