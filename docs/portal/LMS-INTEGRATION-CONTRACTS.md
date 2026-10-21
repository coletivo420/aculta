# Contratos de integração do LMS

Revisado em 2026-10-20. Contratos de integração; a implementação depende das fases e critérios de aceite correspondentes.

## Objetivos de integração de conteúdo (F15)

- Integrar `aculta_lms` e `aculta_lms_h5p` ao pipeline compartilhado `aculta_image` para capas, imagens de atividades, imagens em entregas e anexos de discussão. Toda ingestão deve passar pela finalidade e política de upload apropriadas; arquivos H5P internos permanecem gerenciados e privados, sem duplicar catálogo, remoção ou storage.
- Integrar materiais PDF do curso com a capacidade existente `aculta_pdf_viewer` (incluindo `aculta_pdf_viewer_group`), que atende ao objetivo de leitor PDF solicitado como `aculta_pdf_reader`. Manter Media `course_document`, relação Group e entrega privada do Core como fontes de acesso; não criar reader/storage paralelo.
- Integrar `aculta_lms` e `aculta_lms_h5p` ao `aculta_forum` para discussão contextual de curso, turma e atividade. O adapter `aculta_forum_lms` usa Node/Comment e revalida Entity Access e membership atual no Group. O Group Node de tópicos tem permissão específica; a herança `grade students` do curso para a turma está configurada e coberta por Kernel. No Runtime de teste o adapter está habilitado, com Config Sync limitada às permissões; ainda não há conteúdo LMS para smoke de acesso HTTP. Não habilitar em produção nem expor listagens até passar a matriz integrada de isolamento entre turmas.
- Entregas LMS, documentos PDF e imagens de estudante devem respeitar a mesma política de acesso privado. Integrações precisam cobrir upload, renderização, download, revogação de matrícula, cacheabilidade e ciclo de exclusão nos testes antes de homologar no Runtime.

O motor `aculta_forum` e o adapter LMS já existem. O adapter tem bundle Node, campo Comment compartilhado, relações Group de curso/turma, bloqueio direto de Node sem relação Group e permissões Group Node. Config Sync atribui a permissão somente a course teacher/member/creator e class member, e herda `grade students` do curso para a turma. Kernel usa esses Group roles reais e prova membership, Comment, moderação, metadados de cache por usuário/permissão e instalação do conjunto padrão; Unit valida os IDs e a extensão ativa. No Runtime de teste, `aculta_forum_lms`/`gnode` estão habilitados e uma importação parcial aplicou somente os quatro Group roles e `lms_classes.settings`. A prévia global de Config Sync contém alterações não relacionadas e não deve ser importada integralmente. Não há Nodes/Comments de discussão no Runtime, então autorização por conteúdo HTTP e moderação visual ainda não foram exercitadas. Ver README do adapter, `aculta_forum/docs/CONTEXTS.md` e roadmap.

O Core exige `administer comments` para ver Comments não publicados e executar
`approve`. O adapter concede apenas `view`/`approve` a Comments
`field_aculta_discussion` cujo host resolve para o contexto LMS e cujo Educador
tem `grade students` no curso atual. `update`, `delete` e a permissão global
continuam fora desse grant. As três rotas de status incorporam uma View sem
display público/Page/Block, com argumento Group obrigatório, bundle LMS, campo
Comment e estado fixos. A rota revalida acesso por Group e a View junta os
relacionamentos Node e Group para preservar os filtros de acesso. Kernel prova
separação de curso/turma e dos três status. No Runtime de teste, a View foi
atualizada isoladamente com importação `--partial` de um único item, cache
rebuild passou, o índice retornou 403 sem autenticação e Groups inexistentes
retornaram 404. A coluna de operações também foi aplicada; não houve smoke
autenticado nem comentários LMS de teste. O índice LMS não
entra na busca pública. As filas exibem `Aprovar`/`Rejeitar` por operação de
entidade; Form API confirma e revalida no submit, publica e limpa marcador ao
aprovar ou despublica e marca ao rejeitar. Kernel cobre as transições e nega
estudante, contexto divergente e estados repetidos. Não há comentários LMS de
teste nem smoke autenticado das operações.
O Kernel também executa `CustomAccessCheck` do Core com a assinatura configurada
na rota, comprovando a conversão Group/Comment, o parâmetro `decision` e a conta
na chamada do manager. Na dev.111, `ParamConverterManager` do Core converte os
IDs Group/Comment pela definição `entity:group`/`entity:comment` antes do check;
o teste nega aluno e Group diferente do proprietário do tópico.

## Matriz de ownership e dependências

| Necessidade | API/dono | Evento/dado que pode cruzar | Proibições |
| --- | --- | --- | --- |
| Course/Group/membership/progresso | Drupal LMS + Group | IDs de entidade e resultado permitido após access; URLs via `DomainPurposeManager` | tabela paralela, cache público de status privado, inferir membership de Flag |
| Arquivos e imagens de curso | Media/File + `aculta_image` + LMS | Capa de curso sob `UploadPolicy`; imagens H5P passam por `upload_ingester`; documento PDF de turma é Media `course_document` privado com relação Group e viewer `aculta_pdf_viewer` | URL pública para arquivo privado, caminho de imagem fora de `aculta_image`, upload de imagem paralelo no H5P |
| PDF de curso | `aculta_pdf_viewer` + `aculta_pdf_viewer_group` + Group | Educador vincula Media `course_document` à turma; viewer usa Media/File e acesso Group atual, com bytes entregues pela rota privada do Core | Usar o bundle editorial `document` público para material de turma; duplicar viewer, storage de documento ou entrega privada |
| Arquivos de trabalho | Media/File + File Access + LMS File Upload + `aculta_image` | Submissão via LMS; anexos raster passam pela `UploadPolicy` e `ImageInspector`, continuam privados e o download revalida matrícula. Validação Kernel e fluxo de upload/revogação ainda pendentes | URL pública de arquivo privado, armazenamento paralelo ou imagem fora da política compartilhada |
| Avisos educacionais | `aculta_messenger` + Message/Message Notify, integrados pelo adapter `aculta_lms_messenger` | evento mínimo, UUID imutável da origem, destinatário e referência de rota revalidável | LMS Messages, fila/storage redundante, envio baseado só em membership antiga |
| Identidade, perfil e conexões | User/Profile + `aculta_user` | referência de User e API `FriendshipManagerInterface` quando aplicável | entidade de amizade ou perfil no LMS |
| Conquistas | `aculta_user`/`aculta_game` | fato de conclusão/atributo LMS confirmado pela API autorizada | recalcular nota/progresso, nova entidade espelho de award |
| Discussão de curso/turma/atividade | `aculta_forum` + `aculta_forum_lms` + Node/Comment + Group access | Adapter com tópico interno e provider contextual; acesso revalidado no Group e no host; curso herda permissão Group Node `grade students` para turma; habilitado somente no Runtime de teste | Entidade paralela; role global `estudante`/`lms_teacher` sem membership ou permissão contextual; expor listagem antes de aplicar filtros de Group/Entity Access |
| Mídia editorial | `aculta_gallery`/Media Gallery | Media Gallery e referências File/Media com seus handlers | fazer Gallery dona do PDF ou duplicar arquivo |
| Apresentação | Portal neutral render contract → ACULTA420 SDC | render arrays, props escalares, cacheability | tema consultar LMS/Group/Domain ou chamar serviços |

## Contrato LMS → Messenger

F13 prioriza eventos acionáveis e persistidos: conteúdo liberado, prazo de atividade e retorno do educador, além de matrícula/certificado, conclusão, avaliação e resposta pendente já contratados. Edições editoriais comuns não geram avisos. A publicação de curso, aula ou atividade, o retorno publicado e a definição/alteração do prazo têm produtores. Mensagens de retorno não contêm o texto da resposta nem do comentário. O prazo é guardado na relação Group entre a turma e a atividade (campo `field_activity_due_at`); pode variar entre turmas e não altera a entidade LMS. Educadores com `grade students` no curso ou administradores definem a data por atividade na tela administrativa da turma; o horário informado usa o fuso da conta. O estudante vê o prazo no próprio fuso. Avisos exigem membership ativa na turma e conferem `take course` no curso pai, conforme o cálculo de permissões do `lms_classes`; não consultam essa permissão na turma. O aviso é criado ao definir ou alterar a data, sem e-mail transacional, e revalida curso, atividade, membership e permissão ao exibir. O lembrete é gerado quando o prazo entra na janela das 24 horas seguintes: Drupal Cron enfileira o processamento e o worker revalida a data e o acesso antes de persistir o aviso. No Runtime de teste o Cron está configurado para 3h; o smoke invocou hook e worker diretamente, sem aguardar ciclo agendado. O lembrete não envia e-mail transacional.

O dono do evento educacional em `aculta_lms` publica evento após save efetivo. Matrículas, certificados, conclusões, avaliações, respostas pendentes, publicações e feedback são persistidos como Message e integrados aos avisos pessoais do drawer/contador multidomínio do `aculta_messenger`. O provider reabre a origem e revalida usuário ativo, membership, acesso e purpose antes de exibir. Matrícula e certificado enviam e-mail transacional mínimo; os demais eventos aparecem sempre no site e fornecem linhas ao resumo diário opt-in do Messenger. Educadores com permissão atual `grade students` no curso recebem o aviso de resposta pendente. Cada nova gravação de avaliação publicada (inclusive reavaliação com nota igual) tem deduplicação própria. Não incluir respostas, notas, arquivos nem corpo de comentário; usar link seguro para ACCOUNT/COURSES.

LMS Messages foi examinado apenas como referência de eventos: o projeto não é coberto pela política de security advisories do Drupal e registra limitação de automação com Group Membership. Não instalar, depender ou copiar seu motor; definir os eventos necessários pelos contratos existentes de Messenger e Message Notify.

Deduplicação idempotente usa a entidade Message já adotada pelo Messenger e referência de origem/destinatário; eventos repetíveis também guardam versão opaca da publicação. Não criar tabela de fila/notification. A revogação de acesso antes do dispatch suprime a entrega. Preferências usam contratos atuais do Messenger; F13 define canais e opt-in para eventos LMS sem criar uma central paralela.

## Contrato LMS → User/Conquistas

O LMS é a autoridade para conclusão, resultado e nota. `aculta_user`/`aculta_game` só recebem fato com identificador de curso, código de evento/fato permitido e horário, depois de consultar o estado definitivo LMS. O serviço público deve definir se o evento pode ser reprocessado e como evita dupla concessão. Definição e concessão de conquista continuam em `aculta_user`; apresentação e regras de missão em `aculta_game`. Perfil público não revela matrícula, nota, resposta, atividade, turma ou identidade de educador. A pessoa controla visibilidade conforme política existente de conquistas. O educador não concede conquista manualmente através de payload sem fonte validada.

## Modelo de avaliações

- Nota oficial é o score calculado e persistido pelo Drupal LMS para resposta/tentativa própria, associada à atividade e curso; o plugin `ActivityAnswer` do LMS define coleta e avaliação.
- Tentativas, respostas, timestamps, feedback e `needs evaluation` são lidos/escritos pelas entidades/serviços LMS. Não manter gradebook ou nota no Portal, Webform, H5P, Messenger ou certificados.
- Atividades objetivas: usar plugins LMS nativos ou extensão `aculta_lms` revisada para resposta/score, regras de pontuação, max score, limite de tentativa, revisão de resposta e feedback. Resultado determinístico e verificável.
- Reflexão/trabalho subjetivo: `no_answer`/plugin discursivo ou File Upload com revisão docente explicitamente pendente; só professor/educador autorizado avalia via LMS. Resultado externo não se torna nota oficial por ser recebido com HTTP 200.
- Webform Score pode coletar formulários apenas após comparação de persistência, identidade e feedback; uma pontuação de Webform só poderá alimentar LMS por integração explícita, autenticada e transacional. Evitar dupla gravação parcial; se não houver API estável, não integrar pontuação.
- H5P poderá começar como conteúdo interativo sem nota oficial. Só vincular resultados após demonstrar API estável, identidade de tentativa, origem e assinatura/confiabilidade, repetição, autoria e escrita pelo modelo de resposta LMS. Resultado postMessage/client-side não é prova de nota.
- F8/F9 devem definir transparência da rubrica, arredondamento, feedback privado, tentativas, prazo, reavaliação e histórico antes de ativar avaliações.

## Contrato de certificados

F11 é posterior à conclusão homologada. Entity Print/Dompdf gera o PDF; o motor não define elegibilidade. A elegibilidade consulta o status final aprovado diretamente no LMS e exige o campo inteiro de carga horária preenchido pela equipe. Decisões do responsável: cada emissão guarda um retrato imutável; a verificação pública usa código opaco e QR via `aculta_qrcode`; correções criam nova emissão e preservam a anterior como revogada; educadores com `grade students` no curso e administradores podem emitir/reemitir; exclusão de conta não invalida certificados nem apaga o snapshot; a página pública mostra nome, curso, carga horária e estado. A base legal e retenção de longo prazo continuam marcadas para validação no inventário LGPD.

O registro mantém UID numérico sem referência de entidade ao usuário, estado de emissão, motivo de revogação e encadeamento; `entity_presave` impede alteração do retrato e reativação. Rotas genéricas da entidade são negadas; somente o código opaco abre a verificação e o PDF. A rota PDF é privada/no-store, faz escaping do snapshot e desliga recursos remotos e PHP embutido do Dompdf. Educadores podem revogar com motivo, e a reemissão revoga a anterior dentro da mesma transação que grava a nova. O resultado não é segundo status de conclusão nem entrada de gradebook. A integração QR, revisão visual e operação no Runtime continuam pendentes.

## Contrato de apresentação ACULTA420

`aculta_lms` fornece ao Portal dados, access results e cacheability; Portal prepara navegação, purpose e URLs; ACULTA420 mapeia o contrato neutro para SDCs Bootstrap/Drupal. Itens apresentados podem incluir curso, estado, progresso permitido, ação e render arrays da entidade; nunca expor `GroupInterface`, serviços ou host a Twig. Deve haver navegação completa e fallback sem JavaScript. O catálogo usa Views existente (`courses_catalog`) e o SDC `aculta420:course_card` substitui `lms:course_card` mantendo o schema compatível; Views/LMS seguem donos de acesso, dados e cacheability. Room/dashboard podem compor Views + serviços LMS com acesso nativo.

SDCs planejados após F5: apresentação de curso; sala/índice de lições; lista/estado de atividades; painel de progresso do estudante; painel de correção do educador; status de entrega/feedback; badge/aviso de acesso. São contratos/componentes novos, não substitutos de formulários/rotas contrib. Reusar tokens e SDCs existentes. Não copiar CSS/templates Opigno.

F11 usa `aculta_qrcode.generator` e `payload_validator` com URL absoluta
resolvida pelo `DomainPurposeManager` no purpose `main`. O SVG público e o PNG
do PDF são gerados em memória; a rota de QR existe somente para códigos de
certificados registrados.

Decisão da F12 (2026-10-20): o painel do estudante reúne cursos, progresso,
avaliações pendentes e certificados próprios. O painel do educador mostra
indicadores agregados por turma, sem respostas abertas nem dados identificáveis
de estudantes na visão agregada. Group/LMS e a entidade de certificado seguem
como fontes de verdade; a camada visual não cria matrícula, progresso, nota ou
certificado paralelo. Cache deve variar por usuário no painel estudantil e por
turma/permissão no educador; não compartilhar agregados entre turmas.

## Segurança, cache e acesso

Cada endpoint, View, download, render array, evento e worker precisa verificar autenticação, Entity Access, access de Group e membership/papel atual; turmas diferentes não compartilham conteúdo privado. Render contexts/tags/max-age vêm das entidades, resultados de access e contexto necessário (`user`, permissions, group ou cache privado). Não reutilizar cache público entre alunos/educadores. URLs cross-purpose resolvidas pelo `DomainPurposeManager`; nenhuma configuração exportada guarda hostname.

Matriz detalhada de arquivos e operações: [PDF-VIEWER-INTEGRATION.md](PDF-VIEWER-INTEGRATION.md) para viewer e [SECURITY-ACCESS-FILES-MATRIX.md](SECURITY-ACCESS-FILES-MATRIX.md) para LMS.


## Moderação de discussões pedagógicas

A moderação dos comentários do LMS pertence aos Educadores autorizados no curso, turma ou atividade em questão. A
autorização deve ser revalidada pelo adapter usando o acesso pedagógico atual (Group/membership e Entity Access),
variando e invalidando cache por usuário/permissões e entidades relevantes. Papel global de educador sem membership ou
acesso contextual não basta. `aculta_user` não aplica a regra global do primeiro comentário dentro do LMS, e a fila de
moderação pública não inclui comentários pedagógicos. O adapter fornece a
decisão contextual para o marcador compartilhado e está habilitado no Runtime
de teste. As provas Kernel cobrem permissão e Comment; filas administrativas e
autorização de conteúdo real no Runtime continuam pendentes. Estudantes podem
participar sem aprovação global nesse contexto.

## Organização administrativa das discussões

As discussões do LMS não aparecem em `/painel-administrativo/conteudo/forum` nem
na fila pública de comentários. A Revista concentra publicações e comentários
em `/painel-administrativo/conteudo/revista`. O adapter ainda não fornece filas
ou listagens próprias; quando forem implementadas, elas deverão ficar em
`/painel-administrativo/lms/forum`,
separando curso, turma e atividade conforme o modelo de acesso, com filtros
fixos e autorização revalidada por Entity Access e Group membership. A fila de
aprovação é operada por Educadores do contexto correspondente. Não publicar uma
View global de comentários LMS antes de provar isolamento entre turmas. O tipo
existente `lms_answer_comment` continua reservado à conversa de avaliação da
resposta e não é convertido em discussão de curso/turma.
