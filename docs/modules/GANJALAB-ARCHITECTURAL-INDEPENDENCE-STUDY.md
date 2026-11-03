# Estudo de independência arquitetural do Ganjalab

**Data:** 2026-11-01  
**Branch analisada:** `dev`  
**Natureza:** estudo e recomendação; não autoriza migração de dados nem mudança do runtime  
**Escopo:** independência do Drupal LMS contrib. O ACULTA permanece Drupal nesta proposta.

## 1. Resumo executivo

Recomenda-se tornar o Ganjalab independente do módulo contrib `drupal/lms` por meio de uma camada de domínio ACULTA e contratos de aplicação estáveis, implementados inicialmente dentro do Drupal. A independência deve ser alcançada por substituição gradual do backend, não por uma reescrita imediata nem pela introdução prematura de microserviços.

A proposta mantém como serviços compartilhados do ACULTA/Drupal aquilo que já tem ownership transversal: identidade e permissões, Group, Media/File e política de arquivos, Commerce, Messenger, viewer PDF, perfis e infraestrutura de publicação. O domínio educacional próprio assume autoria, estrutura do conteúdo, atividades, tentativas, respostas, progresso e avaliação. Certificados devem ser revisados como parte desse domínio, aproveitando a implementação atual se ela puder deixar de depender de entidades/status do Drupal LMS.

A separação é arquitetural: o Ganjalab não deve importar classes, entidades, rotas, plugins ou tabelas do Drupal LMS em controllers, componentes de interface ou regras de negócio. Um adapter temporário pode continuar traduzindo para o LMS contrib enquanto o domínio próprio é construído. Nenhuma segunda fonte de verdade deve ser criada durante essa etapa.

**Recomendação de decisão:** aprovar o desenho e a auditoria de dependências; não iniciar a migração de dados antes de fechar o inventário, os contratos, o plano de coexistência e a matriz de rollback.

## 2. O que “independente” significa aqui

Há três níveis possíveis. Eles não devem ser confundidos:

| Nível | Significado | Recomendação |
| --- | --- | --- |
| Experiência independente | A autoria e o Ganjalab têm interface e fluxos próprios, mas podem chamar serviços LMS existentes. | Fazer já, mantendo a UI desacoplada. |
| Domínio independente do Drupal LMS | Dados e regras pedagógicas têm modelo e APIs ACULTA; o contrib pode ser removido quando os dados forem migrados. | Objetivo recomendado. |
| Plataforma independente de Drupal | LMS roda como produto/serviço fora da aplicação Drupal e precisa integrar identidade, arquivos, Commerce e eventos por APIs. | Não recomendado agora; custo operacional e de integração alto sem requisito que o justifique. |

Este estudo usa “independência do LMS” no segundo sentido. Não significa reimplementar autenticação, pagamentos, Group, mídia, mensageria ou o portal inteiro.

## 3. Estado constatado na branch dev

A documentação normativa consultada atribui hoje:

- **Group**: cursos (bundle `lms_course`), memberships, papéis e turmas;
- **Drupal LMS**: lições, atividades, plugins de resposta, tentativas, respostas, status e notas;
- **Media/File**: arquivos e documentos, com políticas compartilhadas de acesso;
- **Commerce**: pedido/preço e proveniência de memberships pagas;
- **aculta_messenger**: persistência e preferências de avisos; LMS publica eventos/elegibilidade;
- **aculta_pdf_viewer**: visualização compartilhada de PDF;
- **aculta_user/aculta_game**: identidade/perfil e conquistas;
- **aculta_lms**: integração educacional, apresentação e regras ACULTA, sem criar matrícula, resposta, nota ou progresso duplicados.

A mesma documentação registra integração entre memberships e compra, regras de revogação/reconciliação, entregas de arquivos privados, H5P, fóruns, certificados e painéis. Também registra lacunas de validação Runtime/visual e dependências em fases distintas. O Ganjalab student dashboard conhece cursos, contagem de aulas, percentual, avaliações pendentes e certificados, mas ainda não recebe fonte canônica para última aula visitada/próxima aula permitida.

Referências internas:

- [Inventário LMS e Group](LMS-GROUP.md)
- [Roadmap do ACULTA LMS](ACULTA-LMS-ROADMAP.md)
- [Contratos de integração LMS](../portal/LMS-INTEGRATION-CONTRACTS.md)
- [Plano de experiência Ganjalab](../portal/GANJALAB-EXPERIENCE-IMPLEMENTATION-PLAN.md)
- [Status de desenvolvimento](../operations/status/aculta_lms.yml)

### 3.1 Implicação importante

Remover apenas a dependência Composer `drupal/lms` não torna o Ganjalab independente. Hoje, partes relevantes do domínio, das relações e dos dados existem em entidades e contratos do LMS contrib. A remoção exige substituir e migrar essas responsabilidades sem quebrar memberships, pagamentos, acesso privado, histórico, feedback, certificados, fórum e avisos.

O Group pode continuar como infraestrutura social/organizacional, mas é preciso decidir expressamente se o curso seguirá sendo um Group `lms_course` ou migrará para uma entidade de curso ACULTA com uma relação Group. Não se deve manter duas representações editáveis de curso sem uma regra de ownership.

## 4. Arquitetura-alvo proposta

### 4.1 Camadas

1. **Experiência Ganjalab**
   - Autoria, catálogo, dashboard, jornada e sala.
   - Controllers, formulários, SDC/Twig e presenters consomem APIs ACULTA.
   - Não consultam diretamente classes do Drupal LMS.

2. **Aplicação/contratos ACULTA**
   - Casos de uso como criar curso, organizar módulo, publicar aula, inscrever pessoa, registrar tentativa, avaliar entrega e calcular progresso.
   - Interfaces explícitas para catálogo, currículo, atividade, avaliação, progresso, matrícula, certificado e entrega de arquivos.
   - Policies para autorização, transições de estado e publicação.
   - Eventos educacionais versionados para Messenger, auditoria e integrações.

3. **Domínio educacional ACULTA**
   - Modelo canônico de curso, currículo, atividade, tentativa, resposta, avaliação e progresso.
   - Implementado como módulos Drupal próprios, com entidades/configuration entities e storage Drupal onde apropriado.
   - Regras independentes de APIs concretas do contrib.

4. **Adapters de infraestrutura**
   - Group e permissões;
   - Commerce e memberships com origem financeira;
   - Media/File, upload privado, política de exclusão e viewer PDF;
   - Messenger e mail;
   - H5P e fórum;
   - certificados/PDF;
   - adapter temporário do Drupal LMS durante a transição.

### 4.2 Contratos conceituais

Os contratos devem expressar necessidades ACULTA e não imitar nomes de classes do LMS upstream:

- `CourseRepository` / `CourseAccessPolicy`
- `CurriculumRepository` para módulos e aulas ordenados
- `LearningActivityRepository` para atividades e configurações pedagógicas
- `EnrollmentProvider` para elegibilidade e contexto Group/turma
- `AttemptRepository` e `AssessmentService`
- `ProgressQuery` e `LearningResumeProvider`
- `LearningFileAccess` para entrega privada e revogação
- `CertificateEligibility` e `CertificateIssuer`
- `LearningEventPublisher` para eventos públicos versionados

Esses nomes são contratos de desenho, não nomes já existentes no repositório. A auditoria local deve primeiro procurar interfaces equivalentes e reutilizá-las.

Todos os resultados de leitura precisam carregar cacheability Drupal: contexts, tags e max-age apropriados. Toda operação de escrita revalida usuário, estado, vínculo curso–turma–atividade e permissões no servidor. IDs externos não substituem Entity Access.

### 4.3 Modelo de domínio inicial

Modelo lógico sugerido, sujeito à auditoria do modelo atual e de requisitos de versionamento:

- **Curso**: identidade pedagógica, metadados, estado editorial, autoria, idioma, objetivos e política de conclusão.
- **Módulo**: parte ordenável do percurso, estado de publicação e regras opcionais de disponibilidade.
- **Aula**: página/unidade de aprendizagem ordenável, composta por blocos ou referências de conteúdo.
- **Atividade**: formato e configuração da interação; exemplo: pergunta objetiva, reflexão, entrega, discussão ou H5P.
- **Versão publicada**: snapshot imutável do conteúdo visível às estudantes; edição cria revisão nova.
- **Tentativa**: estudante, atividade/versão, timestamps, estado e respostas; submissões anteriores não são sobrescritas silenciosamente.
- **Avaliação/feedback**: nota quando aplicável, critérios, comentário, avaliador e estado.
- **Progresso**: projeção derivada das tentativas e política de conclusão; guardar eventos/fatos necessários à auditoria, evitar contadores paralelos incoerentes.
- **Membership**: permanece na fonte Group durante esta proposta; compra/origem permanece em Commerce conforme regras atuais.
- **Arquivo**: referência a Media/File gerenciada pelas políticas compartilhadas, sem duplicar bytes ou URL pública.
- **Certificado**: snapshot verificável ligado à regra e à versão/conclusão que o justificou.

A decisão entre campos/entidades Drupal, Paragraphs/SDC e JSON estruturado precisa de protótipo e análise de query, revisões, cache, migração, acessibilidade editorial e compatibilidade com o editor. Não se deve escolher JSON ou Paragraphs por conveniência antes de avaliar os blocos e revisões exigidos.

## 5. Ownership e fontes de verdade propostas

| Dado/capacidade | Fonte-alvo recomendada | Observação |
| --- | --- | --- |
| Curso e currículo | Domínio ACULTA | Decidir relação com Group. Evitar duplicar metadados editáveis entre Group e entidade. |
| Turma e membership | Group, inicialmente | Preserva grants, acesso contextual e integração com pagamentos existentes. |
| Pedido, entitlement e origem financeira | Commerce | Não replicar status de pagamento em LMS. |
| Atividade, tentativa, resposta e feedback | Domínio ACULTA | Migrar histórico e manter autoria/auditoria. |
| Progresso | Serviço ACULTA derivado de fatos canônicos | Não confiar em percentual armazenado sem reconciliar as atividades e versões. |
| Arquivos e autorização de download | Media/File + política central | Revogar acesso após mudança de membership; preservar arquivos compartilhados. |
| H5P | Adapter ACULTA H5P | Integrar tentativas e conclusão aos contratos do domínio; bibliotecas e pacote permanecem isolados e validados. |
| Fórum | aculta_forum + adapter educacional | Relação contextual curso/turma/atividade, com revalidação Group em cada leitura/operação. |
| Avisos | Messenger | Publicar eventos mínimos e versionados; não introduzir tabela de mensagens no LMS. |
| PDF | aculta_pdf_viewer | Serviço comum, não uma implementação própria do Ganjalab. |
| Certificados | Domínio/serviço ACULTA com PDF compartilhado | Validar se a entidade existente depende de status/classes do LMS antes de promovê-la. |

## 6. Estratégia de transição

### Fase A - Baseline e mapa de dependências

Inventariar no código e configuração:

- imports, type hints, service IDs e chamadas diretas para `Drupal\lms`;
- entidades/bundles/fields LMS usados por módulo, tema, Views, rotas, plugins, scripts e testes;
- configuração ativa/versionada e conteúdo persistido por tipo;
- hooks de ciclo de vida, cron, filas, access handlers, caches e rotas;
- vínculos entre respostas/arquivos privados, feedback, certificados, fórum e notificações;
- como atualizações upstream e desinstalação se comportam.

Saída: grafo de dependências com consumidor, API usada, dado lido/escrito, risco, cobertura e owner ACULTA proposto. Confirmar versão e estado no lockfile e Runtime antes de estimar migração.

### Fase B - Contratos e seam arquitetural

> **Estado em 2026-11-01 — fatia B1 entregue em código.** O inventário está em [LMS-CONTRIB-DEPENDENCY-INVENTORY.md](LMS-CONTRIB-DEPENDENCY-INVENTORY.md). `CourseJourneyReaderInterface`, `CourseJourney`, `CourseJourneyLesson` e, na B2, `StudentLearningSummaryReaderInterface` com os DTOs de resumo vivem em `aculta_learning_contracts\Learning`, módulo sem dependência de `lms:lms`, `aculta_portal`, `aculta_studio` ou `aculta_lms`; a revisão de 2026-11-01 tirou-os do `aculta_studio`/`aculta_portal` para não fazer o adaptador do LMS depender do namespace do consumidor nem do Portal que ainda exige o contrib. Na B2, o `LearnerDashboardComposer` vive no Studio e o LMS implementa o leitor de resumo; o LMS não declara `aculta_studio`. `LearningDashboardManager` implementa os contratos como adaptador LMS/Group. `CourseJourneyComposer` deixou de importar `CourseInterface`; `CourseRoomBuilder` (matrícula/roster no LMS) e `CourseJourneyPresentationHooks` (jornada/sala no Studio) usam o contrato. Testes Unit provam o builder com leitor falso e o composer sem mock do contrib. B2 está implementado. A B3-A também foi concluída em código: `aculta_portal` não declara mais `lms:lms` nem importa classes do contrib; a retirada do pacote e dos dados do `aculta_lms` permanece pendente. Nenhum dado foi migrado e a fonte de verdade continua no LMS.

- Definir interfaces de aplicação para leitura e escrita educacional.
- Adaptar dashboard, catálogo e jornada para consultar os contratos, sem mudar a fonte de dados.
- Criar testes de contrato usando adapter Drupal LMS e fixtures reais.
- Garantir cacheability e access em cada query.
- Remover do frontend a dependência de nomes/formatos do contrib.
- Documentar eventos e transições de estado.

Aceite: a camada de apresentação pode ser testada contra implementação fake de contrato sem carregar classes do LMS. Isso demonstra desacoplamento de UI, não migração concluída.

### Fase C - Domínio próprio de autoria, sem tráfego de estudantes

- Implementar modelo canônico de curso/currículo e versões publicadas.
- Construir editor mobile-first e prévia de estudante consumindo contratos.
- Manter criação publicada no LMS legado ou limitar a dados de teste até fechar conteúdo e acesso.
- Criar ferramentas de validação de curso antes de publicar.

Aceite: autoria pode salvar e revisar rascunho, publicar snapshot, ordenar módulos/aulas e preservar permissões. Não deve criar atividade duplicada consumida por estudantes.

### Fase D - Migração piloto e leitura reconciliada

- Escolher curso piloto sem eliminar registros antigos.
- Migrar curso/currículo e guardar mapa imutável de IDs antigos → IDs novos.
- Comparar contagem, ordem, revisão, publicação, relações, direitos, tentativas, notas, progresso e certificados.
- Fazer relatório de discrepâncias e resolução editorial.
- Para status e progresso, recalcular contra regras explícitas e comparar com valores antigos.
- Testar acesso a entregas antes e depois de revogação de membership.

Aceite: relatório reexecutável sem duplicar registros; nenhum arquivo privado exposto; acesso e histórico equivalentes conforme política aprovada.

### Fase E - Coexistência controlada e piloto de escrita

Evitar dual-write bidirecional. Preferir migração por curso:

1. LMS contrib continua autoridade do curso antigo.
2. Curso piloto passa para fonte ACULTA após reconciliação.
3. Um registro de ownership identifica backend e versão do contrato para cada curso.
4. Escritas são roteadas para uma única fonte.
5. Leitura de compatibilidade fica temporária, monitorada e removida após janela definida.
6. Rollback é troca de ownership para o legado somente antes de haver novas tentativas incompatíveis; depois disso requer export/replay auditável, não apontar cegamente para dados obsoletos.

Aceite: um curso nunca recebe escrita simultânea nas duas fontes; eventos e links continuam funcionando; rollback foi ensaiado em cópia isolada.

### Fase F - Migração de avaliações e retirada do adapter LMS

- Migrar tentativas, respostas, comentários, notas, arquivos, estados e timestamps com autoria.
- Migrar IDs de atividades e vínculos H5P/fórum.
- Revalidar gradebook/progresso/certificados e avisos.
- Confirmar que não há consumidores ou conteúdo dependente das entidades upstream.
- Retirar por etapas plugins, configurações, serviços e dependência Composer.
- Não desinstalar módulos nem apagar tabelas até comprovar retenção, backups, export e recuperação.

Aceite: testes de regressão de isolamento entre cursos/turmas/usuários; histórico preservado; checklist de desinstalação e restore concluído; monitoramento sem chamadas ao adapter legado.

## 7. Estratégia de coexistência e rollback

- Não usar dual-write como estratégia de segurança: falha parcial cria divergência impossível de auditar.
- Preferir corte por curso/tenant lógico, com ownership explícito e writes em apenas um backend.
- Manter export imutável pré-migração, mapa de IDs e logs da execução.
- Não modificar nem excluir registros LMS originais durante a janela de verificação.
- Definir antes do piloto como tratar atividade já iniciada, resposta submetida sem avaliação, prazo vencido, certificado emitido e membership paga.
- Se houver nova atividade após o corte, rollback exige migrar esses fatos de volta ou manter o curso no novo backend; não basta trocar uma configuração.
- Backups precisam cobrir banco, arquivos privados, config e versões de código compatíveis.

## 8. Riscos e controles

| Risco | Controle necessário |
| --- | --- |
| Perda de tentativas, feedback ou notas | Migração com reconciliação por registro e amostras manuais; preservar timestamps/autoria. |
| Estudante acessar outro curso/turma | Policies server-side, Entity Access/Group atual em toda leitura, testes A/B e invalidadores de cache. |
| URLs de arquivos privados vazarem | Usar entrega privada central, revalidar membership no download e provar revogação. |
| Progresso/certificado divergir | Regras versionadas de conclusão e snapshots de emissão; comparar dados antigos/novos antes do corte. |
| Dual-write e estados inconsistentes | Ownership por curso, uma fonte de escrita e eventos idempotentes. |
| Dependência oculta em Views, config ou tema | Varredura de configuração exportada, plugins, render arrays, scripts, cron e campos. |
| H5P migrado sem tentativas compatíveis | Inventariar tipos/bibliotecas, versões, estado de tentativa e limites da API; migrar por tipo. |
| Autoria lançar conteúdo incompleto | Rascunho, revisão, prévia real, checklist editorial, versionamento e publicação explícita. |
| Custo maior que benefício | Gates de decisão por fase e estimativa baseada no volume real de conteúdo/tentativas. |

## 9. Opções consideradas

| Opção | Benefício | Custo/risco | Avaliação |
| --- | --- | --- | --- |
| Manter o Drupal LMS e apenas reformar a interface | Menor mudança de dados; chega antes à nova experiência. | Permanecem limites de domínio/API e dependência upstream. | Boa ponte e alternativa caso a auditoria prove que o LMS não bloqueia os requisitos. |
| Adapter ACULTA com backend legado substituível | Desacopla autoria/UI imediatamente; preserva dados; cria caminho testável. | Exige disciplina de contratos e manutenção temporária de adapter. | **Recomendada como primeiro investimento.** |
| Domínio educacional ACULTA próprio dentro do Drupal | Controle de autoria, dados e regras; continua reutilizando Group, Commerce e File. | Custo alto de migração e responsabilidade por tentativas, grades, revisão, acessos e suporte. | **Destino recomendado, condicionado aos gates e a um piloto.** |
| Serviço LMS fora do Drupal | Escala independente e fronteira técnica forte. | Autenticação, API, sincronização, disponibilidade, segurança, observabilidade e operação adicionais. | Adiar até haver requisito de escala/equipe/integradores que justifique. |

## 10. Critérios para decidir se a migração completa compensa

Prosseguir para domínio próprio somente após responder:

1. Quais limitações concretas do Drupal LMS impedem os fluxos de autoria priorizados?
2. A interface ACULTA consegue entregar autoria mobile-first por meio de adapters sem customizações profundas do LMS?
3. Qual volume de cursos, atividades, tentativas, respostas e arquivos precisa ser migrado?
4. Quais tipos de resposta e H5P estão realmente em uso?
5. Qual é a política de retenção de tentativas, notas, comentários e certificados?
6. Como serão tratados cursos pagos, reembolsos e memberships de múltiplas origens?
7. Há capacidade de manter um motor próprio de avaliação, versionamento, auditoria e suporte?
8. Quais padrões externos (LTI, SCORM, xAPI) são requisitos reais para parceiros ou conteúdo existente?
9. Qual a meta para manutenção Drupal/upstream versus manutenção do domínio próprio?

## 11. Recomendação final

Construir o Ganjalab para **ser independente do Drupal LMS, mas não da infraestrutura Drupal/ACULTA**. Primeiro, criar e usar contratos ACULTA; depois, autoria e curso/currículo próprios; por último, migrar avaliações, tentativas e progresso por curso, com ownership único e rollback ensaiado.

A independência não é remover uma dependência do Composer; é garantir que o Ganjalab tenha modelo pedagógico, regras e contratos próprios, e que integrações transversais continuem sob seus donos atuais. O editor de autoria deve ser consumidor desses contratos, não proprietário de uma segunda cópia de curso, progresso ou matrícula.

### Referências upstream para análise durante a implementação

- [Moodle - conclusão de atividades](https://docs.moodle.org/502/en/Using_Activity_completion)
- [Moodle - restrição de acesso](https://docs.moodle.org/502/en/Restrict_access)
- [Open edX - organização e edição do curso](https://docs.openedx.org/en/latest/educators/how-tos/set_up_course/manage_course_outline.html)
- [Brightspace - ferramenta Course Builder](https://community.d2l.com/brightspace/kb/articles/34715-about-course-builder)
- [Brightspace - organização de conteúdo](https://community.d2l.com/brightspace/kb/articles/1689-about-content)
