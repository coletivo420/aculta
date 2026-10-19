# Contratos de integração do LMS

Revisado em 2026-10-19. Contratos de integração; a implementação depende das fases e critérios de aceite correspondentes.

## Objetivos de integração de conteúdo (F15)

- Integrar `aculta_lms` e `aculta_lms_h5p` ao pipeline compartilhado `aculta_image` para capas, imagens de atividades, imagens em entregas e anexos de discussão. Toda ingestão deve passar pela finalidade e política de upload apropriadas; arquivos H5P internos permanecem gerenciados e privados, sem duplicar catálogo, remoção ou storage.
- Integrar materiais PDF do curso com a capacidade existente `aculta_pdf_viewer` (incluindo `aculta_pdf_viewer_group`), que atende ao objetivo de leitor PDF solicitado como `aculta_pdf_reader`. Manter Media `course_document`, relação Group e entrega privada do Core como fontes de acesso; não criar reader/storage paralelo.
- Integrar `aculta_lms` e `aculta_lms_h5p` ao `aculta_forum` para discussão contextual de curso, turma e atividade. O alvo usa Node/Comment com adapter opcional; cada leitura/escrita revalida Entity Access e membership atual no Group. Não ativar `gnode` ou relações globais sem teste de isolamento entre turmas.
- Entregas LMS, documentos PDF e imagens de estudante devem respeitar a mesma política de acesso privado. Integrações precisam cobrir upload, renderização, download, revogação de matrícula, cacheabilidade e ciclo de exclusão nos testes antes de homologar no Runtime.

`aculta_forum` ainda não fornece API executável. A arquitetura aprovada em 2026-10-19 define um adapter opcional para os contextos LMS e substitui a referência anterior a Forum contrib como motor final; ver `aculta_forum/docs/CONTEXTS.md` e seu roadmap versionado. Confirmar API, Group e nomes de máquina no código instalado antes de implementar; não declarar integração completa apenas por registrar dependências.

## Matriz de ownership e dependências

| Necessidade | API/dono | Evento/dado que pode cruzar | Proibições |
| --- | --- | --- | --- |
| Course/Group/membership/progresso | Drupal LMS + Group | IDs de entidade e resultado permitido após access; URLs via `DomainPurposeManager` | tabela paralela, cache público de status privado, inferir membership de Flag |
| Arquivos e imagens de curso | Media/File + `aculta_image` + LMS | Capa de curso sob `UploadPolicy`; imagens H5P passam por `upload_ingester`; documento PDF de turma é Media `course_document` privado com relação Group e viewer `aculta_pdf_viewer` | URL pública para arquivo privado, caminho de imagem fora de `aculta_image`, upload de imagem paralelo no H5P |
| PDF de curso | `aculta_pdf_viewer` + `aculta_pdf_viewer_group` + Group | Educador vincula Media `course_document` à turma; viewer usa Media/File e acesso Group atual, com bytes entregues pela rota privada do Core | Usar o bundle editorial `document` público para material de turma; duplicar viewer, storage de documento ou entrega privada |
| Arquivos de trabalho | Media/File + File Access + LMS File Upload | Submissão via LMS; acesso revalidado por matrícula e política do File Upload. Integrar imagens submetidas ao `aculta_image` antes de aceitar imagem como tipo de entrega | URL pública de arquivo privado, armazenamento paralelo ou imagem fora da política compartilhada |
| Avisos educacionais | `aculta_messenger` + Message/Message Notify | evento mínimo, usuário destinatário, entidade e referência de rota validável | LMS Messages, fila/storage redundante, envio baseado só em membership antiga |
| Identidade, perfil e conexões | User/Profile + `aculta_user` | referência de User e API `FriendshipManagerInterface` quando aplicável | entidade de amizade ou perfil no LMS |
| Conquistas | `aculta_user`/`aculta_game` | fato de conclusão/atributo LMS confirmado pela API autorizada | recalcular nota/progresso, nova entidade espelho de award |
| Discussão de curso/turma/atividade | `aculta_forum` + Node/Comment + Group access | Adapter opcional com tópico interno e provider contextual; acesso revalidado no Group e no host | Dependência executável enquanto Forum é apenas documentação; entidade paralela; role global `estudante`; ativar `gnode` sem decisão/teste de isolamento |
| Mídia editorial | `aculta_gallery`/Media Gallery | Media Gallery e referências File/Media com seus handlers | fazer Gallery dona do PDF ou duplicar arquivo |
| Apresentação | Portal neutral render contract → ACULTA420 SDC | render arrays, props escalares, cacheability | tema consultar LMS/Group/Domain ou chamar serviços |

## Contrato LMS → Messenger

Eventos candidatos (F13): matrícula aprovada/nova membership, boas-vindas; curso/módulo/lição liberados; novo trabalho/tarefa e prazo próximo; entrega recebida; retorno do educador; nota publicada; conclusão confirmada; certificado disponível; comunicado docente à turma; menção/discussão de curso. Cada evento precisa definir produtor, momento após persistência, dedupe key, público permitido, preferência/canal, validade, template mínimo, URL canônica e política de retirada/revalidação.

O dono do evento educacional em `aculta_lms` publica uma interface/evento versionado após save efetivo, contendo somente tipo, identificador imutável de origem, destinatário(s) permitidos e referência neutra. `aculta_messenger` integra Message e Message Notify segundo contratos existentes. Worker reabre a entidade de origem e revalida usuário ativo, membership/papel, publicação, access e purpose no envio; não confiar num snapshot de destinatários enfileirado. Não incluir respostas, notas, arquivo ou corpo de mensagem no e-mail sem decisão explícita; padrão é aviso mínimo e link seguro para ACCOUNT/COURSES.

LMS Messages foi examinado apenas como referência de eventos: o projeto não é coberto pela política de security advisories do Drupal e registra limitação de automação com Group Membership. Não instalar, depender ou copiar seu motor; definir os eventos necessários pelos contratos existentes de Messenger e Message Notify.

Deduplicação idempotente deve usar a entidade Message já adotada pelo Messenger e referência de origem/destinatário; não criar tabela de fila/notification. A revogação de acesso antes do dispatch suprime a entrega. Preferências usam contratos atuais do Messenger; F13 define canais e opt-in para eventos LMS sem criar uma central paralela.

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

F11 é posterior à conclusão homologada. Entity Print é avaliado como renderização de documento/PDF; ele não define elegibilidade. A elegibilidade consulta o status final aprovado e os critérios do curso diretamente no LMS. Um certificado contém identificador verificável não sequencial, titular segundo política de privacidade, curso, data de conclusão/emissão, versão do template e estado revogável.

Antes da implementação, decidir: certificado como artefato imutável arquivado ou documento derivado e regenerável; qual estado e auditoria registram reemissão/revogação; quem tem permissão de emitir; como tratar nome atualizado, remoção de conta e correção retroativa de nota; validade e exposição pública da verificação. Não emitir só pelo conteúdo `isFinished` sem confirmar resultado aprovado. Não expor identidade pessoal em endpoint verificável por IDOR. O resultado emitido não é um segundo status de conclusão nem entrada de gradebook. Rollback remove código/formatter apenas após preservar artefatos e auditoria autorizados.

## Contrato de apresentação ACULTA420

`aculta_lms` fornece ao Portal dados, access results e cacheability; Portal prepara navegação, purpose e URLs; ACULTA420 mapeia o contrato neutro para SDCs Bootstrap/Drupal. Itens apresentados podem incluir curso, estado, progresso permitido, ação e render arrays da entidade; nunca expor `GroupInterface`, serviços ou host a Twig. Deve haver navegação completa e fallback sem JavaScript. O catálogo usa Views existente (`courses_catalog`) e o SDC `aculta420:course_card` substitui `lms:course_card` mantendo o schema compatível; Views/LMS seguem donos de acesso, dados e cacheability. Room/dashboard podem compor Views + serviços LMS com acesso nativo.

SDCs planejados após F5: apresentação de curso; sala/índice de lições; lista/estado de atividades; painel de progresso do estudante; painel de correção do educador; status de entrega/feedback; badge/aviso de acesso. São contratos/componentes novos, não substitutos de formulários/rotas contrib. Reusar tokens e SDCs existentes. Não copiar CSS/templates Opigno.

## Segurança, cache e acesso

Cada endpoint, View, download, render array, evento e worker precisa verificar autenticação, Entity Access, access de Group e membership/papel atual; turmas diferentes não compartilham conteúdo privado. Render contexts/tags/max-age vêm das entidades, resultados de access e contexto necessário (`user`, permissions, group ou cache privado). Não reutilizar cache público entre alunos/educadores. URLs cross-purpose resolvidas pelo `DomainPurposeManager`; nenhuma configuração exportada guarda hostname.

Matriz detalhada de arquivos e operações: [PDF-VIEWER-INTEGRATION.md](PDF-VIEWER-INTEGRATION.md) para viewer e [SECURITY-ACCESS-FILES-MATRIX.md](SECURITY-ACCESS-FILES-MATRIX.md) para LMS.


## Moderação de discussões pedagógicas

A moderação dos comentários do LMS pertence aos Educadores autorizados no curso, turma ou atividade em questão. A
autorização deve ser revalidada pelo adapter usando o acesso pedagógico atual (Group/membership e Entity Access),
variando e invalidando cache por usuário/permissões e entidades relevantes. Papel global de educador sem membership ou
acesso contextual não basta. `aculta_user` não aplica a regra global do primeiro comentário dentro do LMS, e a fila de
moderação pública não inclui comentários pedagógicos. Até o adapter fornecer essas ações, moderação LMS permanece
fechada por padrão; estudantes continuam podendo participar sem aprovação global.
