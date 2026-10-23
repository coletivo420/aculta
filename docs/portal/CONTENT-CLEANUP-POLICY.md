# Política de limpeza de conteúdo e anexos

**Dono da política:** `aculta_portal`. **Estado:** APIs compartilhadas de verificação/remoção de Media/File implementadas parcialmente em Portal 0.4.1-dev.9; fluxo coordenado de exclusão de conteúdo segue planejado. Nenhuma exclusão automática de posts foi habilitada.

## Objetivo e limites

Fornecer um fluxo comum e auditável para limpar posts explicitamente selecionados e seus anexos. O módulo de origem continua dono da entidade, workflow e acesso; o Portal coordena a seleção, prévia, verificação de dependências e execução pelas APIs Drupal. O Content Index pode ajudar a descobrir candidatos, mas nunca é fonte de verdade para decidir exclusão ou uso.

Nenhum cron apagará posts. Uma operação de limpeza exige IDs explícitos, uma prévia sem escrita, confirmação do responsável e backup recuperável quando executada em Runtime. A operação registra contagens, motivos, referências preservadas e falhas sem exportar corpo, nomes de pessoas ou dados sensíveis.

## Regras para anexos

1. Antes de excluir um post, enumerar referências de entrada e saída, incluindo referências de campos, embeds em texto formatado, traduções e revisões. Dependência desconhecida ou impossível de verificar bloqueia a exclusão.
2. A exclusão de posts/conteúdo usa Entity API e hooks do módulo dono. Media órfãos são removidos pela API Portal após o adapter do dono indicar o candidato e a checagem transversal final; nenhuma entidade é removida por SQL direto.
3. Para cada Media/arquivo associado, revalidar o uso imediatamente antes da remoção. Se outro conteúdo, revisão, Media ou módulo ainda o usar, preservar o anexo.
4. Imagens passam por `aculta_image.file_remover`, que verifica `file_usage` e `entity_usage`, invalida derivados e remove metadados internos. O Portal não replica nem contorna essa implementação.
5. Para arquivos que não são imagens, remover pela File API somente depois de verificar File Usage, Entity Usage e referências Drupal atuais. Referências não rastreadas, entidades não carregáveis e tipos sem adapter falham fechados.
6. O módulo dono descobre candidatos e aplica suas regras semânticas; o Portal faz a checagem transversal final e remove Media não publicado pela Entity API somente quando não houver referência atual em campos ou embeds suportados. A remoção do post não implica remoção de Media que continua em uso.
7. Excluir conteúdo e arquivo não é reversível por si só. A prévia deve identificar retenções; execução deve ter backup e relatório de rollback/recriação documentado.

## Reaproveitamento existente

- `aculta_image.file_remover` é a autoridade para exclusão de imagens, com nova verificação de `file_usage` e `entity_usage` no momento da remoção.
- `aculta_image.cleanup` já processa arquivos substituídos/removidos em lotes e deve ser integrado quando a operação corresponder ao ciclo de imagens.
- `aculta_forum.attachment_cleanup` demonstra verificações de referências atuais em campos, embeds e revisões; portar somente os trechos genéricos necessários, preservando autoria/licença e atribuindo a lógica específica de Fórum ao seu módulo.
- `entity_usage` é uma fonte auxiliar, nunca prova única de ausência de referências. Consultar também referências estruturadas e conteúdo versionado suportado pelos adapters.

## Estado da implementação — 2026-10-22

`aculta_portal.unused_file_cleaner` oferece avaliação e remoção fail-closed de File: consulta Core File Usage, Entity Usage quando instalado, referências estruturadas de campos e referências UUID em campos de texto formatado/revisões. Arquivos de imagem são delegados a `aculta_image.file_remover`; arquivos não-imagem sem referências são removidos pela File API. `aculta_portal.unused_media_cleaner` revalida referências estruturadas e embeds/revisões em todo o site, recusa Media publicado e o remove via Entity API. O scanner interrompe a operação quando não consegue verificar uma fonte. Essas APIs ainda não são o executor de exclusão de posts: módulos donos descobrem e submetem candidatos e preservam suas regras específicas.

Primeiros consumidores: `aculta_image.cleanup` chama o serviço de File opcionalmente, mantendo fallback standalone e sem dependência `aculta_image` → Portal; `aculta_forum.attachment_cleanup` decide quais Media do Fórum são candidatos e usa os serviços compartilhados para revalidar/remover Media e File. Os demais projetos abaixo ainda estão em planejamento, exceto onde indicado.

## Entrega planejada no Portal

1. Auditoria somente leitura e matriz de adapters de conteúdo/anexos por módulo proprietário.
2. Serviço de planejamento que recebe IDs explícitos e emite relatório de dependências, candidatos a remoção e itens retidos; nenhum efeito colateral.
3. Executor em lotes, iniciado por comando administrativo explícito, que revalida dependências e acessos antes de cada exclusão e delega remoção de imagens ao `aculta_image`.
4. Testes de referências compartilhadas, revisões, traduções, embeds sem registro de uso, concorrência e falha parcial; logging sem conteúdo sensível.
5. Homologação no banco dedicado e Runtime de testes com backup restaurável antes de qualquer limpeza real.

## Integrações por projeto

| Projeto | Trabalho planejado | Limite do módulo |
|---|---|---|
| `aculta_image` | **Primeira integração feita:** `ImageCleanup` usa a API compartilhada opcionalmente; `FileRemoverInterface` formaliza o limite. Próximo: validar que todos os fluxos de quarentena e remoção de imagem passam pelo serviço, sem perder retenção, rechecagem, derivados ou metadados. | Continua sendo o único dono da remoção física de imagens. Sem dependência de módulo circular. |
| `aculta_forum` | **Primeira integração feita:** `ForumAttachmentCleanup` conserva a descoberta de candidatos Media e regras específicas de Fórum; Portal revalida/remove Media e File. Próximo: cobrir posts revisionáveis, embeds, referências compartilhadas e falha parcial por testes. | Fórum interpreta Posts, galerias e comentários; Portal faz checagem transversal final e remoção segura. |
| `aculta_wiki` | Contratos de relações do Content Index concluídos em dev.10 (`related_entry`, `wiki_category`, `wiki_image`); ainda fornecer adapter de limpeza para descobrir referências e anexos da Wiki e submeter candidatos Media ao coordenador durante exclusão autorizada de verbete. | Os providers do Index servem descoberta/consulta e não são adapters de exclusão. Portal só remove candidato após referências globais revalidadas; Wiki mantém a semântica editorial. |
| `aculta_magazine` | Ao excluir publicação selecionada, enviar relações estruturadas e embeds suportados para verificação compartilhada; preservar galerias, imagens e arquivos ainda usados por outro conteúdo. | Revista mantém workflow, publicação e aprovação editorial. |
| `aculta_gallery` | Informar relações de galeria → Media e submeter ao Portal os candidatos após exclusão/troca de referências. | Gallery mantém finalidade, publicação e acesso; Portal revalida e remove Media órfão. |
| Document/PDF Viewer e LMS Resources | Informar referências a arquivos e Media de documentos/turmas, com dependências e acesso Group, e submeter candidatos ao Portal antes da limpeza. | Módulos donos preservam acesso privado e download autorizado; Portal só remove após revalidar referências. |

As APIs mínimas de verificação/remoção de File e Media estão implementadas.
IDX 0.1-R deve homologar a cobertura e definir o futuro executor de exclusão de
posts: módulos de origem descobrem candidatos e relações; Portal revalida e
remove Media/File sem uso e coordena ciclo/retentativas; `aculta_image` executa
remoção física de imagem. Adaptar um consumidor por vez e manter a limpeza
existente ativa até prova de equivalência.

## Estado do Runtime consultado

Na prévia de 2026-10-22 foram encontrados 12 Nodes `revista`, quatro com marcadores de embed e nenhum `media_gallery`. Após autorização explícita para excluir os 12 no Runtime de teste, os Nodes, 17 Media `image` e 17 arquivos de imagem sem uso externo foram removidos. Um Media `remote_video` permaneceu porque seu owner ainda não estava definido naquela data; em dev.22 ele passou a ser do Portal, sem alterar o registro histórico. Evidências, salvaguardas e verificação estão em [MAGAZINE-TEST-CLEANUP-2026-10-22.md](MAGAZINE-TEST-CLEANUP-2026-10-22.md). A política e o executor compartilhado ainda estão planejados.
