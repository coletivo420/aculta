# Roadmap do aculta_lms e visualizador PDF global

Revisado em 2026-10-18. A implementação segue fases independentes, com autorização e critérios próprios. O status é registrado por fase; a homologação de PDF privado não ativa automaticamente o módulo `aculta_lms`.

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
| F10 — H5P | Integração própria `aculta_lms_h5p`, editor, bibliotecas privadas e reprodução integrada ao curso | Bibliotecas, permissões, uploads, CSP/isolamento, salvamento e acesso de estudante homologados; sem nota oficial até API confiável | Despublicar atividades sem apagar conteúdo válido; preservar bibliotecas enquanto houver referências |
| F11 — Certificados | Entity Print após conclusão LMS aprovada | emissão/autorização, verificação, reemissão, revogação, auditoria, privacidade e template testados | Definir registro imutável/derivado antes de codar. Reverter emissão sem apagar auditoria/artefatos válidos |
| F12 — Dashboards | SDC ACULTA420 sobre LMS services/Views | dados próprios para estudante; aggregate limitado à turma para educador; sem cache cruzado | Reverter presenters/Views e manter rotas LMS |
| F13 — Notificações educacionais | Eventos LMS → `aculta_messenger` / Message Notify | dedupe, access atual, preferências, e-mail mínimo e fila testados | Desabilitar subscriber/producer; Message permanece fonte e não apagar comunicações existentes |
| F14 — Conexões | `FriendshipManagerInterface` e Messenger existentes | ações e notificações usam amizade atual sem duplicar estado/permissão | Reverter adapter; amizade continua em `aculta_user` |
| F15 — Integrações de conteúdo | Gallery, Magazine, Wiki, Forum e eventos quando estabilizados | referência de entidades e acesso preservados em todos os purposes | Só após roadmap/PRs dos submódulos estabilizados; remover links sem deletar conteúdo |
| F16 — Hardening | performance, segurança, privacidade, acessibilidade, cache e tamanho/timeout PDF | zero Critical/High; matriz completa e gates PASS | rollback por componente/flag/config, sem downgrade destrutivo de entidades |
| RC — Homologação integrada | testes no Homelab, browser, config sync, documento e gate de release | checklist/testes completos, Runtime PASS, versão e rollback anotados | Nenhum deploy/merge automático; interromper RC se access/cache, segurança ou banco divergir |
| F17 — Google Classroom (opcional e última) | Após RC aprovado e nova decisão; publicar somente link para atividade ACULTA | OAuth/scopes, consentimento e link para fonte LMS aprovados | Nenhum pacote/token/rota/storage nesta linha; desabilitar integração sem afetar LMS |

## Estado de fase

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
canônica do curso. A seção consulta `take course`, verifica `view` por lição e
gera links para a rota nativa LMS (com slugs portugueses do Portal); não cria
progresso, matrícula ou status. RUNTIME STATUS: DEFERRED até smoke test de
membro, não membro e visitante; a fase não está homologada.

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

As etapas depois da F10 continuam na ordem definida acima: F11 certificados (após decisões de produto), F12
dashboards, F13 notificações educacionais, F14 conexões, F15 integrações de conteúdo, F16 hardening e RC
integrado. F17 Google Classroom permanece opcional, por último e dependente de nova decisão. Antes de avançar
para essas fases, fechar as homologações Runtime pendentes de F6–F9 e a aceitação ponta a ponta da F10.

## Ordem, PR e status

Cada fase é uma unidade pequena, commitada e descrita em PR própria conforme a regra do repositório. A autorização explícita do responsável para F9 permite a implementação nesta branch/PR; não autoriza importação de cursos, merge ou deploy.

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
