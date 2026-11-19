## Catálogo público de cursos

As Views `courses` e `courses_catalog` são configuração padrão deste módulo,
com cópias sincronizadas em `config/sync`. Elas consultam grupos do tipo
`lms_course`, preservam as rotas `/courses` e `/cursos/catalogo` e renderizam
cada Group pelo modo de exibição padrão (`entity:group`). A capa e a descrição
seguem o display `group.lms_course.default`; UI Patterns Views compõe as rows
com o SDC `layout-grid` (mínimo de 16rem, máximo de três colunas). Os filtros,
permissão, renderização de Group, pager e cache permanecem na View. O row plugin
legado `course_card_row` não é mais necessário. Config Sync e Runtime ainda
precisam ser alinhados/homologados antes de remover qualquer fallback adicional.

# ACULTA LMS

## ACULTA Studio Ganjalab (ADR-038)

The student dashboard is canonically `/ganjalab` in the Domain purpose
`courses`. Its legacy `/studio/ganjalab` path is removed and must return 404;
the account purpose does not serve this Suite. Keep course/LMS ownership,
enrollment and access checks in LMS. Cross-purpose links must use
`DomainPurposeManager`.

Os Kernels LMS agregados passam com 29 testes / 835 assertions no banco
dedicado. `AcultaLmsDependencyHarnessTrait` registra somente as classes reais
de Portal, Messenger, LMS e Studio necessárias para compilar os cenários
focais; ele não instala toda a configuração desses módulos. A suíte não
substitui um teste de instalação limpa da closure completa. Uma depreciação
reportada pertence ao contrib `webform_score`. Nenhum serviço fictício foi
adicionado ao produto e Runtime não foi alterado.

Submódulo opcional de aprendizagem do Portal. Group mantém cursos, turmas,
relações e memberships. O `aculta_lms` é dono das entidades ACULTA de aula,
atividade, resposta, progresso, tentativa e certificado; não depende dos
módulos Drupal LMS contrib. Mensagens, pagamento e identidade continuam nos
módulos owners correspondentes, sem storage duplicado.

## Ganjalab Educador — estrutura de curso

O workspace `/ganjalab/educador` oferece a estrutura de cada curso em
`/ganjalab/educador/cursos/{course}/estrutura`. A página lista aulas e
atividades das entidades ACULTA; Form API permite criar/editar aula e criar ou
editar atividade com os tipos atendidos pela submissão do aluno (`true_false`,
`select`, `fill_blanks`, `free_text`, `file_upload`). Enunciado, gabarito,
opções, respostas aceitas e mínimo de caracteres são gravados no `answer_config`
já consumido pela sala e pelo formulário de resposta. O tipo fica imutável
depois da criação; enunciado/gabarito não podem ser alterados após haver
respostas, para não reescrever o contexto histórico das notas.

A autorização exige `grade students` contextual ao Group de curso ou
`administer lms`. A rota verifica que aula pertence ao curso e atividade à aula;
os formulários repetem essa checagem no servidor ao carregar e salvar. Turmas,
matrículas e prazos permanecem separados da estrutura curricular. Reordenação
O Educador pode mover aulas e atividades por arraste dentro da própria lista;
os comandos confirmados de mover acima/abaixo continuam disponíveis para
teclado e sem JavaScript. A gravação confirma novamente a permissão, a
ancestralidade e a coleção completa sob lock e transação; a ordem compartilhada
afeta imediatamente as turmas do curso. A exclusão é confirmada e bloqueia
conteúdo com dependências ou histórico. Módulos pedagógicos e homologação
visual integral continuam pendentes.

## Integração de avisos — contrato vigente

O `aculta_lms` é dono dos cursos, matrículas, status e da elegibilidade dos
destinatários. A conclusão é publicada pelo provider atual como o evento neutro
`Drupal\aculta_lms\Event\CourseCompletedEvent` (incorporado do antigo `aculta_learning_contracts`);
Game concede a conquista por esse contrato e Messenger encaminha o aviso ao fluxo centralizado.
Consumidores, avisos no site, persistência, central, deduplicação, preferências
e a orquestração de e-mail são centralizados no `aculta_messenger`.
O aviso no site é obrigatório; o membro pode escolher e-mail desligado, direto
ou digest/resumo, sempre sujeito às políticas administrativas. Transporte,
templates e logs de entrega permanecem no `aculta_mailer`. O `aculta_lms` é consumidor
direto do `aculta_messenger`, publicando eventos de aviso através dos contratos centrais. Consulte [`docs/ROADMAP.md`](docs/ROADMAP.md).


## Administração organizada por curso

O curso é o primeiro nível da administração. A sequência de aulas e atividades
usa as entidades próprias do `aculta_lms`; seus IDs são referências internas
opacas e não vêm de entidades Drupal LMS contrib. A navegação não oferece
atalhos globais de aulas, atividades ou turmas. Painel e prazo de turma usam
`/painel-administrativo/lms/curso/{course}/` e conferem o vínculo entre curso,
turma e atividade. Fóruns e filas de moderação também ficam sob o curso; o
adapter valida o Group da discussão em cada request.

## F7/F16 — preservação de matrículas por compra

Quando um pedido perde o entitlement por reembolso integral, a matrícula só é
revogada se não houver outra compra válida para a mesma conta, turma e curso.
O novo teste confirma a transferência da origem para o pedido ativo e que a
membership não é removida. `CourseOrderEnrollmentManagerTest`: 7 testes / 35
assertions PASS; Unit LMS/Messenger: 87/470 PASS. Checkout HTTP, pagamento
externo e reembolso no Runtime seguem pendentes.
Pedidos pagos também reconciliam suas memberships com os itens e turmas atuais:
turma retirada do pedido perde o vínculo, salvo outra compra ativa; itens ainda
válidos preservam a membership, e vínculos manuais sem origem não são alterados.
`CourseOrderEnrollmentManagerTest`: 9 testes / 47 assertions; Unit LMS/Messenger:
89/482 PASS.
`AcultaLmsInstallTest` também verifica que insert e update de pedido estão
registrados como hooks de reconciliação; Kernel focal: 4/49 PASS. O harness
compartilhado injeta a cadeia real do `AuthorCardBuilder` e todos os argumentos
dos serviços de pagamento sem instalar a configuração completa de módulos
alheios.

## F11 — emissão e revogação por educadores

O Kernel percorre os submits dos formulários de emissão e revogação com um
educador real, autorizado por `grade students` no curso e sem permissão
administrativa global. A emissão redireciona para a verificação pública pelo
código, e a revogação preserva o registro e o motivo informado.
`CertificateLifecycleTest`: 5 testes / 159 assertions PASS; 38 depreciações de
Core/contrib. A conclusão elegível aparece no seletor. Smoke Runtime dos
formulários e revisão visual permanecem pendentes.

## F16 — referências removidas em respostas

O access checker do prazo invalida uma negação armazenada quando a turma passa
a ter curso pai; o teste usa uma entrada real de cache e o cache tag de lista
de relações do Group instalado. Também acompanha alterações do curso quando a
atividade não está vinculada às aulas dele. `ClassActivityDeadlineTest`: 1/49;
Kernel LMS/Messenger: 23/722 assertions PASS (95 depreciações upstream).
O mesmo teste comprova a invalidação da negativa do painel de educadores quando
a relação curso→turma é criada: `ClassActivityDeadlineTest` 1/52; regressão
Kernel LMS/Messenger 23/725 assertions PASS (95 depreciações upstream).
O resultado positivo do painel também é invalidado ao remover a relação
curso→turma: `ClassDashboardKernelTest` 1/46; Kernel LMS/Messenger 23/728
assertions PASS.

Os hooks do formulário de prazo por atividade e do aviso de feedback do
educador resolvem curso pelos campos de referência LMS e toleram LessonStatus
ou CourseStatus removidos durante a limpeza assíncrona. Nesses casos, o
formulário não recebe configuração de prazo e o evento de feedback não é
publicado. Unit do módulo: 41 testes / 192 assertions PASS; 5 depreciações do
ecossistema de teste. A execução local usa PHP 8.4.26 e não valida PHP 8.5.
O arquivamento pré-exclusão e a consulta do histórico também deixam de forma
segura de operar se o LMS já removeu status, curso, aula ou atividade; nesse
caso não é criado snapshot incompleto. A suíte Unit completa passou com 46
testes / 215 assertions (6 depreciações). `AttemptArchiveLifecycleTest` passou
com 9 testes / 369 assertions no banco Kernel dedicado; 42 depreciações vieram
do Core e contrib. O access do histórico também nega estudante ou educador
bloqueado e revalida após bloqueio e revogação da membership.
A rota atual de anexos recebe apenas `AnswerEntity` ACULTA, valida arquivo
permanente em `private://` e revalida matrícula/permissão a cada requisição.
O plugin de upload e a rota de download vinculados a `lms_answer` foram
removidos no P5. A interface ACULTA de submissão para `file_upload` grava a
referência do arquivo no payload JSON da resposta, torna o arquivo permanente
e registra seu uso. Kernel cobre submissão privada pelo dono e recusa de
conteúdo que não corresponde à extensão.
O resolvedor tipa entidades de referência com `FieldableEntityInterface`; o
PHPCS Drupal/DrupalPractice passou nos arquivos novos de resolução e no
`AttemptArchiveManager`.
Na emissão de certificado F11, o manager e o formulário também usam as
referências dos campos do `CourseStatus`: se usuário ou curso tiver sido
removido na limpeza assíncrona, o manager responde 422 e o formulário omite a
conclusão, sem erro 500. Os prazos por turma seguem resolvidos pelo manager
ACULTA e cobertos por `ClassActivityDeadlineTest`; o hook contrib que alterava
o formulário de resposta e o componente LMS de início de curso foram removidos.
A interface ACULTA de submissão está implementada. O Kernel do formulário
confirma armazenamento do payload e do uso de arquivo; smoke HTTP/visual com
resposta real permanece pendente. Unit LMS/Messenger: 84 testes / 445
assertions PASS, com 8 depreciações PHPUnit. F11 executa emissão/revogação com
educador contextual e confirma a opção elegível no formulário: Kernel focal
5/159. Smoke Runtime autenticado e revisão visual permanecem pendentes.
No Runtime de teste, Drush confirmou o módulo habilitado e sem updates; após
rebuild do cache, o loader resolveu os dois usuários de teste por `UserSession`.
O rebuild teve warning de assets H5P e terminou com sucesso. Nenhuma fixture foi
criada, então o download real de resposta continua sem smoke positivo.

## Integrações de conteúdo

- Imagens de capa de curso usam `aculta_image` e sua `UploadPolicy`; imagens
  enviadas ao editor H5P usam `aculta_image.upload_ingester` e o ciclo de vida
  de File/uso compartilhado. Arquivos anexados a respostas do LMS passam pela
  política `aculta_file_uploader.consumer_policy`, na integração `lms` (painel
  `/painel-administrativo/aculta/uploads`), e as imagens rasters também
  pelo validador `AcultaLmsSubmissionImage`, que usa a mesma política. Continuam em storage privado e
  são entregues pela rota de download protegida pela resposta e pela matrícula
  vigente.
- PDFs de material de turma usam Media `course_document`, relação de acesso
  Group e o viewer `aculta_pdf_viewer`; o acesso ao arquivo é revalidado pela
  rota privada do Core. O módulo declara a dependência do submódulo Group do
  viewer para evitar configuração de curso sem o plugin correspondente.
- Discussões LMS consom os serviços e o motor de discussão do `aculta_forum`, com Node e Comment
  compartilhado em Group de curso/turma, access contextual, membership
  revalidada e moderação por `grade students` do curso. `lms_classes.settings`
  herda essa permissão Group Node para turmas. As permissões estão ativas no Runtime de teste. Tópicos podem
  referenciar `field_lms_activity`; o formulário de resposta lista discussões
  autorizadas no Group do curso ou turma da atividade. Config Sync parcial e
  resolução do serviço/hook foram confirmadas, mas o Runtime ainda não tem
  tópico/Comment LMS para smoke HTTP ou revisão visual. Respostas Comment usam
  `usuario_aculta` e o pipeline inline `aculta_image`; anexos separados de
  arquivo não têm fluxo próprio. O contrato e as condições estão em
  [LMS-INTEGRATION-CONTRACTS.md](../../../../../../docs/portal/LMS-INTEGRATION-CONTRACTS.md).

As integrações ainda exigem aceite ponta a ponta no Runtime após a configuração
de cada fluxo; a dependência do PDF garante disponibilidade do plugin, mas não
substitui a validação de permissão/cache nem importa conteúdo. Testes atuais do
adapter Forum LMS: Unit 22/256 assertions e Kernel 2/132 assertions PASS; o
Kernel registra 43 depreciações upstream e ainda não contém fixture do vínculo
atividade-discussão.

O hook `SubmissionImageHooks::answerFormAlter()` aplica
`restrictValidators(..., 'lms')` a toda resposta com upload, não só às imagens:
o educador continua escolhendo as extensões de cada atividade, mas só dentro da
política central efetiva, e o limite de tamanho só diminui. O texto de ajuda do
campo mostra a lista realmente aceita — a interseção entre as extensões da
atividade e a política, não o teto inteiro da política. Com a aba LMS herdando
o global (padrão), a política efetiva é a mesma de antes da migração: 9
extensões
(`png jpg jpeg gif webp pdf docx xls xlsx`) e 10 MB. Imagens inválidas, com MIME
incompatível, continuam recusadas por `AcultaLmsSubmissionImage`.

Unit `SubmissionImageHooksTest` cobre o acréscimo do validador de imagem, a
rejeição de extensões fora da política, o texto de ajuda com a lista aceita
(`png pdf` para uma atividade `png exe pdf txt` sob política `png pdf docx`) e a
não alteração de formulários sem upload. Kernel `AttemptArchiveLifecycleTest`
passou com 8 testes / 322 assertions, inclusive download privado, revogação de
membership e acesso de educador. Kernel `SubmissionUploadPolicyKernelTest`
(5 testes / 39 assertions) fixa a regressão: herança sem mudança de política,
atividade que não amplia extensões nem eleva o teto, aba LMS salva que restringe
sem tocar no global, formulário sem upload intacto e validadores nativos mantidos
sem o serviço. Upload e download foram verificados por HTTP no Runtime de teste
(dev.80 e dev.81); o smoke ponta a ponta de revogação no Runtime continua
pendente.

## F1 — Fundação

O serviço `aculta_lms.course_completion_publisher` publica o evento
`CourseCompletedEvent` através do Event Dispatcher do Drupal quando recebe um
status LMS finalizado e aprovado. A chamada deve ocorrer somente depois da
persistência da transição para aprovado. O contrato inclui identificador UUID
do status de origem, curso, conta e instante de conclusão; nunca inclui nota,
resposta, arquivo ou corpo de mensagem.

O publisher não persiste, deduplica, altera estado LMS ou decide destinatários.
Consumidores devem reabrir a entidade LMS e revalidar acesso/membership no
momento de uso. A deduplicação, preferências, canais e entrega pertencem ao
contrato LMS → Messenger da fase F13. Nenhum produtor ou consumidor está
ativado nesta fase.

O serviço usa DI explícita e aparece em ACULTA Portal → Status como helper
quando o módulo estiver habilitado. A extensão depende de `aculta_portal`,
Drupal LMS e Group, mas não é habilitada automaticamente pelo `aculta_portal`.
Não possui `config/install`, schema, entidade, rota ou hook de instalação.

## F6 — Página de curso e sala (implementação inicial)

O serviço `aculta_lms.course_room_builder` adiciona à página canônica de Group
do tipo `lms_course` uma seção configurável de conteúdo do curso. O índice
respeita o acesso `take course` do curso pai e inclui somente lições publicadas;
links de aula continuam
na rota nativa do LMS, com os slugs portugueses do Portal. Sem autorização para
fazer o curso, a seção fica vazia e não revela os títulos das lições. O módulo
não cria progresso, matrícula, resposta ou armazenamento.

O campo extra pode ser posicionado no Manage Display do bundle `lms_course`.
Render arrays incluem cacheability do curso, lições e resultados de acesso,
variando por usuário e permissões do Group. Em 2026-11-04, smoke somente leitura
no Runtime confirmou que membro matriculado abre aula, atividade e link; não
membro e visitante recebem acesso negado sem revelar o título. O Kernel cobre a
renderização de atividade e link. Firefox ESR visível confirmou home, jornada,
aula e atividade em larguras internas de 500 e 1270 px (sem rolagem horizontal);
o formulário de atividade contém o ID esperado e controles `answer` habilitados.
Nenhuma resposta foi enviada e nenhum aceite de termos foi realizado. Isso não
valida a interface de compra F7.

## F7 — Matrícula em turmas

`EnrollmentFormHooks` valida turmas de memberships criadas pela equipe e bloqueia
a autoinscrição direta do LMS. Na página do curso, `CourseEnrollmentPurchaseForm`
mostra turmas publicadas e o valor do produto Commerce ligado ao curso. Cursos
pagos e gratuitos passam pelo checkout `curso`; o item registra a turma escolhida.
`CourseOrderEnrollmentManager` concede membership Group quando o pedido está
concluído e pago. A role `lms_class-member` tem escopo insider e é aplicada pelo
Group, sem ser gravada como role individual. Smoke no Runtime confirmou que um
pedido gratuito concluído grava a origem em `field_course_order`, concede o
papel global `estudante` e preserva a membership manual do educador. Reembolso
integral revoga somente membership originada pelo pedido; outra compra válida
mantém acesso e matrícula manual permanece independente. Educadores podem
remover memberships manuais, mas não memberships protegidas por pagamento ativo.
Unit testa entitlement de pedido gratuito, pagamento concluído, reembolso parcial
e total e pedido incompleto. `EnrollmentFormHooksTest` também protege contra
remoção manual de matrículas de compra paga/parcialmente reembolsada ou pedido
gratuito concluído e falha fechada quando a origem não pode ser lida; matrícula
manual segue removível sem compra ativa, mas fica protegida se a pessoa comprar
o curso depois da matrícula manual; a elegibilidade da compra é revalidada no
submit e o estorno integral libera a matrícula manual novamente. A confirmação visual
da interface ainda aguarda smoke HTTP. O Runtime não foi conectado ao gateway
para simular uma transação remota. Se o armazenamento retornar um registro de
pagamento inválido, o acesso também é negado sem erro 500.
Na dev.71, Unit LMS/Messenger: 86 testes / 460 assertions; o Kernel confirma
DI do novo verificador de compras (4/40) e a regressão LMS/Messenger passa com
23 testes / 730 assertions, com 95 depreciações upstream.

Na dev.200, Unit `CourseEnrollmentPurchaseFormTest` cobre a turma sem publicação
e o curso sem produto válido (2 testes / 20 assertions); nenhum desses estados
renderiza ação de checkout. Na dev.201, `CourseEnrollmentPurchaseKernelTest`
usa entidades Group e Commerce reais: só oferece turma publicada, apresenta o
preço/ação e submete a compra para confirmar variação e turma no item do
carrinho. MariaDB `aculta_simpletest`: 1 teste / 45 assertions PASS, com 65
depreciações upstream de Core/contrib. Na dev.202, curso despublicado não
oferece formulário de compra; validação e submit repetem a regra e recusam uma
submissão forjada sem criar carrinho. Unit: 3 testes / 24 assertions PASS;
Kernel: 2 testes / 87 assertions PASS, com 65 depreciações upstream de
Core/contrib. O Runtime continua sem produto/turma de negócio para smoke HTTP;
checkout real e sandbox permanecem pendentes.
Na cobertura Kernel adicional de F7, o teste percorre o hook real de atualização de pedido:
pedido gratuito em rascunho não matricula; depois da transição para concluído,
é criada a membership da turma com a origem do pedido. Submissões forjadas de
turma fechada ou de outra turma/curso não criam carrinho. A suíte Kernel focal
passou com 4 testes / 173 assertions; as 67 depreciações vieram de Core/contrib.
O checkout pago, o sandbox e o smoke HTTP seguem pendentes para a etapa de
pagamentos.

Uma pessoa administradora cria o produto e a variação Commerce do curso e associa
esse produto ao Group do curso. A variação possui preço zero ou pago; a página
canônica do produto não tem formulário de compra para evitar matrícula sem turma.

O checkout permite criar conta, não exige e-mail confirmado nessa rota e mantém
a política geral de aceite de termos. O aviso de matrícula por e-mail integra-se
na F13. No Runtime de teste, `aculta_lms` e `lms_classes` estão habilitados e 29
objetos de configuração F7 foram aplicados após backup; nenhuma configuração
ausente foi removida e o curso existente foi preservado. `create_default_class`
permanece desligado. RUNTIME STATUS: configuração aplicada; integração de
pedido gratuito concluído PASS; fluxo de checkout HTTP, pedido pago, estorno/
reembolso e isolamento A/B continuam DEFERRED. Matrícula manual e remoção pela
interface de educador também precisam de smoke HTTP.
Detalhes, limites e caminho de recuperação estão no [changelog](CHANGELOG.md).

## F8 — Avaliações e feedback (transição ADR-042)

Os três scorers objetivos ACULTA cobrem `true_false`, `select` e `fill_blanks`,
compartilhando `ObjectiveAnswerScorer`. A configuração de enunciado e gabarito
ainda precisa ser definida em `ActivityEntity` antes de conectá-los ao fluxo de
submissão. Resposta discursiva permanece com correção manual por estado e nota
ACULTA; o plugin legado de feedback por frase foi removido no corte P5.

Comentários usam Comment API do Core e são ligados à resposta ACULTA. O
histórico arquiva payload JSON e nota anterior em entidade interna protegida;
`score_precise` guarda notas decimais sem alterar o campo inteiro legado.
Acesso é revalidado contra associação e permissão atual do curso. Nenhuma nota
é importada de Webform, H5P ou outro sistema.

A nota mínima continua configurável por aula no LMS. Pontos de atividade são
inteiros nativos do LMS; a nota da aula é a soma ponderada dividida pelo máximo,
arredondada para percentual inteiro pelo LMS. Múltipla seleção dá crédito
proporcional aos acertos, desconta opções erradas e limita o resultado mínimo
a zero. O educador pode ajustar a nota de respostas abertas pela avaliação
nativa do LMS.

No painel de cada turma, **Ver respostas e avaliações** abre a lista de
tentativas dos estudantes atualmente matriculados naquela turma. A lista é
isolada por curso e turma, ordenada da mais recente para a mais antiga e
apresentada em páginas de 50 respostas. O detalhe mostra o conteúdo enviado e,
quando houver um anexo válido, usa a rota privada da própria resposta. Somente
um educador com `grade students` no curso e com a turma relacionada ao curso
pode consultar ou avaliar. A nota deve estar entre 0 e 100; feedback não vazio
é salvo como comentário publicado do Core e mantém a notificação existente.
Rotas em `/painel-administrativo` permanecem restritas ao domínio principal.

**F8 está implementada nesta branch e seus testes automatizados passaram.** A
suíte Unit e Kernel do `aculta_lms` passou (15 testes, 259 assertions), cobrindo
Answer/Comment reais, versões anteriores, reavaliação, exclusão, limpeza por
progresso/conta/curso, acesso de aluno e educador, isolamento entre turmas,
saída em texto e cache de permissões. O teste Unit de seleção do `lms_answer_plugins`
contrib também passou (2 testes, 4 assertions). Gates Portal, imagem, slugs e
lint passaram. Os Kernel tests reportaram 34 depreciações upstream em Group,
LMS, Views e API de entidades do Core. O smoke visual no navegador ainda não
foi executado; a adaptação local segue pequena e isolada em `aculta_lms`. A
barra administrativa usa o ícone próprio de livro aberto do módulo, em lugar
do capelo padrão do LMS contrib.

RUNTIME STATUS: TABELA INSTALADA E SMOKE PARCIAL. Em 2026-10-18, Drush
confirmou `http://cursos.aculta.toca.net.br` e `aculta_runtime`; foi aplicado
somente `aculta_lms_update_10001`, após dump privado com modo `0600`. O teste
sintético confirmou persistência, negação para estudante sem matrícula e acesso
de leitura para educador com `lms_teacher`; edição foi negada e o registro foi
removido ao fim do teste. As contas `teste.estudante` e `teste.educador` ficam
no Runtime; as credenciais estão em `secrets/lms-test-accounts.env` (local,
ignorado pelo Git, modo `0600`). A conta estudante só recebe o papel global ao
entrar em uma turma. Smoke visual pela interface ainda está pendente. Comentários, isolamento entre turmas e ciclo de vida foram exercitados nos Kernel tests no banco dedicado; não são mutações do Runtime.

Os testes de plugins F8 devem ser executados a partir de `web/core` com o
PHPUnit do Composer; os Kernel tests usam exclusivamente o MariaDB dedicado
descrito em `docs/operations/BANCO-DE-TESTE.md`.

## F11 — Certificados (implementação em andamento)

`CourseCertificateManager` emite um retrato imutável somente para status LMS
finalizado e aprovado. A emissão exige permissão `grade students` no curso ou
`administer lms`; o estudante não precisa manter membership depois de ter
concluído o curso. Reemissão revoga a emissão anterior e cria uma nova versão
vinculada; reemissão revoga a anterior na mesma transação. Educadores também
podem revogar com motivo e o histórico permanece. Conta excluída não remove certificado.

O campo numérico `field_course_hours` guarda a carga horária declarada pela
equipe no curso. O registro armazena nome de exibição, curso, horas, conclusão,
emissão, versão de modelo, estado e código aleatório opaco. A página pública de
verificação mostra somente os quatro dados públicos aprovados (nome, curso,
horas e estado); o Kernel confirma que e-mail e motivo interno de revogação não
aparecem. O código também habilita o PDF
do certificado. Entity Print 2.19.0/Dompdf gera o PDF a partir do snapshot já
validado, com escaping e carregamento remoto/PHP embutido desativados. A página
de verificação exibe QR gerado pelo serviço `aculta_qrcode`; o SVG é servido
sem gravar arquivo e o PDF incorpora PNG gerado em memória. A URL absoluta é
resolvida pelo `DomainPurposeManager` no purpose `main`; o LMS não mantém geração
QR própria.

Config Sync declara o campo do curso. O storage da entidade e o campo com mínimo
de uma hora foram aplicados separadamente no Runtime de teste após revisão do
diff; a configuração ativa do formulário foi preservada e não há valor no curso
existente. O smoke anônimo de código inexistente respondeu 404 após reconstruir
o container. Em 2026-10-21, um fixture temporário real de User, Group e
CourseStatus aprovado foi criado via APIs Drupal. O manager emitiu o snapshot;
o controlador real apresentou os quatro dados públicos, gerou QR SVG e PDF
válidos com o `aculta_qrcode` e Entity Print/Dompdf instalados. Em seguida, um
segundo fixture repetiu a emissão com listeners de conclusão/certificado
temporariamente removidos e fez GET HTTP anônimo às três rotas: página pública,
QR SVG (`image/svg+xml`) e PDF (`application/pdf`, assinatura `%PDF`) responderam
200. Na primeira execução, salvar o CourseStatus temporário acionou o evento
normal de conclusão; a mensagem, a chave de deduplicação e o agregado estatístico
dessa fixture foram identificados e removidos. Em ambas as execuções, User,
Group, status e certificado foram excluídos por APIs Drupal; a fila de e-mail
terminou vazia e os totais voltaram ao estado anterior. RUNTIME STATUS: parcial;
emissão/revogação pelo formulário, revisão visual do QR/PDF e aceite do modelo
permanecem pendentes.

## F12 — Painéis de aprendizagem (implementação inicial)

`/minha-aprendizagem` apresenta apenas os cursos autorizados da própria conta,
progresso em aulas, avaliações pendentes e certificados emitidos. A navegação da
conta recebe a seção por hook do Portal, sem alterar o shell ou duplicar a rota
de cursos.

O painel `/painel-administrativo/lms/curso/{course}/turma/{group}/painel` exige a permissão
contextual `grade students` no curso associado. Exibe apenas contagens agregadas
da turma (matrículas, início, andamento, avaliação pendente, aprovação e
reprovação), sem nomes, respostas ou texto de feedback. Educadores e
administradores não são contados como estudantes, e status de pessoas fora da
turma são excluídos. Cache depende dos memberships e contas consultados para
invalidar alterações de participação/roles. Group/LMS continuam fontes de
verdade e as páginas não usam cache compartilhado.

Unit dos painéis e do PDF: 4 testes/18 assertions (escopo próprio, menu
autenticado, PDF indisponível e turma órfã); em conjunto com os Kernel de
instalação/certificados, 6 testes/76 assertions PASS. Unit total: 20
testes/106 assertions.
Kernel de Group/LMS comprova contagens para dois estudantes, exclusão de
educador/admin e outsider e ausência de dados identificáveis (1 teste/38
assertions). Revisão visual/browser, acesso do dashboard do estudante com
fixtures completas e smoke no Runtime continuam pendentes; RUNTIME STATUS:
DEFERRED. Em `dev.62`, o Kernel integra o painel estudantil a certificados de
duas contas e confirma que somente o certificado da pessoa autenticada aparece,
com a tag de cache dessa entidade. Focal: 1 teste/41 assertions.

Suítes do `aculta_lms` revalidadas em 2026-10-21: Unit 51 testes/281
assertions; Kernel MariaDB 20 testes/634 assertions PASS. O Kernel registrou 90
depreciações de Core/contrib e uma de `webform_score`; a Unit registrou 8
depreciações do PHPUnit.

Regressão combinada do LMS com `aculta_messenger`: Unit 84 testes/445
assertions; Kernel MariaDB 23 testes/719 assertions PASS. No total, houve 8
depreciações PHPUnit e 95 de Core/contrib, sem falhas.

O Kernel `LearningDashboardKernelTest` reproduz o contrato de acesso do LMS:
`take course` permite o currículo da pessoa matriculada, embora `view` genérico
da Lesson seja negado. Aula não publicada fica fora do total e do progresso.
Revisão visual e smoke Runtime seguem pendentes.

`ClassActivityDeadlineFormTest`: 2 testes/8 assertions PASS para fuso da conta e
fallback UTC. `LearningDashboardControllerTest`: 1/4 assertions PASS. Unit
total: 39 testes/185 assertions PASS; 5 depreciações do
PHPUnit.
`CertificateLifecycleTest`: 3 testes/97 assertions PASS, cobrindo o snapshot
público, cache e endpoint QR SVG; 38 depreciações upstream.
`ClassDashboardKernelTest`: 1 teste/43 assertions PASS, incluindo a política de
cache do painel educador.
O download privado também falha fechado durante a exclusão assíncrona de um
LessonStatus, quando a resposta ainda existe, mas já perdeu o contexto de curso.
Kernel `AttemptArchiveLifecycleTest`: 9 testes/363 assertions PASS; caso
dedicado 1/39 assertions. Naquela rodada, a suíte Kernel totalizou 19
testes/594 assertions, com 90 depreciações de Core/contrib.

Validação desta entrega parcial: Kernel isolado 6 testes/76 assertions; Unit
16/88; gates Drupal 11+ (1007 checks), índice de status, slugs e imagens PASS;
Composer audit sem advisories. O Entity Print 2.19.0 gera três deprecações
Drupal 11.2/11.3 em código upstream; nenhuma alteração foi feita no contrib.

## F13 — Avisos e prazos por turma (implementação funcional; revisão visual pendente)

Educadores com `grade students` no curso e administradores podem definir,
alterar e remover o prazo de uma atividade em `/painel-administrativo/lms/curso/{course}/turma/{group}/atividade/{activity}/prazo`.
O prazo fica na relação Group turma↔atividade (`field_activity_due_at`), então
uma atividade reutilizada pode ter prazos diferentes em cada turma. A tela usa
o fuso da conta do educador; a área de atividade do estudante e o aviso usam o
fuso da conta que consulta.

Definir ou alterar o prazo gera aviso no painel e no resumo diário opt-in por
meio do contrato público do `aculta_messenger`. O lembrete é enfileirado na
janela de 24 horas antes do prazo. A mensagem não envia e-mail transacional e é
revalidada contra publicação, curso e membership atual.
O módulo `lms_classes` herda `take course` no curso pai a partir da matrícula na
turma; os leitores de prazo e avisos conferem essa permissão no curso e exigem
membership ativa na turma.
O responsável confirmou registrar o prazo por atividade na turma. Unit
LMS/adapter: 47 testes / 210 assertions PASS; Kernels de instalação/Group/Message:
6 testes / 85 assertions PASS. Smoke temporário no Runtime confirmou persistência
do aviso e lembrete, integração com aviso pessoal e worker; as fixtures e filas
foram removidas. Não houve envio de e-mail nem espera pelo Cron agendado. A
revisão visual autenticada permanece DEFERRED até o aceite do acordo geral pelo
responsável.
`CourseContentEventHooksTest` também confirma que editar uma atividade já
publicada sem liberar conteúdo novo não gera aviso: F13 notifica transições e
mudanças acionáveis, não edição editorial. Focal 4 testes/11 assertions; Unit
LMS/Messenger 79/407 PASS (8 depreciações PHPUnit).

## Testes

- `tests/src/Unit/CourseCompletionPublisherTest.php`: publicação apenas de
  status passado e finalizado; falha e progresso não publicam evento.
- `tests/src/Unit/AttemptArchiveManagerTest.php`: preserva dados e notas
  anteriores antes de edição/reavaliação/reinício e ignora salvamento sem
  alteração.
- `tests/src/Kernel/AcultaLmsInstallTest.php`: instalação isolada, registro DI, persistência, bloqueio de edição e limpeza por conta.
- `tests/src/Unit/LearningDashboardManagerTest.php`: painel pessoal isolado por usuário e negação segura para turma sem curso pai.
- `tests/src/Unit/ClassActivityDeadlineFormTest.php`: formulário interpreta o prazo no fuso horário da conta do educador e usa UTC como fallback.
- `tests/src/Kernel/AttemptArchiveLifecycleTest.php`: ciclo real de Answer/Comment, comentários publicados, acesso e cache por turma/permissão, saída em texto e limpeza por progresso/conta/curso.
- `web/modules/contrib/lms/modules/lms_answer_plugins/tests/src/Unit/SelectBaseAnsweringFormTest.php`: teste upstream do formulário de seleção.

Execute os testes a partir de `web/core` com PHPUnit do Composer. O Kernel test usa o banco MariaDB
de teste dedicado (nunca o Runtime, nunca SQLite). Configuração, procedimento e resultados estão em
`docs/operations/BANCO-DE-TESTE.md`; a senha fica em `secrets/simpletest-db.env` e não entra no repositório:

```sh
cd web/core
set -a && . ../../secrets/simpletest-db.env && set +a
mkdir -p /tmp/aculta-lms-test-output
BROWSERTEST_OUTPUT_DIRECTORY=/tmp/aculta-lms-test-output \
../../vendor/bin/phpunit -c phpunit.xml.dist \
  ../modules/custom/aculta_portal/modules/aculta_lms/tests/src/Kernel/AcultaLmsInstallTest.php
```

Runtime/Homelab não faz parte do teste.

## Documentação

- [Roadmap do LMS e PDF global](../../../../../../docs/modules/ACULTA-LMS-ROADMAP.md)
- [Contratos de integração](../../../../../../docs/portal/LMS-INTEGRATION-CONTRACTS.md)
- [Matriz de segurança e arquivos](../../../../../../docs/portal/SECURITY-ACCESS-FILES-MATRIX.md)
- [Changelog](CHANGELOG.md)

## Registro histórico: identidade estudantil anterior (G420-A)

[Contrato e inventário histórico da migração](../../../../../../docs/architecture/GANJALAB420-TO-ACULTA-STUDIO-MIGRATION.md). O trecho a seguir registra a fase anterior ao ADR-038. O nome vigente é **ACULTA Studio Ganjalab**, ID `ganjalab`, com rota canônica `/ganjalab` no purpose `courses`; `/studio/ganjalab` não possui alias ou redirect e retorna 404. O controller de experiência é `aculta_studio.ganjalab_controller`. LMS continua dono de dados, progresso, matrículas, certificados, autorização e cache.

## Ganjalab em Cursos - migração ADR-038

Estado ADR-038: `/ganjalab` em purpose `courses` está implementada; a antiga `/studio/ganjalab` foi removida sem alias. LMS mantém ownership de matrículas, progresso, dados, certificados e permissões. O bloqueio de purpose incorreto foi incluído na matriz HTTP de 275 requisições.


## Fronteira de apresentação Ganjalab (2026-11-01)

`aculta_lms` não declara dependência de `aculta_studio` e não registra serviços de apresentação do Studio. A experiência da jornada e da sala passou para CourseJourneyComposer e CourseJourneyPresentationHooks em aculta_studio, consumindo o contrato CourseJourneyReaderInterface. CourseRoomBuilder fica no LMS para matrícula e roster, sem compor a jornada.

O `LearningDashboardManager` continua lendo entidades Group/Drupal LMS e certificados, mas publica `StudentLearningSummaryReaderInterface` com DTOs neutros. O `AccountCoursesReader` implementa `AccountCoursesReaderInterface` e fornece cursos autorizados para a Conta; o Portal recebe esse provider como opcional. `LmsFriendlySlugs`, o conversor, o route processor e o subscriber da rota foram movidos para este módulo, preservando os IDs de serviço `aculta_portal.lms_friendly_*` e os tipos de parâmetro existentes. O `aculta_portal` não declara mais `lms:lms`.

A composição de render arrays do estudante e a autoria do curso ficam em módulos
owners diferentes: `aculta_studio` apresenta o workspace e suas rotas; o
`aculta_lms` lê e grava as entidades ACULTA e revalida permissões. Rotas do
Ganjalab ficam em `aculta_studio.routing.yml` com IDs `aculta_lms.*` por
compatibilidade. A rota nativa de atividade ainda é integração temporária. A
dependência dos módulos Drupal LMS contrib foi removida do Composer, metadata e
código customizado; Group continua como infraestrutura deliberada.

A conclusão neutra remove o import direto do evento LMS nos consumidores Game e
Messenger. O provider ainda lê `CourseStatusInterface` e permanece dependente
do Drupal LMS contrib; esta mudança não migra dados nem remove a dependência do
módulo LMS.
