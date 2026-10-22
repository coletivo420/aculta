# Política de limpeza de conteúdo e anexos

**Dono da política:** `aculta_portal`. **Estado:** planejada para IDX 0.1-R / Portal 0.4.x; não habilita exclusão automática.

## Objetivo e limites

Fornecer um fluxo comum e auditável para limpar posts explicitamente selecionados e seus anexos. O módulo de origem continua dono da entidade, workflow e acesso; o Portal coordena a seleção, prévia, verificação de dependências e execução pelas APIs Drupal. O Content Index pode ajudar a descobrir candidatos, mas nunca é fonte de verdade para decidir exclusão ou uso.

Nenhum cron apagará posts. Uma operação de limpeza exige IDs explícitos, uma prévia sem escrita, confirmação do responsável e backup recuperável quando executada em Runtime. A operação registra contagens, motivos, referências preservadas e falhas sem exportar corpo, nomes de pessoas ou dados sensíveis.

## Regras para anexos

1. Antes de excluir um post, enumerar referências de entrada e saída, incluindo referências de campos, embeds em texto formatado, traduções e revisões. Dependência desconhecida ou impossível de verificar bloqueia a exclusão.
2. A exclusão de conteúdo usa Entity API e hooks do módulo dono. Não apagar linhas SQL diretamente nem remover entidades pertencentes a outro módulo.
3. Para cada Media/arquivo associado, revalidar o uso imediatamente antes da remoção. Se outro conteúdo, revisão, Media ou módulo ainda o usar, preservar o anexo.
4. Imagens passam por `aculta_image.file_remover`, que verifica `file_usage` e `entity_usage`, invalida derivados e remove metadados internos. O Portal não replica nem contorna essa implementação.
5. Para arquivos que não são imagens, remover pela File API somente depois de verificar File Usage, Entity Usage e referências Drupal atuais. Referências não rastreadas, entidades não carregáveis e tipos sem adapter falham fechados.
6. Apagar Media apenas quando nenhum conteúdo fora do conjunto selecionado o referenciar. Apagar o post não implica apagar automaticamente seus arquivos.
7. Excluir conteúdo e arquivo não é reversível por si só. A prévia deve identificar retenções; execução deve ter backup e relatório de rollback/recriação documentado.

## Reaproveitamento existente

- `aculta_image.file_remover` é a autoridade para exclusão de imagens, com nova verificação de `file_usage` e `entity_usage` no momento da remoção.
- `aculta_image.cleanup` já processa arquivos substituídos/removidos em lotes e deve ser integrado quando a operação corresponder ao ciclo de imagens.
- `aculta_forum.attachment_cleanup` demonstra verificações de referências atuais em campos, embeds e revisões; portar somente os trechos genéricos necessários, preservando autoria/licença e atribuindo a lógica específica de Fórum ao seu módulo.
- `entity_usage` é uma fonte auxiliar, nunca prova única de ausência de referências. Consultar também referências estruturadas e conteúdo versionado suportado pelos adapters.

## Entrega planejada no Portal

1. Auditoria somente leitura e matriz de adapters de conteúdo/anexos por módulo proprietário.
2. Serviço de planejamento que recebe IDs explícitos e emite relatório de dependências, candidatos a remoção e itens retidos; nenhum efeito colateral.
3. Executor em lotes, iniciado por comando administrativo explícito, que revalida dependências e acessos antes de cada exclusão e delega remoção de imagens ao `aculta_image`.
4. Testes de referências compartilhadas, revisões, traduções, embeds sem registro de uso, concorrência e falha parcial; logging sem conteúdo sensível.
5. Homologação no banco dedicado e Runtime de testes com backup restaurável antes de qualquer limpeza real.

## Integrações por projeto

| Projeto | Trabalho planejado | Limite do módulo |
|---|---|---|
| `aculta_image` | Adaptar a fila e API já existentes para aceitar solicitações do coordenador Portal; conservar rechecagem de uso, invalidação de derivados, quarentena e exclusão de imagens. | Continua sendo o único dono da remoção física de imagens. |
| `aculta_forum` | Trocar as verificações genéricas locais por chamadas ao serviço comum; manter no adapter do Fórum a descoberta de embeds de Comments/ForumPost e revisão. | Só o Fórum sabe interpretar suas entidades, galerias e comentários. |
| `aculta_wiki` | Fornecer descoberta explícita de relações e anexos da Wiki quando seus contratos forem finalizados; usar o coordenador ao excluir verbete pela operação autorizada. | Categoria/imagem ainda são dívidas; nenhum arquivo é apagado por inferência. |
| `aculta_magazine` | Ao excluir publicação selecionada, enviar relações estruturadas e embeds suportados para verificação compartilhada; preservar galerias, imagens e arquivos ainda usados por outro conteúdo. | Revista mantém workflow, publicação e aprovação editorial. |
| `aculta_gallery` | Informar relações de galeria → Media e impedir remoção de Media compartilhado por outras galerias ou conteúdos. | Gallery mantém finalidade, publicação e acesso das galerias. |
| Document/PDF Viewer e LMS Resources | Informar referências a arquivos e Media de documentos/turmas, com as respectivas dependências e acesso Group, e consumir a verificação comum antes de qualquer limpeza. | Módulos donos preservam acesso privado e download autorizado. |

O serviço comum ainda não existe. IDX 0.1-R deve fixar seu contrato antes das
refatorações: os módulos de origem descobrem relações; Portal verifica uso e
coordena ciclo/retentativas; `aculta_image` executa remoção física de imagem.
Adaptar um consumidor por vez e manter a limpeza existente ativa até prova de
equivalência.

## Estado do Runtime consultado

Na prévia de 2026-10-22 foram encontrados 12 Nodes `revista`, quatro com marcadores de embed e nenhum `media_gallery`. Após autorização explícita para excluir os 12 no Runtime de teste, os Nodes, 17 Media `image` e 17 arquivos de imagem sem uso externo foram removidos. Um Media `remote_video` permaneceu por falta de ownership definida. Evidências, salvaguardas e verificação estão em [MAGAZINE-TEST-CLEANUP-2026-10-22.md](MAGAZINE-TEST-CLEANUP-2026-10-22.md). A política e o executor compartilhado ainda estão planejados.
