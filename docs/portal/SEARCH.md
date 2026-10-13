# Busca do site (política)

Status: vigente desde 0.2.0-dev.41.

## Regra

- **Busca geral centralizada no MAIN.** `/busca` (e `/busca/sugestoes`) é atendido só no domínio principal. Não há busca geral nos subdomínios.
- **Índice cobre o conteúdo público dos subdomínios.** O índice `aculta_conteudo` (Search API, backend de banco) inclui os tipos `activity`, `article`, `document`, `page` e `project`, publicados em qualquer purpose.
- **Cada resultado abre no seu host.** O link é gerado pelo purpose do nó (`field_domain_source`), via `DomainPurposeManager::canonicalPathUrl`. Um item do magazine abre em `coletivo420.aculta.org`, um item da home abre em `aculta.org`.
- **Visibilidade por acesso.** `content_access` filtra no índice; o controller confere `node->access('view')` por item.

## Exceções

- **Wiki420 mantém o próprio motor** (`/wiki/busca`, `WikiController::search`), restrito aos verbetes `wiki_entry`. A prévia (`/wiki/busca/previa`) reusa a mesma consulta e o comportamento JS de `aculta_portal/search-preview`, mas responde apenas com verbetes publicados e acessíveis no purpose Wiki. Por isso `wiki_entry` não entra no índice geral.
- `editorial_highlight` (destaques da home) não é conteúdo pesquisável e fica fora do índice.

## Verificação

- Teste de Kernel: publicado aparece, rascunho não aparece para visitante, total igual à lista.
- Runtime de teste: visitante vê 12 itens no índice, igual aos 12 publicados nos tipos indexados. Resultado de magazine abre no host do magazine.
