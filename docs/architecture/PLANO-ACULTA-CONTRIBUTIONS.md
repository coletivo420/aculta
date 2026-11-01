# Plano de arquitetura e implementação - ACULTA Contributions

**Estado:** F0 revalidado; F1/F2 parciais; Wiki dual-store; F4 concluída com escopo restrito a perfis públicos não reivindicados; F5 parcial; fila descoberta pelo Content Admin; avisos de submissão e decisão de Organização/Local/Evento no inbox via envelope mínimo e outbox; fila/revisão administrativa de Evento, `event_create` e `event_update` com interfaces de autor implementados; workspace e ressubmissão estruturada implementados; updates Messenger e Wiki homologados parcialmente no Runtime; autorização autenticada de domínio pendente
**Base de código auditada:** branch `dev`, checkout baseado em `821f99810` (2026-11-01); auditoria F0 revalidada após o teste de instalação limpa e o conflito de Evento duplicado e referenciada em [`INVENTARIO-ACULTA-CONTRIBUTIONS.md`](INVENTARIO-ACULTA-CONTRIBUTIONS.md). Revalidar HEAD antes de cada etapa, pois `dev` integra outras frentes.
**Escopo:** motor de propostas comunitárias reutilizável por domínios ACULTA, sem depender de Workflow Participants ou de Content Moderation para o ciclo de contribuições.

## 1. Decisão de arquitetura

Criar o módulo first-party `aculta_contributions` como proprietário do ciclo de vida das propostas comunitárias: submissão, revisão da proposta, estados, decisões, comentários de revisão, concorrência, conflitos com a origem, trilha de auditoria e aplicação controlada.

Os módulos de domínio continuam donos dos dados e das regras específicas:

- `aculta_wiki`: verbetes, campos permitidos, elegibilidade e política editorial da Wiki.
- `aculta_events`: Eventos, Locais e Organizações, campos permitidos, titularidade, membros e regras de publicação.
- `aculta_user`: identidade, membros, titularidade e reivindicação de organizações.
- `aculta_content_admin`: descoberta das filas de revisão e entrada no painel administrativo, sempre respeitando o acesso nativo.
- `aculta_messenger`: avisos para proponente, revisores e responsáveis.
- `aculta_index`: descoberta do conteúdo oficial publicado. Propostas privadas nunca entram no índice.

O motor não deve ser um sistema universal de moderação. Denúncias, comentários de Fórum, reivindicações de titularidade, moderação de mensagens e decisões editoriais internas permanecem em seus módulos. Podem integrar-se por eventos ou links, sem serem convertidos em propostas.

## 2. Evidências e estado atual

### Evidências de implementação registradas em `46ec52a96` (2026-11-01)

- `aculta_contributions` agora define `aculta_contribution`, entidade privada
  revisionável, registro de adaptadores confiáveis e serviços de submissão,
  revisão, decisão, aplicação seletiva, conflito e retirada.
- A entidade preserva módulo owner, adapter, purpose e idioma; o payload é
  validado pelo adapter. A política de submissão usa Flood configurável, chave
  de idempotência armazenada como hash e lock. A aplicação exige revisão-base
  igual e decisão explícita de campos.
- Contexto privado da origem e escolhas do revisor ficam separados do payload
  aplicável e são persistidos por revisão. O update 10001 instala os novos
  campos em uma instalação existente.
- O adapter Wiki foi registrado. Novas submissões e os consumers de status,
  retirada, decisão, workspace, fila editorial e publisher usam leitura
  dual-store; novos envios ficam fechados até existir checkpoint para toda
  proposta legada. O workflow oficial da Wiki continua em Content Moderation.
  No Runtime, schema Wiki 10003 já estava registrado; a auditoria encontrou zero
  propostas/revisões legacy, Contributions, checkpoints e revision maps; o gate
  de migração retornou completo. O update 10003 já está aplicado no Runtime; não havia
  conteúdo a copiar.
- Unit: registry/state machine 3 testes / 19 assertions; Kernel 12 testes / 138
  assertions (passaram em `aculta_simpletest`; combinado 15/157). O Kernel revelou e corrigiu a
  dependência `options` e um fixture que usava incorretamente UID 1. PHPCS
  Drupal, lint PHP e `git diff --check` passaram após as alterações recentes.
  Updates Messenger 10015/10016 foram executados no Runtime após os reparos
  descritos na homologação abaixo.

- O update Wiki 10003 está preparado em lotes e preserva ID, UUID e revision ID,
  com reconciliação de cada proposta/revisão. Testes focais do corte/status
  passaram 2/264; lifecycle genérico 10/109; consumer Messenger 5/55; adapter
  Wiki 3/5; histórico/retry, colisão de ID e revisão traduzida passaram 3/133.
  Acesso autenticado de ponta a ponta no Runtime continua pendente. O modelo Wiki antigo não persistia campos ou
  modos de lista selecionados ao aceitar, portanto essa decisão histórica não
  pode ser reconstruída. O update aborta uma proposta se encontrar colisão de
  ID ou traduções históricas; essas situações exigem migração explicitamente
  tratada antes da troca de consumidores.

A implementação do núcleo é progresso parcial, não conclusão das fases F1/F2:
persistência, submissão, aplicação seletiva, conflito, Flood configurável,
rollback provocado pelo adapter e acesso delegado ao owner de domínio têm Kernel
MariaDB. O ciclo de retorno para ajustes, nova revisão do autor e retirada final
também tem cobertura Kernel. Updates/configuração Runtime foram homologados;
autorização autenticada e jornadas de domínio ainda precisam de validação. O
Kernel verifica contenção de submissão entre backends independentes/conexões
MariaDB; ainda não há runner de processos SO concorrentes.

A auditoria do HEAD confirmou:

- A Wiki mantém `aculta_wiki_proposal` e fluxo legado revisionável; novos envios
  gravam em `aculta_contribution` após gate de checkpoints. Os consumers aceitam
  ambas entidades; preservação das tabelas antigas e reconciliação Runtime não
  estão homologadas.
- O serviço `WikiProposalDecision` ainda usa interfaces de `content_moderation` para transições e validação editorial da revisão oficial. Portanto, extrair a entidade de proposta não elimina por si só essa dependência.
- `aculta_events` tem entidades próprias `aculta_event`, `aculta_event_location` e `aculta_event_organization`, formulários e controles de acesso, páginas/catálogo e providers para Index.
- Organizações têm titularidade/reivindicação e membros. Locais e eventos vinculam organizador; os access handlers têm regras de publicação, propriedade e administração.
- `aculta_content_admin` agrega coleções moderáveis sem copiar dados nem substituir a autorização da rota de origem.
- `aculta_ugc` já opera denúncias com entidade, fila, estados e decisões próprios.
- `aculta_messenger` é a central de mensagens e notificações.

O inventário F0, incluindo a matriz domínio × operação × policy × applier, foi
revalidado no checkout baseado em `821f99810` em 2026-11-01 após a cobertura de falha fechada
para organizações reivindicadas, conforme
[`INVENTARIO-ACULTA-CONTRIBUTIONS.md`](INVENTARIO-ACULTA-CONTRIBUTIONS.md).
Claims continuam exclusivamente em `aculta_user`; endereço completo de Local
usa CEP central e apenas BR. Criação de Organização com claim pendente e
propostas adicionais para membros de organização reivindicada seguem fora do
adapter até fechar política e experiência do autor.

O Drupal `Workflow Participants` exige `content_moderation`, associa participantes a entidades moderadas e descreve cobertura limitada para entidades que não sejam Nodes. Ele gerencia designação de editores/revisores dentro do workflow do Core, não propostas comunitárias independentes. Não usá-lo como dependência nem adaptar seu código como base.

O Content Moderation do Core mantém revisão publicada enquanto outra revisão passa por estados de workflow. Esse padrão informa os requisitos de segurança e publicação, mas o motor comunitário será baseado em propostas separadas e contratos dos domínios. Não afirmar que a Wiki já é independente do Core: essa migração é uma frente distinta, com risco e escopo próprios.

### Homologação de banco do Runtime — 2026-11-01

- Ambiente confirmado: `toca`, checkout servido `/home/piradopirata/projetos/aculta`
  no commit `46ec52a96`, URI `http://default`, banco `aculta_runtime`, Drupal
  11.4.10, 631 tabelas. Schema final: Wiki 10003, Contributions 10001 e
  Messenger 10016. `updatedb:status` não lista pendências e
  `config:status` não mostra diferenças.
- A leitura Wiki encontrou zero propostas/revisões legacy, zero Contributions,
  zero checkpoints e zero revision maps; o gate de migração retornou completo.
- Messenger 10015/10016 concluíram. O aviso usa bundle e fields dentro do limite
  Drupal de 32 caracteres; 10015 instala apenas os nove objetos do aviso e
  ignora entidades já presentes. 10016 preserva formatos de lista e mapa. Os
  11 objetos ativos foram exportados seletivamente para `config/sync`; o
  settings sincroniza também `private_message_digest_anchor`.
- Snapshots integrais fora do repositório foram validados com `gzip -t` e
  SHA-256. O último, posterior à sincronização, está em
  `/home/piradopirata/worktrees/backup-runtime-messenger-20261101-post-import/`
  com hash `daf48e769b5d02a343a800d8dc3a1db5f6f4645607904fe9c3eef291c6ba2e46`.
  Houve tentativas abortadas por IDs acima de 32 caracteres e por instalação
  global de default de amizade divergente; os updates foram então corrigidos e
  concluídos seletivamente. `config:import --diff` também aplica a importação:
  o diff removeu somente traduções órfãs. A conferência posterior ficou limpa.
- Nenhuma mensagem de aviso foi criada na homologação. Testes autenticados de
  acesso a filas e jornadas Events continuam um gate separado.

## 3. Conceitos e modelo proposto

### 3.1 Proposta comunitária

Uma proposta deve registrar, no mínimo:

- UUID, domínio/módulo de origem e tipo de contribuição.
- Entidade alvo (tipo e UUID), ou intenção de criação sem alvo.
- Proponente e data; idioma/purpose quando aplicável.
- Revisão-base ou fingerprint/versionamento dos campos relevantes da origem.
- Payload estruturado allowlisted, com valores tipados; nunca aceitar nomes arbitrários de campos do cliente.
- Estado atual e revisões imutáveis da proposta.
- Histórico de decisões, revisor, momento, parecer e campos aceitos.
- Metadados de deduplicação/idempotência, sem dados pessoais desnecessários.

Estados iniciais: `submitted`, `needs_changes`, `accepted`, `declined`, `withdrawn`, `conflict`. `conflict` pode ser derivado ou persistido conforme decisão técnica; o comportamento deve exigir reanálise quando a origem divergir da revisão-base. Estados finais não podem ser reabertos sem ação explícita e histórico.

### 3.2 Intenções

- `create`: sugerir novo conteúdo comunitário.
- `update`: propor alteração de entidade existente.
- Opcionalmente `correction` como categoria de motivo dentro de `update`, sem duplicar o ciclo de vida.

Proposta de criação gera entidade oficial somente após aprovação, passando por validações e deduplicação. Proposta de alteração nunca edita o conteúdo ativo ao ser submetida.

### 3.3 Contratos de domínio

Criar contratos explícitos, versionáveis e testáveis, por exemplo:

- `ContributionTypeDefinitionInterface`: tipo, entidade alvo suportada, campos permitidos, formulários e apresentação.
- `ContributionPolicyInterface`: autorização para submeter/decidir, elegibilidade, titularidade e acesso à origem.
- `ContributionApplierInterface`: valida e aplica seleção allowlisted sob revisão-base/lock/transação.
- `ContributionSourceResolverInterface`: resolve alvo, estado e versão-base; retorna acesso cacheável.
- `ContributionEventInterface`: eventos de submissão, decisão, conflito e aplicação concluída.

Não criar APIs genéricas que aceitem classe, campo, serviço ou rota arbitrários enviados pela requisição. Descobrir adaptadores por plugin manager/tag com schema e validação em servidor.

## 4. Integrações e responsabilidades

### 4.1 Wiki

Extrair gradualmente o motor genérico da proposta existente, preservando a semântica funcional da Wiki. A Wiki fornece intents e campos permitidos para verbetes, regras de autoria, revisores, notas e aprovação.

Não mover para `aculta_contributions` regras de conteúdo, workflow da Wiki, autorização de revisão de Nodes ou código de busca. O status da origem, revisão-base, permissões e publicação continuam revalidados pela Wiki.

Como `WikiProposalDecision` depende de Content Moderation, há duas entregas separadas:

1. Extrair propostas e contratos sem alterar o workflow oficial atual.
2. Projetar e implementar eventual substituição do workflow Core da Wiki por mecanismo próprio, incluindo revisões oficiais, grants, leitura de revisão, publicação, rollback e migração. Não remover a dependência do Core antes de cobrir esses requisitos e dados.

### 4.2 Eventos, Locais e Organizações

Prioridade inicial:

1. Organização: criação e correção de perfil; autorização por titularidade e membros ativos; respeitar status reivindicado/não reivindicado e reivindicação pendente.
2. Local: criação e atualização de endereço, descrição, acessibilidade, coordenadas e instalações. Endereço completo, coordenadas precisas e contato devem obedecer à política de exposição e não aparecer em payload público/log.
3. Evento: criação e correção de descrição, datas, local, organizador e informações de acesso. Revalidar organizador, vínculo com local, datas e estado do evento na decisão.

Criar adaptadores no `aculta_events`; não deslocar entities nem access handlers ao módulo central. A aplicação deve rejeitar ou sinalizar conflito se a entidade mudou desde a proposta. Eventos publicados/índice público devem refletir apenas a entidade oficial depois da aplicação aprovada.

### 4.3 Organizador

Não criar uma entidade de Organizador antes de fechar o modelo. Hoje organizador é usuário associado e/ou organização cultural com titularidade e membros. O motor deve consultar `aculta_user` e `aculta_events`; propostas não inventam uma segunda fonte de titularidade.

### 4.4 Painel de revisão

Integrar a fila ao `aculta_content_admin` através de rota de coleção cuja autorização verifica acesso à proposta e ao contexto de domínio. O painel descobre e lista; a decisão permanece no serviço do `aculta_contributions` mais o adaptador de domínio. Não conceder acesso por o item aparecer na lista.

### 4.5 Mensageria

Publicar eventos mínimos no bus central. O Messenger envia avisos conforme preferências e política; não decide aprovação nem guarda estados da contribuição. Revalidar acesso antes de mostrar detalhes privados. Nunca incluir payload completo de proposta em e-mail ou log.

### 4.6 Indexação, mapas e calendários

- Index: só conteúdo oficial público; mudança semântica em projeções exige atualizar versão/fingerprint e seguir procedimento de rebuild.
- Maps: auxiliar localização/validação; sem ownership da proposta ou do endereço.
- Calendar/ICS: importações externas podem futuramente criar propostas em lote, com prévia, relatório de conflitos, idempotência e aprovação antes de alterar dados oficiais. Não misturar importação ICS com o CRUD inicial.
- Fórum/UGC: oferecer entrada/encaminhamento quando útil; denúncia permanece denúncia e discussão permanece discussão.

## 5. Fases e microfases

### F0 - Inventário e contratos
- Ler instruções locais e confirmar HEAD.
- Mapear classe, tabela, configuração, rota, permissão, campos e testes da proposta Wiki.
- Mapear entidades/fields/access de Organization, Location e Event e titularidade/membership.
- Mapear filas de conteúdo do painel, eventos Messenger e políticas de retenção/privacidade.
- Fechar contrato de versão-base, aplicação seletiva, estados, acesso e conflitos.
**Saída:** ADR e matriz domínio × operação × policy × applier.

### F1 - Esqueleto do módulo
- Criar `aculta_contributions`, info, services, schema, permissões administrativas, entity/plugin definitions e documentação.
- Criar entidade privada revisionável de proposta e armazenamento de decisão/histórico conforme modelo escolhido.
- Implementar acesso negado por padrão; listagens sem vazamento, proteção CSRF/método e cache contexts.
- Não integrar ainda todos os domínios.
**Saída:** instalação limpa, proposta inacessível anonimamente, API sem adaptador falha fechada.

### F2 - Ciclo genérico
- Submissão idempotente e proteção de flood/abuso.
- Revisão e alteração da proposta sem alterar origem.
- Estados, parecer plain text, aceitar/recusar/pedir ajuste/retirar conforme política.
- Lock de concorrência e transação ao decidir/aplicar.
- Comparação da versão-base e conflito explícito.
- Registro de autor/revisor/tempo e audit trail sem armazenar dados desnecessários.
- Kernel valida contenção em conexão independente e processo OS separado: a
  submissão concorrente é negada e nenhuma segunda proposta persiste (suíte
  Entity + Lifecycle: 14 testes / 161 assertions no HEAD `50d01ac2c`; os commits
  posteriores do Portal/LMS não alteram esses módulos.
**Saída:** ciclo completo de proposta isolada e policy fake/fixture para integração.

### F3 - Migração Wiki
- Adaptar Wiki como primeiro consumidor.
- Migrar propostas, UUIDs, revisões, autoria, estado, alvo, valores tipados e decisões sem perda.
- Preservar links/rotas e acesso existentes ou documentar redirecionamento compatível.
- Comparar comportamento do applier campo a campo com a versão atual.
- Manter workflow Content Moderation existente nesta fase.
**Saída:** migração idempotente, contagens reconciliadas, Wiki opera pelo contrato comum.

### F4 - Organizações
- [x] Criar proposta privada para Organização por não administradores, pelo formulário próprio e pelo modal do Evento; nenhuma entidade é criada nem vinculada antes da revisão.
- [x] Decisão administrativa de criação exige seleção explícita dos campos e título; lifecycle e modal/admin Kernel completos passaram (13/437 e 8/266 assertions).
- [x] Editar proposta de perfil de Organização existente com campos allowlisted.
- [x] Preservar ownership em `aculta_user`: Contributions só editam perfil público de organização não reivindicada, excluem titularidade/membros/reivindicação do allowlist e falham fechado para organização reivindicada. Criação aprovada permanece não reivindicada, com proponente como organizador.
- [x] Definir conflito para organização: mudanças no perfil ou estado de reivindicação invalidam a versão-base; a aplicação também revalida `isUnclaimed()`, e nome duplicado conflita sem mescla. Kernel novo `testContributionCannotSubmitOrApplyToClaimedOrganization`: 1/27 PASS em `aculta_simpletest`.
**Saída:** criação e atualização sem alteração pública pré-aprovação.

### F5 - Locais
- [x] Implementar propostas privadas de criação/atualização com allowlist do perfil público e revisão-base; confirmar em Kernel que criação fica ausente até aprovação e atualização obsoleta conflita.
- [x] Reutilizar o formulário de endereço central e fixar BR; excluir coordenadas, identificadores OSM e campos editoriais/protegidos do payload aplicável.
- [x] Impedir duplicidade provável por título/endereço durante submissão/aplicação, sem mesclar automaticamente.
- [x] Enfileirar resultados de Organização e Local por evento mínimo, sem payload, endereço ou parecer; Messenger persiste avisos privados no inbox e revalida revisão, autor e acesso ao listar/abrir.
- [ ] Definir política/categoria de e-mail para esses avisos; o incremento atual não envia e-mail nem digest.
- [x] Workspace privado de leitura para Organização, Local e Evento em `/culturas/propostas`; ressubmissão em `/culturas/propostas/{uuid}/revisar` cria nova revisão após `needs_changes`, com payload tipado, endereço BR central e acesso restrito ao owner. A execução focal registrada foi 2/64, incluindo endereço BR; não repetida neste HEAD. Formulários de Evento para `event_create` e `event_update` estão implementados; o envio gera aviso privado por envelope mínimo. Homologação Runtime segue pendente.
- [ ] Revalidar Eventos ligados ao Local antes de qualquer fusão/alteração sensível; propostas atuais não fazem fusão.
**Saída:** catálogo, seletor de local, página pública e Index mantêm consistência.

### F6 - Eventos
- Adapter `events_event` implementa `event_create` e `event_update`, valida allowlist e intervalos, revalida organizador/Organização/Local, usa fingerprint com Datas e aplica sob lock/transação. A aplicação recarrega o autor e revalida capability e Entity Access atuais, limpando o cache do Evento e recarregando Locais das Datas antes de verificar acesso; a validação registrada no HEAD `dd6ba130c` da suíte de lifecycle e fronteira cobre revogação de membership, Local tornado privado, criação privada em rascunho e atualização seletiva com Datas aditivas (37 testes / 1.275 assertions; 24 depreciações upstream). Os commits posteriores de LMS não alteram esses módulos.
- Decisões de propostas de Evento enfileiram envelope mínimo e são consumidas pelo mesmo inbox Messenger de Organização/Local; não incluem payload nem parecer. A revisão administrativa usa `administer aculta events` no MAIN e a fila aparece no Content Admin via autorização nativa. Na aplicação, Kernel comprova revalidação quando membership da Organização é revogado e quando o Local proposto passa a privado. O workspace abre propostas de Organização, Local e Evento. A ressubmissão após `needs_changes` usa formulário estruturado; `ContributionLifecycle::revise()` revalida owner, estado, capacidade e revisão-base. Autores propõem `event_create` em `/culturas/propostas/evento/nova` e `event_update` em `/culturas/eventos/{aculta_event}/propor-alteracao`; ambos salvam Contributions privadas e emitem aviso mínimo. Atualizações exigem Entity Access e capturam fingerprint da versão persistida; Datas novas são opcionais e append-only. No HEAD `34e79950e`, a classe Kernel completa passou 27/888 e seis cenários `event_update` passaram 6/171, cobrindo submissão, aplicação, perda de acesso, membership, Local e origem alterada. Updates Messenger 10015–10016 foram executados. A classe Events completa foi revalidada com 29/941, incluindo conflito exato de título/Data duplicados; testes autenticados e jornadas Events no Runtime continuam pendentes.
- Manter ICS/feeds existentes sem importação automática no escopo inicial.
**Saída:** evento só entra no catálogo público após aprovação/aplicação.

### F7 - Fila e notificações
- [x] Expor as filas de propostas de Organização, Local e Evento no painel `aculta_content_admin` usando autorização nativa das rotas Events; Registry Unit 1/8 e Kernel Events 21/691 passaram localmente.
- [x] Persistir avisos privados de decisão de Organização/Local/Evento no inbox Messenger, com revalidação da revisão/autor/acesso e outbox que permite recuperar falha transitória ao gravar na Queue API.
- [x] Entregar workspace privado para consultar estado, payload e retorno editorial de propostas de Organização, Local e Evento; avisos Messenger abrem a proposta. Execuções registradas: Kernel Events 23/753 e fronteira de domínio 10/369; não repetidas neste HEAD.
- [x] Permitir ressubmissão estruturada pelo autor somente em `needs_changes`, usando o ciclo genérico e nova revisão imutável. Kernel focal 2/64, incluindo endereço BR fixo.
- [x] Oferecer formulário inicial `event_create` em `/culturas/propostas/evento/nova`; salva apenas Contribution privada, revalida capability e normaliza Datas. A suíte combinada registrada no incremento foi 37/1.275; não repetida neste HEAD.
- [x] Oferecer formulário de proposta `event_update` para usuários com Entity Access `update`; captura revisão-base persistida e não altera o Evento até aprovação. Kernel focal 1/35.
- [x] Avisar o autor no envio de propostas de Evento com envelope mínimo, revisão imutável, outbox e revalidação no Messenger. Updates 10015–10016 foram executados no Runtime após reparo dos IDs e instalação seletiva; config sync importado e limpo.
- [ ] Definir política/categoria de e-mail; avisos de Contributions permanecem somente no site e fora do digest.
- Notificações não revelam campos privados nem fornecem links que bypassam Entity Access.
**Saída:** revisor autorizado consegue encontrar e decidir; usuário só vê suas propostas.

### F8 - Importação e integrações ampliadas
- Avaliar ICS/imports como geradores de propostas em lote.
- Avaliar atalhos do Fórum e fluxo UGC como origem/encaminhamento, mantendo os domínios separados.
- Index, Calendar e Maps recebem apenas mudanças aprovadas e visíveis.
**Saída:** integração opt-in, idempotente, auditável e sem publicação silenciosa.

### F9 - Independência do workflow Core na Wiki (decisão/gate separado)
- Inventariar todo uso de Content Moderation/Workflows em Wiki, configuração, permissões, revisões e ambiente.
- Especificar mecanismo próprio para rascunho, revisão oficial, publicação, acesso a revisão, rollback e histórico.
- Fazer migração reversível/testada; comparar grants e visibilidade antes/depois.
- Remover dependência Core somente após paridade funcional e dados reconciliados.
**Saída:** aprovação explícita de arquitetura e plano de migração antes de executar. Esta fase não é pré-requisito para o novo módulo aceitar proposals de Events.

## 6. Segurança e integridade

- Negar acesso por padrão; não confiar em UID, entity type, field names, estado ou URL enviados pelo cliente.
- Revalidar usuário ativo, permissões, papel, membership/titularidade e acesso à entidade em submissão, leitura, decisão e aplicação.
- Forçar allowlist de campos e validação de valores; nunca aplicar propriedades internas como proprietário, publicação ou estado de moderação sem regra explícita.
- Lock por proposta e alvo; confirmar expected revision/fingerprint dentro da transação imediatamente antes de salvar.
- Idempotência por proponente, token e alvo/operação; limites contra abuso configuráveis e documentados, sem fixar limites não aprovados como decisão de produto.
- Sanitização de pareceres e payload; texto público mínimo; segredo e dados pessoais fora de logs.
- Mensagens privadas só para proponente/revisores autorizados; respostas públicas da entidade só após publicação oficial.
- Retenção e exclusão/anonimização definidas com política LGPD; não apagar trilha necessária sem regra.
- Falhas de Messenger não podem reverter conteúdo aprovado; eventos devem ser recuperáveis/deduplicados.

## 7. Testes e critérios de aceite

Reusar testes existentes da Wiki e construir testes do novo contrato. Gates por fase:

- Unit: máquina de estados, allowlist, conflito, idempotência e policy.
- Kernel: persistência revisionável, transação/rollback, locks, acesso com duas contas, aplicação seletiva e migração Wiki.
- Events: Organization, Location e Event com titulares, membros, proponente sem vínculo, revisor, entidade alterada e acesso revogado.
- Painel: somente links autorizados e acesso revalidado na rota/ação.
- Messenger: avisos únicos, sem payload sensível, links revalidam acesso.
- Index: propostas privadas nunca projetadas; publicação aprovada atualiza projeção oficial conforme os gates do Index.
- Migração/Runtime: backup, contagens, configuração, filas e conteúdo reconciliados; homologação real registrada separadamente de testes locais.

Aceite final: nenhum conteúdo oficial é alterado na submissão; só adaptadores de domínio podem aplicar campos; conflitos bloqueiam sobrescrita; toda decisão é auditável; as propostas privadas ficam fora do Index; Events, Wiki e painel preservam access policy; instalação limpa e update existentes são suportados.

## 8. Exclusões explícitas do escopo inicial

- Substituir o sistema de denúncias `aculta_ugc`.
- Reescrever moderação do Fórum, comentários da Revista, reivindicações de titularidade ou comunicação interna como propostas.
- Autoaprovar/importar conteúdo externo.
- Publicar propostas diretamente no Index.
- Remover Content Moderation da Wiki durante a extração inicial.
- Criar entidade própria de Organizador sem decisão de domínio.
- Introduzir redesign visual ou mudança de rotas públicas sem ADR.

## 9. Referências

### ACULTA, branch `dev`
- `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Entity/WikiProposal.php`
- `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiProposalSubmission.php`
- `web/modules/custom/aculta_portal/modules/aculta_wiki/src/Service/WikiProposalDecision.php`
- `web/modules/custom/aculta_portal/modules/aculta_events/src/Entity/Event.php`
- `web/modules/custom/aculta_portal/modules/aculta_events/src/Entity/EventLocation.php`
- `web/modules/custom/aculta_portal/modules/aculta_events/src/Entity/EventOrganization.php`
- `web/modules/custom/aculta_portal/modules/aculta_events/src/Access/`
- `web/modules/custom/aculta_portal/modules/aculta_content_admin/src/Moderation/ModeratableCollectionRegistry.php`
- `web/modules/custom/aculta_portal/modules/aculta_ugc/src/Service/ReportService.php`

### Drupal.org
- Content Moderation overview: https://www.drupal.org/docs/8/core/modules/content-moderation/overview
- Workflow Participants: https://www.drupal.org/project/workflow_participants
- Workbench Approver: https://www.drupal.org/project/workbench_approver
- Workflows overview: https://www.drupal.org/docs/8/core/modules/workflows/overview

As páginas de módulos contrib são referências de escopo e trade-offs, não requisitos de dependência. Nenhum módulo contrib listado deve ser adicionado ao composer como parte deste plano.

Busca final de referências ao modelo anterior e motivos transitórios está
registrada em [`INVENTARIO-ACULTA-CONTRIBUTIONS.md`](INVENTARIO-ACULTA-CONTRIBUTIONS.md).
