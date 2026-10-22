# ADR-013 — ACULTA Content Index

- **Status:** Aceita; decisão arquitetural. Implementação ainda não homologada.
- **Data:** 2026-10-22.
- **Dono:** Kernel `aculta_portal`.
- **Iniciativa:** ACULTA Content Index 0.1.0.
- **Auditoria:** [IDX 0.1-A](../portal/ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md).

## Contexto

Revista, Wiki, Fórum e LMS possuem entidades, relações, workflows e controle de acesso próprios. Busca textual já usa Search API, inclusive configuração geral `aculta_conteudo` e configuração especializada do Fórum. Entity Usage rastreia relações configuradas. Statistics mantém agregações analíticas. Não foi encontrado um catálogo ACULTA que permita consultar metadados relacionais comuns entre esses domínios.

Uma tabela central de registros de negócio ou a transferência de permissões para um índice geraria propriedade duplicada, risco de divergência e exposição indevida. Um índice textual separado repetiria capacidades de Search API. Ver [auditoria e matriz](../portal/ACULTA-CONTENT-INDEX-AUDIT-0.1-A.md) para evidências concretas.

## Decisão

Criar `aculta_index` como submódulo opcional do Kernel `aculta_portal`. O módulo manterá um catálogo SQL derivado, descartável, reconstruível e incremental para metadados mínimos e relações explicitamente projetadas pelos adaptadores dos módulos de origem.

Os módulos de origem continuam donos das entidades, revisões, traduções, workflows, permissões, publicação e regras de negócio. `aculta_index` fornece APIs de consulta e manutenção, mas não confere autorização. A consulta usa o catálogo para selecionar candidatos e revalida acesso no Entity API do proprietário antes de expor dados, operações, contagens ou facetas. Falha ao determinar acesso significa negar exposição.

Search API continua responsável pela busca textual, datasource/tracking e backend. O índice poderá fornecer campos/IDs ou ser consultado como fonte para Search API após contrato e medição; não implementará fulltext próprio nesta versão.

`aculta_statistics` continua dono dos eventos analíticos, métricas, agregados, retenção e relatórios. Uma ponte opcional pode correlacionar IDs/seleções e agregar indicadores, sem dependência circular e com fallback quando qualquer dos submódulos estiver ausente.

Os providers definem allowlists de entidades, metadados e relações. Relações têm tipo, direção, origem, cardinalidade e lifecycle explícitos. Dados pessoais, corpos integrais e arquivos não são projetados sem caso de uso demonstrado.

No 0.1, a identidade de uma entrada representa uma entidade e um idioma; revisões não geram entradas independentes. O site hoje usa `pt_BR`, mas o idioma permanece na identidade. Providers opcionais são serviços DI tagueados como `aculta_index.provider`, escolhidos pelo responsável para evitar um plugin manager prematuro. Não instalar Search API, Advanced Queue, Entity Usage, Views Bulk Operations ou Track Usage para esta iniciativa; usar APIs ACULTA/Core existentes e portar código somente se uma lacuna técnica concreta exigir e a proveniência/licença estiver registrada.

O rebuild usa `generation_id` nas tabelas e um ponteiro de geração ativa trocado em transação. A fila incremental usa Core Queue e estado mínimo próprio para falhas/retries/dead-letter; a política aprovada é cinco tentativas com backoff exponencial de 30 segundos até seis horas. Nenhum módulo contrib pesquisado será instalado.

Reconstruções usam geração/checkpoint e catch-up de alterações. A gravação valida versão/fingerprint para impedir jobs antigos de sobrescrever dados novos. O worker aplica alterações às gerações `active` e `building`; a validação final e o catch-up antes da ativação são gates pendentes. Todas as leituras verificam acesso na entidade original e falham fechadas. No contrato inicial 0.1, consultas não retornam totais nem facetas. A varredura tem limite fixo de até 100 candidatos; cursor aleatório, vinculado a conta/filtros/geração, guarda somente hash no banco e não expõe IDs. O token de continuação aparece quando a página de candidatos alcança o limite, o que pode sinalizar que existe continuação; não inclui contagem ou metadados privados.

## Alternativas consideradas

1. **Expandir `aculta_conteudo` / Search API para todos os usos:** rejeitada como catálogo geral; Search API resolve texto/index tracking, mas não substitui contratos de projeção, relações editoriais e autorização contextual.
2. **Usar Entity Usage como catálogo:** rejeitada; armazena usos source/target por field, revisão e método e não é catálogo de metadados/consultas.
3. **Mover tabelas de Statistics para o índice:** rejeitada; mistura eventos analíticos com projeções e cria dependência de ciclo de vida.
4. **Indexar todo tipo/referência automaticamente:** rejeitada; faria relações e metadados sem garantia de semântica ou visibilidade.
5. **Instalar Advanced Queue:** rejeitada; Core Queue com estado mínimo ACULTA atende a primeira etapa e evita dependência adicional.

## Consequências

- Há custo de armazenamento e sincronização duplicada de metadados, aceito porque o catálogo é derivado e descartável.
- Provedores e hooks precisam ser versionados, idempotentes e tolerantes a módulos opcionais.
- Revogação de acesso por grants, Group ou domínio precisa de integração além do save da entidade; este ponto é gate de segurança antes de expor consultas.
- Facetas e estatísticas exigem autorização ou agregação segura; não podem derivar diretamente de candidatos SQL unrestricted.
- Schema, queries e locking serão validados nos bancos suportados (MariaDB e SQLite, conforme contratos de testes do projeto).
- Não haverá ativação/upgrade de Runtime nem alteração de configuração persistente nesta decisão.

## Próximos passos

IDX 0.1-B estabeleceu manifesto, registry e contrato inicial de providers. IDX 0.1-C define schema e primeiro provider, respeitando as decisões de identidade acima. O detalhamento, status e bloqueios estão no roadmap do [ACULTA Content Index](../portal/ACULTA-CONTENT-INDEX.md).
