# Changelog — ACULTA Game

## 0.1.0-dev.11 — excluir Forum contrib das conquistas — 2026-10-19

- O trigger `forum_comment` só aceita `aculta_discussion_post` hospedado em
  Node `aculta_forum_topic` publicado. Comentários do bundle legado
  `comment_forum` em `forum` e comentários sem conta deixam de gerar conquistas
  contemporâneas.
- O caminho transitório registrado em dev.10 fica substituído; `field_autor_legado`
  e entidades em sincronização permanecem excluídos. Sem backfill ou novo storage.
- `ForumParticipationPolicyTest` cobre a rejeição explícita do bundle legado.
  Unit 8 testes / 35 assertions PASS; PHPCS novo e Portal 970, boundary 1374,
  mapa 154 e `git diff --check` PASS. Runtime `toca`: Game habilitado, hook
  resolvido após cache rebuild; leitura encontrou 8 concessões já existentes.
  Nenhum evento foi disparado e nenhuma concessão foi escrita. RUNTIME STATUS:
  PARTIAL.

## 0.1.0-dev.10 — integração com o Fórum ACULTA — 2026-10-19

- O trigger existente `forum_comment` reconhece respostas publicadas em
  `aculta_discussion_post` hospedadas por `aculta_forum_topic`, incluindo
  aprovação posterior sem conceder em edições subsequentes.
- Até a remoção segura do Forum contrib, o par de bundles antigo permanece
  temporariamente elegível; `field_autor_legado` e entidades em sincronização
  nunca geram concessões contemporâneas.
- Nenhum critério, ID de conquista, concessão existente ou storage mudou; não há
  backfill e o Runtime permanece DEFERRED.
- Teste Unit direcionado cobre Forum novo, aprovação pendente, outros contextos,
  acervo histórico e caminho transitório do módulo contrib.

## 0.1.0-dev.9 — G5.4: ilustrações antiproibicionistas — 2026-10-15

- Quatro conquistas de participação recebem arte temática própria; novas conquistas sem arquivo usam selo geral canábico e antiproibicionista.
- PNGs 512 × 512 com transparência; arte final gerada para este Portal, mantendo intactos os três arquivos históricos importados.
- `README`, documento de direção de arte e smoke browser atualizados.

## 0.1.0-dev.8 — G5.3: conquistas no perfil público — 2026-10-15

- Perfis públicos exibem conquistas obtidas em cartões com imagem própria ou símbolo ilustrativo, título, descrição e destaque; avatar de pessoa sem foto usa o acervo do `aculta_user`.
- Upload informa recomendação de imagem quadrada de 512 × 512 px e limites aceitos.
- Visibilidade individual e global continuam controladas pela pessoa; dados de cursos, pedidos e concessões ficam privados.
- Nenhum filtro/busca aparece no perfil: segue o padrão GamiPress de listar apenas as conquistas da pessoa exibida.
- Verificação Firefox desktop/mobile no Runtime; gates Drupal 11 e fronteira módulo/tema.

## 0.1.0-dev.7 — G5.2: apresentação e avisos de conquistas — 2026-10-15

- Editor reorganizado por identificação, imagem e condições; critérios separados por participação, formação, apoio e equipe.
- Imagem administrável por upload validado; remoção exige confirmação e exclui arquivo enviado sem outros usos.
- Conta exibe catálogo único com estado, condição, privacidade, destaque e ordenação; removida a lista duplicada de troféus.
- Nova conquista integra Avisos como item dispensável e enfileira e-mail; pessoa pode desativar e-mail.
- GamiPress serviu de referência de UX para critérios agrupados, progresso por conquista e controles de exibição; sem código copiado ou módulo instalado.
- Firefox no Homelab 14/14; exclusão de arquivo gerenciado confirmada com fixture removida. Entrega de e-mail não verificada; cron não executado.

## 0.1.0-dev.6 — G5.1: conquistas administráveis — 2026-10-15

- Criação/edição de conquistas com formas de concessão fechadas, disponibilidade, imagem e versão do critério.
- Apoiadore: pedido concluído, apoio no sistema de doações ou concessão administrativa, conforme decisão explícita.
- Conceder/revogar manualmente com ator, data e motivos na entidade histórica; consulta administrativa paginada.
- Definições e histórico permanecem no User; nenhuma tabela de concessões/progresso paralela.
- Update User 10003 adiciona dois campos opcionais; dados antigos preservados por digest.
- HTTP 34/34, evento de apoio repetido sob rollback e PNGs servidos com hash original conferidos.
- Avisos de conquista/deduplicação de entrega permanecem em G5.2; não foi instalado ECA.


## 0.1.0-dev.5 — troféus do site antigo — 2026-10-15

- Importados Fundadore, Associado/a Antiproibicionista e Apoiadore, com PNGs originais e manifesto de origem/hash.
- IDs `associado` e `apoiador` preservados; Fundadore novo, manual, sem concessões automáticas.
- Catálogo visível na própria conta e na área Game; imagens nas conquistas efetivamente obtidas, respeitando privacidade.
- Nenhuma titularidade/conta antiga importada; nenhuma dependência WordPress/GamiPress.
- Regra ampliada de Apoiadore aprovada pelo responsável: pedido, apoio direto ou administrador; execução na fase administrativa seguinte.

## 0.1.0-dev.4 — G4: missões, descoberta e administração inicial — 2026-10-15

- Três missões unitárias com configuração/schema e progresso por concessões reais.
- Entrada destacada na visão geral; Game abaixo de Avisos na administração MAIN.
- Update 10001 instala somente configuração ausente; nenhum dado migrado.
- Homelab: Firefox 8/8, HTTP 17/17; preferências e configuração de teste restauradas.
- Código inicial em commit compartilhado `5b325e5`; contrato/evidência em EXECUCAO.

## 0.1.0-dev.3 — G3: conta, perfil e escolhas de exibição — 2026-10-15

- Lista própria e formulário na aba Conquistas; entrada pelos ajustes de privacidade.
- Projeção pública única, ocultação global/individual, destaques e ordenação.
- Preferências no User Data do User, preservadas após desinstalar Game.
- Perfil privado, conta bloqueada e legado não publicam conquistas; sem evidência
  de curso/pedido na projeção. Render API e tags de invalidação preservados.
- Helper registrado no Status; nenhum novo componente, CSS ou rota.
- Renderizações no Homelab verificadas; interação e matriz funcional pendentes,
  conforme [EXECUCAO.md](docs/EXECUCAO.md).

## 0.1.0-dev.2 — G2: integridade dos eventos — 2026-10-15

- Aprovação posterior de comentário, publicação de revisão da Wiki e transições
  de pedido/LMS verificadas; LMS usa `getUserId()` da API instalada.
- Migrate/sync, contas bloqueadas e papel legado excluídos de novas concessões.
- Concessão/revogação pela API serializadas com lock até o commit externo.
- Sem alteração de critérios pessoais ou de reconhecimento de apoio existentes.
- Evidências e limites de validação em [EXECUCAO.md](docs/EXECUCAO.md).

## 0.1.0-dev.1 — G1: integração e fonte única — 2026-10-15

- Extensão opcional do Portal, dependente de User, sem dependência inversa.
- Avaliador automático transferido do User; catálogo, serviço, entidade e dados
  mantidos na fonte existente, sem migração ou storage paralelo.
- Inventário e evidências em [EXECUCAO.md](docs/EXECUCAO.md).

## 2026-10-15 — abertura documental e pesquisa

- Registradas conquistas e missões sem ranking, XP/níveis ou benefícios financeiros.
- Conquistas públicas por padrão, com privacidade, seleção de destaques e ordenação.
- Inventariada a base de badges do `aculta_user`, com transição sem duplicar concessões.
- Pesquisados módulos, código de releases, licenças, arquitetura e roadmap.
- Quatro pacotes inspecionados com checksums; nenhum código copiado ou módulo instalado.
- Entrega exclusivamente documental; sem versão executável nova ou alteração de Runtime.
- [Evidência de revisão e validação estática](../../../../../../docs/portal/FORUM-GAME-PESQUISA-VALIDACAO.md).
