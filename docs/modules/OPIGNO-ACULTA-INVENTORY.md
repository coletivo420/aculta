# Inventário do Opigno LMS Community e aderência ao ACULTA

**Data:** 2026-11-01  
**Escopo:** inventário documental do Opigno LMS Community 3.x e triagem de integração ao ACULTA.  
**Método/limite:** consulta às páginas oficiais do Drupal.org e ao manual Opigno 3.x. Este documento não afirma ter feito instalação, auditoria linha a linha do código, Composer dry-run ou teste de segurança local. O manual público consultado contém páginas atualizadas em 2021; recursos e configuração devem ser confirmados no código 3.2.7 antes de qualquer port.

## 1. Resumo executivo

Opigno oferece um conjunto amplo de conceitos úteis para Ganjalab: trilhas compostas visualmente, módulos reutilizáveis, atividades, tentativa/nota, condições de avanço, sessões presenciais, reuniões virtuais, certificados, catálogo, calendário, gamificação, H5P, SCORM e integração xAPI/LRS.

O projeto comunitário não é uma fundação de baixo risco para o ACULTA hoje. O Drupal.org marca Opigno Community como sem suporte e sem desenvolvimento dos mantenedores; informa encerramento das releases comunitárias. A release estável listada é 3.2.7, baseada em Drupal 10, enquanto tarefas/issues de compatibilidade com Drupal 11 continuam sendo discutidas. Há também issues recentes de instalação/dependências e advisories críticos históricos nos módulos Opigno, o que exige auditoria antes de reutilizar qualquer código.

**Recomendação:** usar Opigno como referência funcional e fonte potencial de port seletivo. Não instalar a distribuição completa nem iniciar um fork integral sem uma prova técnica. Fazer inventário de código/Composer em checkout isolado, testar somente os módulos candidatos e portar para namespaces e contratos ACULTA, preservando Group, Commerce, Media/File, Messenger, fórum e PDF como donos atuais de seus domínios.

## 2. Estado e manutenção

| Item | Evidência pública | Consequência para o ACULTA |
| --- | --- | --- |
| Community releases encerradas | A página de projeto diz que as releases comunitárias terminaram; suporte e atualizações de segurança são oferecidos pelo Success Plan, e issues no queue não são mais tratadas. | O ACULTA teria de assumir compatibilidade, correções e resposta a vulnerabilidades. |
| Código sob GPL | A página do projeto informa que releases existentes continuam disponíveis sob GPL. | Reuso/port pode ser possível conforme licença; verificar avisos por arquivo, dependências e obrigações de distribuição com revisão apropriada. |
| Marca Opigno | Opigno é marca registrada e o projeto não está aberto à transferência de ownership. | Um fork deve ter nome, namespace, documentação e identidade ACULTA próprios; não se apresentar como Opigno. |
| Base técnica da release | 3.2.7 é baseada em Drupal 10. Projetos relacionados frequentemente declaram compatibilidade ^9 || ^10. | Drupal 11 é gate obrigatório; não presumir compatibilidade por atualização automática de metadados. |
| Segurança/dependências | Há advisory crítico histórico de execução arbitrária de PHP no Group Manager, corrigido em versão posterior, e issue de instalação afetada por advisory em dependência JWT. | Fixes de segurança, cadeia Composer e arquivos de upload/SCORM precisam de revisão específica antes de qualquer execução. |
| Documentação funcional | Manual público cobre Opigno 3.x, mas as páginas de arquitetura/autoria localizadas foram atualizadas principalmente em 2021. | Documentação guia a investigação, mas não prova que o código publicado mantém cada fluxo sem regressão. |

## 3. Modelo funcional documentado

### Hierarquia e percurso

O manual descreve:

- **Training / learning path** como o percurso que a pessoa realiza;
- **Course** como container que agrega um ou mais módulos;
- **Module** como container sequenciado de atividades e regras de realização;
- **Activity** como unidade, por exemplo slide teórico, quiz ou conteúdo externo SCORM/Tin Can;
- **Instructor-led training (ILT)** como encontro presencial programado;
- **Live meeting** como sessão virtual integrada ao percurso.

Um percurso pode combinar cursos, módulos, atividades especiais, encontros presenciais e reuniões virtuais. Itens podem ser obrigatórios; módulos podem definir nota mínima e condições para avançar. A arquitetura oferece mais flexibilidade de percurso que a hierarquia simples curso → módulo → aula proposta no Ganjalab, mas também aumenta a complexidade editorial.

### Autoria

O Learning Path Manager oferece composição visual dos passos, incluindo organização e condições de transição. Cursos e módulos podem ser reutilizados; conteúdos podem ser duplicados. O módulo permite definir ordem sequencial ou aleatória, limite de tentativas, retomada, navegação para trás, armazenamento da melhor/última/todas as tentativas, intervalo de disponibilidade, badges, associação a competências e feedback por faixas de nota.

Pontos que valem protótipo no Ganjalab:

- árvore visual do percurso;
- seleção de conteúdo já existente para reutilizar;
- duplicar curso/módulo sem duplicar inadvertidamente IDs de tentativa ou referências privadas;
- prévia real da experiência de estudante;
- condições de avanço e pré-requisitos;
- feedback/rubrica por faixa de resultado;
- retomada e política de tentativas explícitas;
- calendário para atividades presenciais/virtuais.

### Acompanhamento e gestão

A documentação de produto descreve painel personalizável, catálogo com filtros por categoria e ação rápida para retomar cursos iniciados, gestão de classes/turmas e usuários, estatísticas de treinamento, certificados, badges, fórum, mensagens privadas, agenda e comércio eletrônico.

A página do produto também declara 30+ tipos de atividade, percursos adaptativos, H5P, xAPI/Tin Can e SCORM 1.2/2004. São capacidades anunciadas pela página do fornecedor; cobertura, integração e qualidade precisam ser validadas no código 3.2.7 e num Runtime Drupal 11.

## 4. Inventário funcional dos componentes

A relação abaixo cobre os componentes mais relevantes encontrados nas páginas do projeto e no manual; **não é uma lista exaustiva de cada submódulo interno da distribuição**.

| Componente/projeto | Papel documentado | Possível valor para Ganjalab | Triagem |
| --- | --- | --- | --- |
| `opigno_lms` distribution | Empacotamento da experiência LMS e módulos Opigno. | Referência de arquitetura full-LMS, instalação integrada e fluxos. | **Não adotar inteira agora:** distribuição com ownership e configuração sobrepostos ao ACULTA; status de manutenção e D11 desfavoráveis. |
| `opigno_learning_path` | Entidade/experiência de trilhas de aprendizagem e etapas. | Organização de oficinas, módulos e atividades em percurso visual. | **Estudar o padrão e UX; port seletivo** se a árvore/editor passar em D11 e puder consumir contratos ACULTA. |
| `opigno_group_manager` | Editor para compor passos de trilhas, ordenar itens e configurar transições condicionais. | Inspiração forte para autoria guiada e progressão. | **Alto valor conceitual; alto risco de código**: módulos de sequência e permissões muito acoplados ao modelo Opigno/Group Manager. Testar isoladamente. |
| `opigno_course` | Curso que agrega módulos e pode configurar obrigatoriedade, score mínimo e condições. | Referência para relações curso/módulo e regras por curso. | **Mapear, não copiar entidade automaticamente**: ACULTA já usa Group para cursos/matrículas; evitar dois cursos canônicos. |
| `opigno_module` | Entidade de módulo composta de atividades; execução, tentativas, score, navegação e feedback. | Referência para formato de aula/atividade e política de tentativas. | **Candidato a estudo de domínio**; provável núcleo de maior custo para portar, pois tentativas, scoring e progresso são motor próprio. |
| Activity Types em `opigno_module` | Tipos/plugins de atividade com conteúdo e comportamento de execução/avaliação. | Ideia de catálogo extensível de blocos/atividades para autoria. | **Estudar plugin API e tipos úteis**; evitar carregar todas as dependências. Comparar com LMS Answer Plugins e H5P próprios. |
| `opigno_class` | Entidade de classes, grupos de pessoas vinculadas a trainings e coach. | Pode informar UX de gestão de turmas. | **Não substituir Group agora**; ACULTA tem memberships, papéis, classes e proveniência Commerce integrados ao Group. |
| `opigno_catalog` | Catálogo, filtros por categoria, treinamentos do usuário e retomar treinamento iniciado. | Bons padrões para descoberta, filtros e retomada. | **Reaproveitar ideias/UI; não entidade**. ACULTA já tem Views/catalog, dashboard e rota Ganjalab. |
| `opigno_dashboard` | Dashboard customizável pelo usuário com layout/blocks arrastáveis e blocos obrigatórios por admin. | Referência para composição e personalização de dashboards. | **Baixa prioridade**; pode complicar mobile e conflitar com experiência Ganjalab curada. Priorizar home orientada à próxima ação. |
| `opigno_certificate` | Templates e emissão automática de PDF ao concluir training. | Referência de editor de templates e critérios de emissão. | **Comparar com certificados ACULTA**; não migrar modelo sem verificar snapshot, verificação pública, QR, revogação e PDF comum já implementados. |
| `opigno_calendar` / event | Calendário de eventos atribuídos a usuários, widget e interfaces. | Referência para agenda estudantil, prazos e sessões. | **Reaproveitar padrões, não serviço**; ACULTA possui módulos de calendário/eventos e uma agenda LMS em desenvolvimento. |
| `opigno_ilt` | Encontros presenciais com horário, inscrição/presença e possível requisito de conclusão. | Útil para oficinas e ações presenciais do Coletivo. | **Investigar caso de uso**; provável integração com Events/Calendar/Group, não novo storage de evento. |
| `opigno_moxtra` / live meetings | Integrações para reuniões virtuais e atividades ao vivo na trilha. | Referência de sessão síncrona. | **Não portar provedor** sem necessidade; integrar serviço de reunião externo por adapter/atividade. |
| `opigno_scorm` | Upload e reprodução de pacotes SCORM 1.2/2004 dentro de trainings. | Pode importar cursos de conteúdo de terceiros. | **Apenas se houver conteúdo SCORM real**; alta superfície de segurança em ingestão/extração de ZIP e estado de runtime; validar código, advisory e progresso. |
| `opigno_tincan_api` | Emissão de statements xAPI para atividades/ações e comunicação com LRS; dashboard pode consumir dados LRS. | Interoperabilidade e export de eventos de aprendizagem. | **Avaliar após definição do modelo de eventos ACULTA**; xAPI é investimento de integração, não requisito inicial do editor. |
| H5P integrado | Atividades H5P usadas dentro dos módulos. | Referência de uso pedagógico e cobertura de tipos interativos. | **ACULTA já tem integração H5P própria em RC**; não trocar sem comparação de segurança, storage, grades e ownership. |
| `opigno_messaging` / messaging app | Mensagens privadas entre usuários/grupos, conforme projeto/documentação. | Algumas ideias de interação social. | **Não portar**: Messenger ACULTA é dono de avisos, conversas e preferências; evitar mensageria paralela. |
| `opigno_forum` | Discussões/fórum ligados à experiência Opigno. | Padrões de associação de discussão a percurso. | **Não portar motor**: ACULTA possui Fórum e adapter contextual LMS, com Entity Access e Group. |
| `opigno_profile` / social | Perfil, conexões e recursos de comunidade descritos na distribuição. | Inspiração para identidade de participantes e comunidade de aprendizagem. | **Não duplicar**: reutilizar Profile/aculta_user e conexões/Messenger existentes. |
| `opigno_migration` | Migração de sistemas/formatos anteriores para Opigno; documentação cita limites de conteúdo migrado. | Referência de estratégia de importação e reconciliação. | **Não usar como migrador Ganjalab**; escrever importadores por fonte e formato com mapa/reconciliação próprios. |
| Módulos de poll, cron, standalone SCORM, learning path creation e Aristotle | Extensões listadas pelo ecossistema Drupal.org. | Identificar features opcionais e possíveis convenções Drupal. | **Auditar individualmente**; páginas de projeto podem ser independentes e sem cobertura de segurança/release atual. |

## 5. Aderência ao ecossistema ACULTA

| Domínio ACULTA atual | Estado documentado | Colisão potencial com Opigno | Direção |
| --- | --- | --- | --- |
| Cursos/matrículas/turmas | Group `lms_course`, Group memberships/classes, Commerce para origem paga. | Opigno tem entidades de course/class/training e seu próprio gerenciamento de participantes. | Manter Group como fonte canônica; portar apenas UX/organização após adaptar relações. |
| Lições/atividades/tentativas/notas | Drupal LMS contrib atualmente é a fonte canônica. | Opigno `opigno_module` e activity plugins seriam outro motor e storage. | Não ativar dois motores no mesmo curso; contrato/adapter e migração por curso antes de qualquer corte. |
| Progresso/retomada | ACULTA compõe progresso LMS, mas última aula/URL canônica de retomada está pendente. | Opigno catalog e status dão padrões e dados de retomada. | Usar como referência de requisito; projetar `LearningResumeProvider` canônico ACULTA. |
| H5P | Integração própria ACULTA em RC. | Opigno tem H5P integrado à sua estrutura de módulo/activity. | Não trocar; comparar armazenamento, tentativas, nota, bibliotecas e acessos antes de pensar em interoperar. |
| Arquivos/PDF/imagens | Media/File, política privada, aculta_image e aculta_pdf_viewer. | Opigno gerencia mídia/SCORM e pode assumir upload/storage próprio. | Reutilizar somente referências de UX; todas as ingestões devem passar pelas políticas ACULTA. |
| Commerce | Commerce e memberships vinculadas a pedido com reconciliação/refund. | Opigno descreve e-commerce próprio/integrado. | Não portar checkout. Preservar entitlement Commerce como fonte de acesso. |
| Avisos/mensagens | Messenger unificado e mailer. | Opigno inclui messaging. | Não portar comunicação; mapear eventos educacionais para o contrato público Messenger. |
| Fórum/comunidade | aculta_forum e adapter contextual. | Opigno traz forum/social wall. | Não duplicar; adaptar contextos e permissões Group ao Fórum ACULTA. |
| Agenda/eventos | Calendar/Events e agenda LMS em Dev. | Opigno calendar/ILT/live meeting. | Reutilizar padrões de agenda; vincular sessões a Events/Calendar ACULTA via adapter. |
| Certificados | Emissão/validação pública/QR/PDF ACULTA parcialmente implementados; Runtime visual ainda pendente. | Opigno templates + emissão automática. | Fazer gap analysis dos templates/editor; não substituir sem provar perda/ganho. |
| Perfil/conquistas | aculta_user/aculta_game. | Opigno profile, badges e competências. | Integrar por APIs/eventos ACULTA, sem pontuação ou perfil paralelos. |

## 6. O que vale testar primeiro

### Protótipo P1: árvore/autoria de learning path

Objetivo: validar a experiência, não adotar o motor completo.

- Modelo mock/adapter com curso Group ACULTA e fixtures de módulos/aulas.
- Interface mobile-first para adicionar, selecionar, ordenar, duplicar e recolher itens.
- Estados de rascunho/publicado e prévia de estudante.
- Critérios de acessibilidade por teclado, foco e leitor de tela.
- Nenhum registro de atividade/tentativa real é criado durante a prova.

Comparar três caminhos: UI ACULTA própria inspirada em Opigno; adaptar `opigno_group_manager`; portar a ideia para contratos ACULTA.

### Protótipo P2: uma atividade e uma tentativa

Selecionar um tipo simples (pergunta objetiva ou reflexão) e medir:

- definição/configuração e revisão do conteúdo;
- autorização por curso/turma;
- tentativa, resposta, feedback e conclusão;
- cache, auditoria, duplicação e migração;
- interoperabilidade com H5P/Fórum/arquivo.

Só se o código Opigno demonstrar vantagem concreta e passar segurança/D11 deve haver port de atividade.

### Inventário de código para agente local

Um checkout isolado deve registrar:

1. URL/tag exata e SHA dos repositórios Opigno usados (3.2.7/3.2.8 e commits aplicáveis);
2. lista exata de módulos ativados pela receita/perfil Composer;
3. `composer.json`/`composer.lock`, dependências abandonadas/advisories e licença;
4. entidades, campos, tabelas, plugins, routes, access handlers, event subscribers, cron e queues;
5. pontos de integração com Group, Commerce, H5P, SCORM, xAPI, File/Media e certificados;
6. diffs necessários para Drupal 11 sem automatizar fixes cegamente;
7. dependências que já existem no ACULTA e colisões de serviço/config/route/entity IDs;
8. relatório de security review focado em upload e extração de pacotes, execução/parse de conteúdo, endpoints e permissões;
9. recomendações por componente: port, adapter, referência de UX, substituir por ACULTA ou descartar.

## 7. Matriz preliminar de aproveitamento

| Componente | Valor funcional | Custo de adaptação provável | Risco atual | Decisão preliminar |
| --- | --- | --- | --- | --- |
| Learning Path Manager / group manager | Alto | Alto | Alto | Provar UX, depois portar conceitos/pequeno subconjunto. |
| Catálogo e retomada | Alto | Baixo/médio | Médio | Usar como checklist funcional; compor com Views e dados ACULTA. |
| Module/activity authoring | Alto | Alto | Alto | Estudar API e atividades individualmente; não adotar engine no escuro. |
| Activity plugin types | Médio/alto | Médio/alto | Alto | Comparar com Answer plugins/H5P ACULTA; selecionar somente lacunas. |
| SCORM | Condicional | Alto | Alto | Prioridade baixa até existir inventário de conteúdo SCORM necessário. |
| xAPI/LRS | Condicional | Médio/alto | Médio/alto | Definir contrato de eventos e consumidor antes de escolher implementação. |
| Classes/memberships | Baixo para ACULTA | Alto | Alto | Rejeitar duplicação; manter Group. |
| Certificate | Médio | Médio | Médio | Gap analysis, provavelmente manter emissão ACULTA. |
| Calendar/ILT | Médio | Médio | Médio | Adaptar UX/conceito a Events/Calendar existente. |
| Dashboard personalizável | Baixo/médio | Médio | Médio | Não priorizar; preferir dashboard curado e mobile-first. |
| Messaging/forum/profile | Baixo | Alto | Alto | Não portar; já há serviços ACULTA com ownership claro. |

## 8. Conclusão

Opigno tem mais valor como **mapa de capacidades e padrões de autoria** do que como distribuição a instalar dentro do ACULTA. O maior ativo para o Ganjalab parece ser a experiência de montar trilhas e definir regras de progressão; os maiores passivos são o motor próprio de atividade/tentativa, as sobreposições de domínio, a compatibilidade Drupal 11 e a manutenção de uma base comunitária encerrada.

A decisão recomendada após este inventário é **autorizar uma auditoria de código isolada e dois protótipos pequenos** (árvore de autoria e tentativa simples), sem alterar Composer/config/runtime do ACULTA. Ao fim, escolher por componente se haverá port seletivo ou implementação ACULTA. Não migrar cursos, estudantes, tentativas ou arquivos nesta etapa.

## 9. Fontes primárias

- [Opigno LMS Community - situação, funcionalidades e releases](https://www.drupal.org/project/opigno_lms)
- [Release Opigno LMS 3.2.7](https://www.drupal.org/project/opigno_lms/releases/3.2.7)
- [Ecossistema de módulos Opigno](https://www.drupal.org/project/opigno_lms/ecosystem)
- [Manual Opigno 3.x - hierarquia de conteúdo](https://opigno.atlassian.net/wiki/spaces/OUM3/pages/2802942618/Content%2Bhierarchy)
- [Manual Opigno 3.x - criar módulo](https://opigno.atlassian.net/wiki/spaces/OUM3/pages/2802944026/Create%2Ba%2Bmodule)
- [Manual Opigno 3.x - criar curso](https://opigno.atlassian.net/wiki/spaces/OUM3/pages/2802944156/Create%2Ba%2Bcourse)
- [Manual Opigno 3.x - importar/exportar](https://opigno.atlassian.net/wiki/spaces/OUM3/pages/2802944860/Importing%2Bexporting%2Bmodules%2Bcourses%2Band%2Btrainings)
- [Learning Path](https://www.drupal.org/project/opigno_learning_path)
- [Group Manager](https://www.drupal.org/project/opigno_group_manager)
- [Module](https://www.drupal.org/project/opigno_module)
- [SCORM](https://www.drupal.org/project/opigno_scorm)
- [TinCan/xAPI](https://www.drupal.org/project/opigno_tincan_api)
- [Certificate](https://www.drupal.org/project/opigno_certificate)
- [Drupal LMS security advisory - Group Manager](https://www.drupal.org/sa-contrib-2024-027)
