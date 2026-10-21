# Roadmap do aculta_lms e visualizador PDF global

**Ciclo vigente:** seguir [Dev → RC → deploy/validação Alpha → produção](../operations/RELEASE-LIFECYCLE.md). Em RC, código, configuração, testes isolados, gates e revisão visual/acessível aplicável estão prontos para deploy. Alpha valida o Runtime no servidor de desenvolvimento; depois de aprovado, preparar a migração do mesmo candidato para produção. RC não exige validação Runtime que só pode ocorrer após deploy.

Revisado em 2026-10-19. A implementação segue fases independentes, com autorização e critérios próprios. O status é registrado por fase; a homologação de PDF privado não ativa automaticamente o módulo `aculta_lms`.

## Princípios e estado inicial

`aculta_lms` será opcional, integrador e educacional. Group mantém cursos/memberships; Drupal LMS mantém lições/atividades/respostas/status/notas; Media/File mantém arquivos; `aculta_messenger` controla comunicações; `aculta_user`/`aculta_game` controlam identidade/conquistas. O serviço compartilhado de PDF pertence a `aculta_pdf_viewer`, não ao LMS. O inventário está em [LMS-GROUP.md](LMS-GROUP.md), contratos em [LMS-INTEGRATION-CONTRACTS.md](../portal/LMS-INTEGRATION-CONTRACTS.md), segurança em [SECURITY-ACCESS-FILES-MATRIX.md](../portal/SECURITY-ACCESS-FILES-MATRIX.md) e PDF em [PDF-VIEWER-INTEGRATION.md](../portal/PDF-VIEWER-INTEGRATION.md).

Bloqueios técnicos: PDFPreview 1.2 e ImageMagick 5.0 não resolvem juntos via Composer; PDF 1.3 usa referências `.js`, incompatíveis com a distribuição PDF.js `.mjs` moderna; miniaturas privadas continuam desativadas; H5P segue beta e precisa de auditoria de segurança; PHP 8.5 e a produção Hostinger precisam de validação nos ambientes correspondentes.

## Fases e gates

| Fase | Escopo e dependências | Critério de aceite | Bloqueio/rollback |
| --- | --- | --- | --- |
| F0 — Documentação, inventário e auditoria | Fontes, versões, Composer, acesso, contratos, roadmap e revisão upstream | Documentos normativos atualizados; evidência PASS/FAIL/DEFERRED; nenhum estado Drupal alterado | `git diff --check`; commits documentais separados; rollback pelo revert dos commits |
| F1 — Fundação `aculta_lms` | Módulo opcional, DI, contratos públicos e testes de instalação; sem mover serviços LMS ativos no Portal | Instalação em site de teste, sem mudança automática de dados/config, sem storage paralelo; gates Drupal 11.3+ | Não ativar no Runtime antes de target/URI/config confirmados; desinstalação preserva dados LMS |
| F2 — PDF.js e formatter global | `drupal/pdf` 1.3 e PDF.js 6.4.299 pinados localmente; viewer ESM implementado em `aculta_pdf_viewer`, sem usar o formatter legado | Unit, browser smoke isolado e leitura pública/privada no Runtime PASS (PDF oficial de 14 páginas; acesso privado sob Group descrito em PV5). | Sem patch direto em contrib. CSP, proxy, matriz ampliada de browsers e Hostinger ficam no PV7; rollback remove o módulo/asset após retirar o formatter do display |
| F3 — PDFPreview e integração Media | `aculta_pdf_viewer` independente; formatter no display Media `document`; preview opcional e política de acesso a derivados | Formatter e filtro Core Media Embed testados em Kernel; CKEditor e criação de documento por educador, PDF público e miniatura gerada por `aculta_image` foram verificados no Runtime. A miniatura permanece restrita a PDF público; documentos de turma usam PV5. | PDFs grandes e falhas de conversão seguem para PV7. PDFPreview 1.2 requer ImageMagick 3.7/4.x; versões e policy do host exigem validação específica. Não aplicar policy PDF/PS nem migrar arquivos. Detalhes no roadmap próprio `aculta_pdf_viewer`. |
| F4 — Consolidar Drupal LMS | Atualizar LMS/Group controladamente, rotas, papéis e provisionamento piloto | Composer audit/dry-run, config/schema update, conteúdo piloto reconciliado, regressões e permissões aprovadas | Não consolidar scripts antes de escolher fonte canônica. Backup e retorno de código/config; nunca deletar conteúdo como rollback |
| F5 — Catálogo | SDC ACULTA420 sobre `courses_catalog` e Views existentes | Anônimo/aluno vê somente cursos elegíveis; access/cache e teclado validado; sem JS essencial | Reverte presenter/SDC/config da view; manter rota catálogo anterior |
| F6 — Página de curso e sala | Overview, índice de lições, leitura/atividade, viewer compartilhado | Form/rota nativa LMS continua; membership e acesso de atividade testados | Sem alterar entidades LMS; desligar componente e voltar render padrão |
| F7 — Turmas e memberships | LMS Classes conforme existência/metadata real, educadores/alunos, isolamento | Usuário A/B, educador A/B, acesso admin e autocadastro/política aprovados | Não habilitar autoenrollment até política; rollback de grants/config em janela controlada |
| F8 — Avaliação objetiva | Answer plugins LMS, tentativas, scores e feedback | Cálculo repetível no servidor, regra documentada, audit trail e sem score externo confiado | Remover plugin somente após confirmar atividades referenciadas; preservar respostas existentes |
| F9 — Webform Score e entregas | Webform Score, LMS File Upload, storage privado | Identidade/autoria e resultados integrados sem gradebook duplicado; revogação de membership corta arquivos | Dependente de APIs Composer e privacidade; desabilitar formulário/integração mantendo submissions e arquivos |
| F10 — H5P | Integração própria `aculta_lms_h5p`, editor, bibliotecas privadas, reprodução integrada ao curso e imagens via `aculta_image`; materiais PDF complementares usam `aculta_pdf_viewer` + Group | Bibliotecas, permissões, uploads, imagens, CSP/isolamento, salvamento e acesso de estudante homologados; sem nota oficial até API confiável | Despublicar atividades sem apagar conteúdo válido; preservar bibliotecas enquanto houver referências |
| F11 — Certificados | Entity Print/Dompdf após conclusão LMS aprovada; QR via `aculta_qrcode` | emissão/reemissão transacional por educador/admin, snapshot imutável, verificação pública por código/QR, revogação com motivo, retenção após exclusão de conta, privacidade e template testados | Decisões registradas em `LMS-INTEGRATION-CONTRACTS.md`; reverter código sem apagar auditoria/artefatos válidos |
| F12 — Dashboards | SDC ACULTA420 sobre LMS services/Views | dados próprios para estudante; aggregate limitado à turma para educador; sem cache cruzado | Reverter presenters/Views e manter rotas LMS |
| F13 — Notificações educacionais | Adapter `aculta_lms_messenger` → avisos globais `aculta_messenger` / Message Notify | avisos sempre no site; matrícula/certificado com e-mail transacional; demais eventos em resumo diário opt-in; dedupe, access atual, fila, privacidade e UI testados | Desabilitar subscriber/producer; Message permanece fonte e não apagar comunicações existentes |
| F14 — Conexões | `FriendshipManagerInterface` e Messenger existentes | ações e notificações usam amizade atual sem duplicar estado/permissão | Reverter adapter; amizade continua em `aculta_user` |
| F15 — Integrações de conteúdo | `aculta_lms` e `aculta_lms_h5p` integrados a `aculta_image`, ao leitor PDF existente `aculta_pdf_viewer` (solicitado como `aculta_pdf_reader`) e `aculta_forum`; Gallery, Magazine e Wiki | Imagens de curso, atividade, entrega e discussão passam pela política `aculta_image`; PDFs usam Media `course_document`, Group e viewer compartilhado; tópicos e respostas preservam Forum/Node/Comment como fonte e revalidam membership, access, cache e purpose | `aculta_forum` ainda não tem API executável para vínculo LMS; implementar e testar isolamento por turma antes de dependência/ativação. Não duplicar storage, reader PDF ou entidade de discussão |
| F16 — Hardening | performance, segurança, privacidade, acessibilidade, cache e tamanho/timeout PDF | zero Critical/High; matriz completa e gates PASS | rollback por componente/flag/config, sem downgrade destrutivo de entidades |
| RC — Candidato integrado pronto para deploy | Testes automatizados, config sync estática, navegador pré-deploy, acessibilidade, segurança, documentação, versão e rollback | Todo requisito funcional e visual aplicável passa antes de RC; evidências e limites registrados. Alpha valida o Runtime após deploy | Não implantar automaticamente; se Alpha encontrar divergência, voltar a Dev, corrigir e gerar novo RC |
| F17 — Google Classroom (opcional e última) | Após RC aprovado e nova decisão; publicar somente link para atividade ACULTA | OAuth/scopes, consentimento e link para fonte LMS aprovados | Nenhum pacote/token/rota/storage nesta linha; desabilitar integração sem afetar LMS |

## Estado de fase

**F13 — notificações educacionais: implementação funcional; revisão visual pendente.** Matrícula e certificado passam ao adapter
`aculta_lms_messenger`, que grava Message, integra lista/contador do `aculta_messenger` e enfileira e-mail
transacional mínimo via Message Notify. Conclusão, avaliação publicada, respostas pendentes, conteúdo publicado,
feedback do educador e definição/alteração de prazo aparecem no site e no resumo diário opt-in; educadores recebem
respostas pendentes somente com `grade students` atual. Avisos não incluem resposta, nota, arquivo ou corpo de
comentário, e revalidam a fonte ao exibir. O prazo é salvo por atividade na turma em relação Group, usa o fuso da
conta e não modifica o conteúdo LMS compartilhado entre turmas. O educador registra a data por atividade em cada turma; o campo fica na relação Group da turma com a atividade. O lembrete é enfileirado quando a data entra na janela de 24 horas. Unit LMS/adapter: 47 testes / 210 assertions PASS; Kernels de Group/Message: 6 testes / 85 assertions PASS, incluindo persistência real da mesma atividade em duas turmas, permissão `take course` no curso pai e persistência/deduplicação do aviso em Message.
Esses Kernels registraram depreciações upstream (3 em instalação/certificado e 42 nos Kernels recentes de Group/Message).
No Runtime de teste o adapter está habilitado, as cinco configurações Group de prazo foram importadas isoladamente e manager/worker instanciam. Smoke temporário com turma, membership e prazo confirmou aviso em Message, integração do aviso pessoal, enqueue pelo hook de Cron e persistência do lembrete pelo worker; limpeza removeu os dados e filas temporários. Não foi enviado e-mail. Cron roda a cada 3h, mas o hook e o worker foram invocados diretamente, sem aguardar execução agendada. A tentativa de Firefox headless chegou ao acordo geral `/agreement`, que exige aceite na sessão; nenhum aceite foi submetido. A gaveta autenticada ainda não foi revisada. Próximo: executar revisão visual/acessível em sessão de teste autenticada após o responsável concluir o acordo; depois gates e revisão pré-RC.

**F1 — Fundação `aculta_lms`: entregue na PR #286, versão `0.1.0-dev.1`.** O submódulo opcional e o contrato
DI de conclusão aprovada estão implementados. Testes Unit (2/2) e Kernel isolado (1/1) passam; `aculta_lms`
continua desativado no Runtime e ainda não tem produtor ou consumidor educacional ativo (**RUNTIME STATUS:
DEFERRED**).

O viewer compartilhado e a relação Media↔Group para documentos privados de turma foram desenvolvidos nos
submódulos `aculta_pdf_viewer` e `aculta_pdf_viewer_group`. O Kernel cobre membro, não membro, remoção da
relação e revogação de membership. **PV5 foi homologada no Runtime de teste em 2026-10-17**: membro leu o
PDF; anônimo e não membro receberam 403; a mesma URL conhecida voltou a receber 403 após remover a relação
ou a membership. A rota Core `/system/files?file=...` foi usada porque `/system/files/{filepath}` retornava
404 nesse ambiente. Evidências e limite de escopo estão no
[changelog do viewer](../../web/modules/custom/aculta_portal/modules/aculta_pdf_viewer/CHANGELOG.md).

O PDF privado de turma está validado no Runtime, mas isso não homologa o restante do LMS. **F4 — consolidação
do Drupal LMS — concluída no Runtime de teste em 2026-10-17.** O responsável escolheu
`scripts/content/courses/introducao-antiproibicionismo.json` como fonte editorial canônica; o caminho histórico
`scripts/institution/provision-lms-pilot.php` delega ao loader. Composer audit passou sem advisories e o dry-run
isolado previu somente a atualização LMS 1.2.3 → 1.2.4. O update 12002 foi aplicado; `updatedb:status` ficou
sem pendências; `lms.settings` e o Config Sync receberam `activity_page_title`. O conteúdo existente do curso
piloto foi comparado ao JSON no Runtime: 7 lições e 14 atividades correspondem, sem divergência. Foi
reconciliada a permissão versionada `create course_document media` no papel `lms_teacher`, que faltava no
Runtime. Teste temporário no Runtime confirmou: estudante membro pode `take course` e não pode `grade
students`; educador membro pode avaliar e criar Media `course_document`; usuário fora da turma, mesmo com
papel global `lms_teacher`, não pode iniciar nem avaliar; anônimo não pode iniciar curso. Contas temporárias
foram removidas e a turma voltou ao número original de membros. O curso publicado continua visível a
anônimos/visitantes conforme a configuração outsider. Os Kernel tests do LMS passaram (10 testes, 990
asserções); `aculta_lms` (3/13); documento privado sob Group (1/39, membro e outsider). A suíte
FunctionalJavascript upstream não foi executada porque este ambiente não fornece `SIMPLETEST_BASE_URL`. O
update adicionou `activity_page_title`; schema config validado sem erros e `updatedb:status` está NONE. O
provider Core `mysql` também foi ativado após o requisito de status apontar que estava ausente e foi registrado
no Config Sync. Não houve importação global de Config Sync por causa das divergências pré-existentes do site.
O Runtime Drush usa PHP 8.4.26; PHP 8.5 continua aberto como DT-P04 e não foi inferido. F6 — Página de
curso e sala — é a próxima fase e exige decisão/autorização próprias. PV6, CSP, matriz ampliada de
navegadores, produção Hostinger e o fluxo editorial mantêm gates separados.

**F5 — Catálogo — implementada e validada no servidor de testes em 2026-10-17.** A View `courses_catalog`
continua como fonte, com filtros de curso publicados e o row plugin nativo do LMS. O tema negocia
`lms:course_card` para `aculta420:course_card` por substituição SDC compatível; o LMS mantém URL,
ação de entrada, access e cacheability. A execução da View produziu o curso publicado para anônimo
e para a conta autenticada com papel `estudante`; os resultados carregaram contextos `user.permissions`,
`user.group_permissions`, `session.exists` e tags do curso/status. `drush cr` e a requisição HTTP do
catálogo responderam com sucesso. No Firefox headless, o cartão ficou com foco visível de 3 px por
Tab, o rail manteve nome acessível e foco nativo, e não houve JavaScript necessário ao catálogo.
Somente um curso publicado existe no Runtime; não há curso não publicado no acervo para um caso
negativo da listagem. PHP no Runtime continua em 8.4.26.

**F6 — Página de curso e sala — implementação inicial nesta branch.** O campo
extra configurável `aculta_lms_course_room` integra um índice na visualização
canônica do curso. A seção consulta `take course` no curso pai e lista somente
lições referenciadas publicadas, com links para a rota nativa LMS (com slugs
portugueses do Portal); não cria progresso, matrícula ou status. `view`
genérico da entidade Lesson não é a autorização contextual da rota. RUNTIME
STATUS: DEFERRED até smoke test de membro, não membro e visitante; a fase não
está homologada.

**F7 — Configuração aplicada no Runtime de teste; funcionalidade ainda não homologada.** Config Sync habilita `lms_classes`
1.2.4, inclui configuração opcional, exige criação explícita de turmas e concede
ao educador permissões de criação/gestão; o hook de papel global reconhece
matrículas em turma. A página do curso oferece produto Commerce próprio, escolha
de turma e checkout para preço pago ou zero. Pedido concluído efetiva membership
Group; reembolso parcial preserva acesso e reembolso integral revoga apenas a
origem comercial, mantendo memberships manuais e outras compras válidas.
Educadores podem remover matrículas manuais, mas não memberships de pedido ativo.
Decisões: equipe também pode matricular, estudante é concedido ao entrar em
qualquer turma, curso não recebe turma padrão e matrícula não exige confirmação
de e-mail/termos (aviso por e-mail, entrega reservada à F13). Checkout permite
cadastro e dispensa confirmação de e-mail, mantendo termos gerais da conta.
Em 2026-10-18, após backup, `aculta_lms` e `lms_classes` foram habilitados e 29
objetos F7 entraram por importação parcial; o restante do drift não foi importado.
`create_default_class` está desligado e o curso existente foi preservado. RUNTIME
STATUS: CONFIG APLICADA; smoke tests A/B, checkout pago/gratuito e reembolso
continuam DEFERRED. Backup privado em
`/home/aculta/private/aculta/config-reconcile-20261018/aculta_runtime-before-f7.sql.gz`.
Detalhes em
[LMS-GROUP.md](LMS-GROUP.md).

**F8 — implementação e testes automatizados entregues nesta branch; smoke visual pendente.** Os plugins contrib
existentes cobrem escolha única/múltipla, verdadeiro/falso, completar lacunas e
resposta aberta. Há feedback imediato nas variantes correspondentes. A F8
adiciona um plugin de resposta aberta com feedback formativo, mas mantém a nota
final pendente para avaliação do educador, e habilita o módulo contrib
`lms_answer_comments` para comentário ligado à resposta. A avaliação da resposta
discursiva nunca usa a pontuação automática por frases como nota final.

O código upstream apaga respostas substituídas, reavaliadas ou reiniciadas. A
branch implementa uma cópia interna de auditoria em `aculta_lms`, sem substituir
o estado operacional do LMS. A cópia inclui a resposta anterior, nota,
timestamps e a conversa vinculada à resposta; é visível só com `take course` ou
`grade students` atuais e removida com o progresso do curso, conta ou curso.
Unit e Kernel do `aculta_lms` passaram (15 testes, 259 assertions); o teste upstream
de seleção passou (2 testes, 4 assertions). Kernel cobre Answer/Comment reais,
edição, reavaliação, exclusão, limpeza por progresso/conta/curso, isolamento entre
turmas, permissões, saída em texto e cacheability. Gates Portal (950 checks),
imagem, slugs e PHP lint passaram. O Kernel registra 34 depreciações no
ecosistema Group/LMS/Views/Core, sem alterações em contrib/Core. No Runtime de
teste, o update de schema segue aplicado e o cache foi reconstruído após a
correção; o smoke visual por navegador está pendente. RUNTIME STATUS: UPDATE
APLICADO; homologação visual DEFERRED. A F9 (Webform Score e entregas) está concluída; a F10 (H5P) começou por auditoria de segurança e compatibilidade.

**F9 — implementação e critérios automatizados concluídos nesta branch, autorizada pelo responsável em 2026-10-18.** `webform_score` 2.4.0,
`lms_file_upload` 1.0.2 e `fraction` 3.1.0 foram adicionados via Composer; o dry-run indicou somente
três pacotes novos e zero atualizações/remoções. Os módulos estão habilitados no Runtime de teste e em
`core.extension`. Revisão do código upstream encontrou que o dono de uma resposta LMS mantém acesso a
`see_details` sem revalidar matrícula. `aculta_lms` agora adiciona `take course` atual à rota de download
de anexos do LMS File Upload, preservando a autorização LMS para educadores. Kernel usa arquivo no
filesystem privado temporário, valida a rota descoberta, transmite os bytes, nega a rota após revogar
membership e preserva acesso do educador; a suíte do submódulo passou (10 testes, 311 assertions; 77
deprecações Core/contrib). Unit passou (15 testes, 81 assertions), incluindo permissões de leitura própria,
leitura ampla e bloqueio de edição de score Webform. Auditoria somente leitura no Runtime confirmou que
nenhum papel atualmente tem as permissões `view any submission score` ou `view own submission score`.
Kernel também passou com uma submissão Webform persistida e autoria real: o dono com a permissão de leitura
própria pode ver a nota, outra conta é negada e edição direta permanece proibida (1 teste, 11 assertions;
42 deprecações de Core/contrib). O Runtime não tem atividade de upload, resposta de arquivo, atividade de quiz
ou formulário com score; por isso, smoke HTTP/browser no Runtime fica `DEFERRED` e não foram criadas fixtures
persistentes nele. A suíte automatizada cobre transmissão de arquivo privado, membership revogada, acesso
preservado de educador e autoria/controle de nota Webform com entidade persistida. Não há integração de nota
Webform para LMS, conforme fronteira definida.
Webform Score continua restrito às submissões Webform; não há integração que promova resultado
automaticamente para a nota LMS. A tentativa automática de baixar traduções
pt-BR dos três módulos durante `drush en` falhou porque `translations://` não está configurado neste
Runtime; o código e a ativação terminaram com sucesso. RUNTIME STATUS: MÓDULOS HABILITADOS; homologação
de acesso Runtime/browser DEFERRED por ausência de conteúdo de teste. **F10 — concluída em 2026-10-19.**
No Runtime `cursos`, a tela administrativa instalou uma biblioteca de teste; o educador salvou atividades
no Group descartável 15 e a estudante matriculada reproduziu o tipo `div` pelo player H5P. O navegador
anônimo não recebeu o player e foi bloqueado (403) na rota de arquivos privados; a estudante passou pela
verificação de acesso do curso. O smoke revelou e corrigiu o URI privado enviado ao instalador, a restauração
de serviços do formulário entre requisições, a agregação indevida dos assets privados, o formatter de
bibliotecas dinâmicas e o namespace do formulário de matrícula da sala. As fixtures, contas e biblioteca
de teste foram removidas por APIs Drupal; a biblioteca `H5P.HelloWorld` que já existia foi preservada.
Unit e Kernel LMS/H5P, gates Drupal 11+, imagens e slugs: resultados registrados no changelog da integração.
RUNTIME STATUS: HOMOLOGAÇÃO DE TESTE CONCLUÍDA; produção não foi alterada. Detalhes em
[LMS-H5P-AUDIT.md](LMS-H5P-AUDIT.md) e no [changelog do módulo H5P](../../web/modules/custom/aculta_portal/modules/aculta_lms_h5p/CHANGELOG.md).

As etapas depois da F10 continuam na ordem definida acima: F11 certificados, F12
dashboards, F13 notificações educacionais, F14 conexões, F15 integrações de conteúdo, F16 hardening e RC
integrado. F17 Google Classroom permanece opcional, por último e dependente de nova decisão. F10 está
homologada no Runtime de teste; os objetivos de integração de envio de imagem e discussão passam para F15,
com discussão condicionada à implementação executável do `aculta_forum`. As homologações Runtime pendentes
de F6–F9 (funcional, testes, gates e visual aplicável) continuam antes da RC; a
validação do ambiente Runtime ocorre no Alpha após deploy.

**F11 — implementação parcial na branch atual.** O storage de
snapshot, emissão/reemissão transacional, revogação com motivo, verificação
pública e PDF escapado foram adicionados. Kernel dedicado cobre elegibilidade,
autorização, cadeia de emissão, revogação, retenção após exclusão de conta e
headers do PDF. Unit e gates passam. O QR integra `aculta_qrcode` por URL
absoluta do `DomainPurposeManager`; o SVG é servido sem persistir arquivo e o
PNG é incorporado ao PDF. No Runtime de teste, a rota pública para código
inexistente e as rotas de QR e PDF com código inexistente retornam 404. No
Runtime há zero certificados e zero status aprovado atual. Em 2026-10-21,
`field_course_hours` não existia na configuração ativa; após revisar o diff
global (dezenas de itens) e confirmar que o storage ainda não tinha tabela nem
valores, foi importado parcialmente somente o storage do campo, a definição do
campo no bundle `lms_course` e seu widget no formulário ativo. A prévia mostrou
exatamente duas criações e uma atualização; a configuração anterior do formulário
foi guardada para recuperação e os demais itens locais foram preservados. O
campo existe agora, mas tem zero valores e não há status aprovado; não foi criada
uma conclusão artificial nem certificado público. Portanto, ainda não existe
fixture real elegível para smoke positivo. Após materializar os padrões do Core
no YAML canônico, `config:status` não aponta diferença na storage nem na
definição do campo; o display continua com diferenças locais anteriores, que
foram preservadas. O 404 inicial foi corrigido por
`drush cr`, que atualizou um container compilado com argumentos antigos do
serviço. RUNTIME STATUS: parcial; campo e formulário ativos, emissão positiva,
QR/PDF válidos para código real e revisão visual continuam DEFERRED.

Em 0.1.0-dev.45, o campo foi restringido a no mínimo uma hora, mesma condição
exigida pela emissão. O Kernel de certificados rejeita zero horas (3 testes /
85 assertions PASS; 38 depreciações upstream). Como o campo ainda não tem
valores, a única definição foi importada parcialmente após revisão da prévia;
Runtime agora confirma `min: 1`, sem mudanças em outras configurações. Cache foi
reconstruído e Drush não encontrou updates pendentes. O rebuild registrou o
aviso conhecido do asset H5P fora desta alteração.

Em 0.1.0-dev.46, o Kernel passou a cobrir também uma resposta positiva do
endpoint SVG do QR para um código ativo, incluindo cache privado, MIME,
`nosniff` e CSP. O teste usa provedor mock; a geração real continua coberta na
suíte `aculta_qrcode` e a validação HTTP de um certificado real segue pendente.
`CertificateLifecycleTest`: 3 testes / 94 assertions PASS, com 38 depreciações
upstream.

Em 0.1.0-dev.47, um teste Unit integra `CertificateQrCodeProvider` com o
`DomainPurposeManager`, o validador, o registry e o gerador `aculta_qrcode`
reais. Confirma URL absoluta no propósito `main` e falha fechada sem domínio;
Unit do módulo: 50 testes / 277 assertions PASS, com 8 depreciações do PHPUnit.

**F12 — decisões de conteúdo recebidas em 2026-10-20.** Estudante: cursos,
progresso, avaliações pendentes e certificados próprios. Educador: indicadores
agregados por turma, sem respostas abertas nem dados identificáveis na visão
agregada. Implementar sobre Group/LMS/Views existentes e a emissão F11, sem
duplicar estado. Ver contrato em `LMS-INTEGRATION-CONTRACTS.md`.

F12 tem implementação inicial na branch: painel do estudante em
`/minha-aprendizagem` e contagens do educador por turma sob
`/painel-administrativo/lms/turma/{group}/painel`. Kernel Group/LMS verifica que
somente estudantes elegíveis e ativos da turma entram nas métricas, exclui
educador, administrador, conta bloqueada e outsider, e mantém as respostas fora
do retorno; cache é
invalidado por alterações nas memberships/contas consideradas (1 teste/38
assertions). Unit dos painéis: 4/18 assertions PASS. Revisão visual, fixture
integrada do painel do estudante e smoke Runtime permanecem pendentes. Runtime
status: DEFERRED.

Em 0.1.0-dev.43, o progresso passou a usar `view` genérico de Lesson. A
verificação com entidades reais demonstrou que esse access nega estudantes
matriculados, embora a rota nativa permita a participação via `take course` no
curso pai. Em 0.1.0-dev.48, sala e painel foram alinhados ao contrato correto:
o curso precisa autorizar `take course` e somente lições publicadas do curso
entram no currículo e no progresso. Kernel confirma que `view` genérico é
negado para a estudante matriculada, mas a aula publicada e sua conclusão são
contadas; a aula não publicada é excluída. Suítes completas: Unit 51/281 e
Kernel MariaDB 20/631 assertions PASS. Revisão visual e smoke Runtime seguem
pendentes.

**F13 — implementação funcional e integração de teste concluídas; revisão visual pendente.** Os eventos contratados, incluindo avisos por conteúdo publicado, retorno do educador, prazos por atividade/turma e lembrete em janela de 24h, são produzidos pelo `aculta_lms` e entregues via Message/`aculta_messenger`. Runtime de teste confirmou persistência do aviso e lembrete, integração com aviso pessoal e worker, além da limpeza das fixtures. Unit: 47 testes / 210 assertions; Kernels Group/Message: 6 testes / 85 assertions. O teste não aguardou Cron agendado nem revisou visualmente a gaveta. F13 permanece Dev até revisão visual/acessível e gates/revisão pré-RC.

Regressão combinada atual de `aculta_lms` e `aculta_lms_messenger` (2026-10-21):
Unit 67 testes / 347 assertions e Kernel MariaDB 21 testes / 643 assertions
PASS; 8 depreciações PHPUnit e 92 de Core/contrib. O LMS Unit isolado é 51/281
e o Kernel isolado é 20/631. O gate geral de endereços de ambiente ainda reprova
22 literais em testes de `aculta_qrcode` e `aculta_mailer`; o teste QR do LMS
agora usa domínio `.test` e não aparece mais entre as ocorrências.
`composer audit --locked` não encontrou advisories. O Homelab executa PHP
8.4.26 e não tem binário PHP 8.5; a versão alvo continua sem validação.

O formulário de prazo foi protegido por teste Unit para o contrato de fuso por conta: `#date_timezone` usa o fuso configurado do educador ou UTC como fallback; o campo DateTime do Core armazena os instantes em UTC. Drupal Core define UTC como fuso de armazenamento e `#date_timezone` como o fuso de exibição/interpretação ([DateTimeItemInterface](https://api.drupal.org/api/drupal/core%21modules%21datetime%21src%21Plugin%21Field%21FieldType%21DateTimeItemInterface.php/interface/DateTimeItemInterface/11.x), [elemento datetime](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Datetime%21Element%21Datetime.php/class/Datetime/11.x)). Unit: 2 testes / 8 assertions PASS.

**F14 — escopo de conexão no LMS aguarda decisão de produto.** `aculta_user`
mantém a fonte de amizade e `aculta_messenger` consulta o estado atual para
conversas; o LMS não tem ação própria na turma. A lista de colegas pode revelar
matrículas e não será exibida antes de definir o escopo. A dívida DT-O15 de
pedidos recebidos sem formulário ativo no Messenger também permanece separada.

**F15 — integrações parciais; atividade/discussão e homologação ainda abertas.**
`capa_curso` usa `aculta_image`; imagens do editor H5P passam por
`ImageUploadIngestor`; anexos raster de respostas passam pela `UploadPolicy` e
`ImageInspector`; materiais PDF usam `course_document`, Group e
`aculta_pdf_viewer`. `aculta_forum_lms` e suas permissões estão ativos no
Runtime de teste e suportam tópicos Node/Comment no Group de curso/turma, mas
não há tópico/Comment LMS real nesse Runtime, vínculo a atividade, nem discussão
criada a partir da sala do estudante. Unit do adapter: 18/190 assertions; Kernel
MariaDB: 2/127 assertions PASS com 43 depreciações upstream; a cobertura é de
curso/turma e não inclui atividade. O constraint de imagem em entrega é
exercitado pelo `file.validator` do Core com arquivo válido/inválido, e o
Kernel cobre a rota privada após revogação; upload de ponta a ponta no Runtime,
anexos de discussão e renderização HTTP/visual seguem pendentes.
O escopo de Gallery, Magazine e Wiki também precisa ser concretizado em
integrações específicas, sem CMS paralelo.

**F16 — hardening inicial em curso.** A reemissão de certificados agora usa o
lock do Core por UUID da conclusão para impedir duas versões ativas em
requisições concorrentes; uma segunda emissão enquanto o lock está ocupado
recebe conflito sem persistir. Em arquivos de entrega, o Kernel confirma que
um estudante matriculado não baixa a resposta de outro estudante; o dono
matriculado tem acesso, perde-o após revogação e um educador com `grade
students` continua autorizado. Quando o Drupal LMS remove um LessonStatus, a
resposta pendente na fila não mantém contexto de curso; a rota agora nega essa
janela sem erro 500. Formulário de prazo e publicação do evento de feedback
resolvem relações via campos de referência e encerram sem erro quando
LessonStatus/CourseStatus já foi removido. `ActivityDeadlineFormHooksTest` e
`EducatorFeedbackEventHooksTest` cobrem referências ausentes; Unit completo do
`aculta_lms`: 42 testes / 197 assertions PASS (5 depreciações), executado em
PHP 8.4.26; PHP 8.5 não validado. O arquivamento pré-exclusão e a consulta de
histórico também resolvem relações por campos; status/curso/aula/atividade já
removidos suprimem o snapshot incompleto ou retornam histórico vazio sem erro.
`AttemptArchiveLifecycleTest`: 9 testes / 369 assertions PASS no banco Kernel
dedicado; 42 depreciações de Core/contrib. O access do histórico nega contas
bloqueadas e revalida revogação de membership após limpar o resultado de access
da requisição anterior. Tanto histórico quanto download resolvem o User real
mesmo quando o request fornece AccountProxy/session. Download de arquivo
privado também nega estudantes e educadores bloqueados antes de delegar ao LMS.
No Runtime `cursos`, Drush confirmou módulo ativo/sem updates; após `drush cr`,
serviços carregaram e o loader resolveu os dois usuários de teste via
`UserSession`. O rebuild emitiu warning preexistente de configuração `assets://h5p`
e terminou com sucesso. Sem fixture real de resposta, download privado segue
sem smoke Runtime positivo. Unit: 46 testes / 215 assertions PASS (6 depreciações).
Verificação de certificado, render
do snapshot e painéis agora preservam
`max-age: 0` ao mesclar tags/contextos; `CertificateLifecycleTest`: 3/75,
`ClassDashboardKernelTest`: 1/43 e `LearningDashboardControllerTest`: 1/4
assertions PASS. O painel exclui contas bloqueadas e mantém a dependência da
entidade User para invalidar a contagem se a conta voltar a ser ativa; o Kernel
cobre esse caso. `CertificateLifecycleTest`: 3/82 confirma que o certificado
continua verificável após bloqueio da conta, que a reemissão gera código
URL-safe diferente de 43 caracteres e que códigos curtos/longos são rejeitados
antes da consulta; outro Kernel já cobre exclusão da conta. Core documenta que
`applyTo()` define diretamente o metadata
do render array e que dependências devem ser mescladas ([CacheableMetadata](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Cache%21CacheableMetadata.php/class/CacheableMetadata/11.x), [Renderer::addCacheableDependency](https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Render%21Renderer.php/function/Renderer%3A%3AaddCacheableDependency/11.x)). O adapter F13 também passou a resolver referências de resposta por campos antes de montar avisos de submissão, avaliação ou feedback: quando o LMS remove LessonStatus/CourseStatus de forma assíncrona, a notificação desaparece até a limpeza terminar em vez de lançar erro. Unit do `aculta_lms_messenger`: 1/6 PASS. O conjunto ainda não completa a matriz de acesso/cache,
acessibilidade e os gates da fase.

Em 0.1.0-dev.44, a fixture de instalação passou a criar uma entidade User real
para o access de histórico que valida status ativo via `AccountUserLoader`.
Kernel completo do `aculta_lms`: 19 testes / 594 assertions PASS no MariaDB
dedicado; 90 depreciações de Core/contrib e uma de `webform_score`, sem falhas.

Em 0.1.0-dev.40, a autorização contextual para emitir e revogar certificados
declara também o cache context `user.group_permissions` exigido pelo Group. Isso
permite variar e invalidar o resultado quando membership ou papéis de turma
mudam; o teste Unit focal verifica a presença do contexto. Unit: 47 testes / 219
assertions PASS (8 depreciações PHPUnit); PHPCS Drupal/DrupalPractice, gate do
índice de estado e gate Drupal 11+ (1010 checks) PASS. Runtime `cursos` usa
`aculta_runtime`, `aculta_lms` está habilitado e não há updates pendentes;
`drush cr` concluiu com warning conhecido de assets H5P fora do escopo.

Em 0.1.0-dev.41, `CertificateLifecycleTest` verifica também que o handler
genérico do snapshot nega `view` e `create` mesmo ao administrador, enquanto a
rota pública por código opaco permanece disponível. Kernel: 3 testes / 84
assertions PASS no MariaDB dedicado; 38 depreciações upstream.

## Ordem, commits e status

Cada fase é uma unidade pequena e commitada na branch autorizada. PR só quando
solicitada. A autorização explícita do responsável para F9 permite a
implementação nesta branch; não autoriza importação de cursos, merge ou deploy.

Antes de operação Homelab: registrar `hostname`, `pwd`, branch/estado Git e Drush; confirmar site URI, banco, estado de configuração e possibilidade de recuperação.

## Rollback transversal

Prévia de mudanças: `composer require --dry-run`, `composer validate`, `composer audit`, diff de configuração e entidades, inventário de arquivos e estratégia de restauração. Toda atualização de schema/config/estado deve ter backup verificável e caminho de retorno. Rollback de código não remove dados de Group/LMS/File/Media/Message; não usar downgrade que perca respostas, notas ou memberships. Mudanças de grants podem cortar acesso, por isso validar usuário A/B e preservar access antes de liberar.

## Referências funcionais

- [ADR-005](../decisions/ADR-005-lms-integration.md)
- [LMS e Group](LMS-GROUP.md)
- [Contratos de integração](../portal/LMS-INTEGRATION-CONTRACTS.md)
- [PDF compartilhado](../portal/PDF-VIEWER-INTEGRATION.md)
- [Matriz de segurança](../portal/SECURITY-ACCESS-FILES-MATRIX.md)
- [LMS Drupal.org](https://www.drupal.org/project/lms)
- [Opigno Catalogue](https://www.drupal.org/files/Opigno-Catalogue.png), [Training Page](https://www.drupal.org/files/Opigno-Training-Page.png), [Dashboard](https://www.drupal.org/files/Opigno-Dashboard.png): referências visuais/funcionais; não copiar CSS/templates. O [repositório Opigno](https://github.com/Craftmasters/opigno) declara GPL-2.0, e a página do [Opigno LMS Community](https://www.drupal.org/project/opigno_lms) informa licença igual ao Core; qualquer reaproveitamento de código exige confirmar a licença do arquivo/versão exata e preservar avisos/atribuição.
