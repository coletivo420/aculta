# Auditoria estática do código Opigno LMS Community para integração ACULTA

**Data:** 2026-11-01  
**Objeto:** Opigno LMS Community 3.2.7 e módulos centrais 3.2.8  
**Tipo:** revisão estática; sem instalação/execução do Opigno  
**Resultado:** não integrar a distribuição como está. Há valor em prototipar a autoria, mas a base exige adaptação grande, revisão de segurança e manutenção de fork.

## 1. Escopo e método

Obtive releases oficiais Drupal.org de `opigno_lms-3.2.7`, `opigno_module-3.2.8`, `opigno_group_manager-3.2.8` e `opigno_learning_path-3.2.8`. Inspecionei profile/Composer, metadados, rotas, controllers, import/export, validação de ZIP, autoria, acesso e testes empacotados. Arquivos extraídos apenas em diretório temporário; nenhum código ou ambiente ACULTA foi alterado.

A revisão concentra-se nos componentes de autoria/progressão. Não cobre todos os módulos opcionais, dependências transitivas, análise dinâmica, teste de penetração, Composer audit completo, revisão integral de licenças ou validação Drupal 11. Severidades são triagem estática, não laudo de explorabilidade.

| Pacote | Versão | SHA-256 |
| --- | --- | --- |
| Perfil/distribuição `opigno_lms` | 3.2.7 | `a4314871b0e38a6d12fcc0701a5260d669937c3b21298625e4e46263c43c5952` |
| `opigno_module` | 3.2.8 | `33fb298523ac97f51864a93a7ab171ba2db074d0dae3f2cfafb055c66d1f46d8` |
| `opigno_group_manager` | 3.2.8 | `aa3edfd82af0ca32e1c62b4f480042e3587d3e23d4a3f1bd5f7646ed855acbd8` |
| `opigno_learning_path` | 3.2.8 | `2bfdf85c08b8cf471728717d2663542691fb7f5cb817cda0dbbb57b6ac29096f` |

## 2. Achados executivos

| ID | Severidade | Achado | Confiança |
| --- | --- | --- | --- |
| OPA-01 | **Alta, condicional** | Exportações são gravadas em diretório de arquivos públicos, previsível, sem limpeza por resposta; export concorrente apaga o mesmo diretório compartilhado. | Alta no desenho; exposição depende de configuração do webserver e sensibilidade do conteúdo. |
| OPA-02 | **Bloqueador** | O perfil exige Drupal Core 10 e Group 1.x; ACULTA `dev` usa Drupal 11 e Group 3.x. Não é coinstalável sem port/adaptação. | Alta, verificação direta de manifests. |
| OPA-03 | **Alto custo** | O manager de autoria é Angular 5/CLI 1/TypeScript 2.4 e depende de APIs/entidades Opigno/Group. | Alta. |
| OPA-04 | **Médio, a validar** | Importações ZIP conferem nomes/extensões, mas não aparentam limitar entradas, bytes descompactados ou razão de compressão antes de extrair. | Média; requer validação dinâmica controlada. |
| OPA-05 | **Médio** | Testes empacotados são limitados: nenhum em `opigno_module` e `opigno_group_manager`; 14 arquivos em `opigno_learning_path`. | Alta para os arquivos do pacote, não mede CI externa. |
| OPA-06 | **Médio** | O profile traz dependências extensas e patches upstream, incluindo packages alpha/dev e versões de Group, H5P, PDF, editor e browser. | Alta, manifesto do profile. |

## 3. OPA-01 — Export público e diretório compartilhado

Em `opigno_group_manager/src/Controller/OpignoGroupManagerController.php`, `courseExport()` e `trainingExport()`:

- usam `sites/default/files/opigno-export`;
- chamam `deleteRecursive($dir)` antes de cada export;
- gravam JSON/ZIP com rótulo e ID no nome;
- retornam `BinaryFileResponse` desse caminho;
- não removem a exportação no ciclo de resposta analisado.

Exportação de atividade/módulo em `opigno_module/src/Controller/LearningPathController.php` usa o mesmo diretório. As rotas Drupal verificam `group.update` ou `entity.update`, mas isso não protege necessariamente o acesso HTTP direto a arquivos servidos pelo diretório público.

**Risco:** se a pasta for servida como public files padrão, o ZIP pode ser lido sem passar pela rota autorizada. Isso pode expor material restrito/licenciado. O nome incorpora rótulo e ID, não segredo robusto. Exportações concorrentes podem apagar arquivos umas das outras. Os arquivos permanecem no disco.

**Recomendação:** não portar esse fluxo. Implementar export ACULTA com storage temporário privado fora do webroot, autorização revalidada no download, token de expiração, nome sanitizado, arquivo por operação, lock e cleanup assíncrono; testar concorrência, acesso direto e expiração.

Fontes: [course/training export](https://git.drupalcode.org/project/opigno_group_manager/-/blob/3.2.8/src/Controller/OpignoGroupManagerController.php), [activity/module export](https://git.drupalcode.org/project/opigno_module/-/blob/3.2.8/src/Controller/LearningPathController.php), [rotas de export](https://git.drupalcode.org/project/opigno_group_manager/-/blob/3.2.8/opigno_group_manager.routing.yml).

## 4. OPA-02 — Conflito de plataforma e Group

O profile declara `core_version_requirement: ^9 || ^10`, Composer `drupal/core-recommended: ^10.2` e `drupal/group: ^1.6`. O inventário ACULTA registra Drupal 11.4.10 e Group 3.3.5, com cursos, turmas, memberships e Commerce já integrados.

Os componentes Opigno registram tipos próprios como `learning_path` e `opigno_course` e dependem de entidades/handlers Group da arquitetura anterior. Não basta forçar Composer ou alterar metadados para `^11`: há diferenças de API e modelo, além de sobreposição de cursos, turmas, relações, roles e UI.

**Consequência:** instalar a distribuição inteira no ACULTA não é seguro. O caminho viável é portar capacidades, preservando Group 3 como fonte canônica.

Fontes: [composer.json do profile 3.2.7](https://git.drupalcode.org/project/opigno_lms/-/blob/3.2.7/composer.json), [info do profile](https://git.drupalcode.org/project/opigno_lms/-/blob/3.2.7/opigno_lms.info.yml), [inventário ACULTA Group/LMS](LMS-GROUP.md).

## 5. OPA-03 — Autoria: valor e custo técnico

### Capacidades presentes

- manager visual em árvore para compor/ordenar passos;
- links e regras condicionais entre passos;
- reuso de módulos em learning paths/courses;
- obrigatoriedade, score mínimo e navegação guiada;
- plugins de atividade, tentativas, scores, retomada e feedback;
- import/export de módulos, atividades, cursos e treinamentos.

A autoria é a parte mais valiosa para estudo. O modelo é mais rico que um editor de páginas simples, mas mais complexo que o percurso inicial previsto para Ganjalab.

### Acoplamentos

- Frontend do `opigno_group_manager` usa Angular 5, Angular CLI 1.x, TypeScript 2.4 e RxJS 5.5; bundle compilado é distribuído.
- `OpignoGroupManagerController.php` tem 2.160 linhas; `opigno_learning_path.module` 3.979; `opigno_module.module` 1.477.
- O manager depende de `opigno_group_content`, tipos Opigno, funções globais `opigno_*`, config própria, status próprios e Group.
- Extrair a UX requer recriar endpoints/contratos; não é somente reutilizar Twig.
- O profile acumula configurações e patches upstream, junto de capacidades que o ACULTA já possui em serviços próprios.

**Recomendação:** reimplementar a interface como SDC/Form/API ACULTA; usar Opigno para referência de interação e regras. Não reutilizar o bundle Angular compilado. Portar backend isolado somente após provar compatibilidade e ownership.

## 6. OPA-04 — Upload/importação ZIP

### Controles observados

`UnsafeFileValidation` rejeita nomes começados por ponto/underscore e padrões simples `../` e `..\`, e dispara `FileUploadSanitizeNameEvent` com extensões permitidas. Formulários limitam extensões e validam antes de extrair. O validator H5P também restringe arquivos internos.

Isso indica validação contra path traversal básico; esta auditoria **não** afirma que o padrão `../` passe sem bloqueio.

### Lacuna a validar

Os caminhos analisados chamam `ZipArchive::extractTo()` depois da validação de nomes. Não vi limite aplicativo para quantidade de entradas, bytes descompactados ou razão de compressão antes da extração. Validação de nome/extensão não impede ZIP bomb. Também é necessário testar paths absolutos/drive, symlinks e entradas especiais com as versões de PHP/libzip alvo.

**Recomendação:** extração isolada fora do webroot; limites de tamanho comprimido e descompactado, contagem e ratio; rejeição de symlink/tipos especiais; normalização de paths; cleanup garantido em falha; teste red-team em ambiente descartável antes de habilitar import a educadores.

Fontes: [ImportModuleForm](https://git.drupalcode.org/project/opigno_module/-/blob/3.2.8/src/Form/ImportModuleForm.php), [UnsafeFileValidation](https://git.drupalcode.org/project/opigno_module/-/blob/3.2.8/src/Traits/UnsafeFileValidation.php), [H5P validator](https://git.drupalcode.org/project/opigno_module/-/blob/3.2.8/src/H5PImportClasses/H5PValidatorImport.php).

## 7. OPA-05 — Autorização e confiança de release

Rotas centrais do Group Manager declaram `_entity_access: group.update`. Isso é uma fronteira explícita, mas precisa ser mapeada para papéis de educador ACULTA e testada entre curso/turma, autor, educador removido e conta bloqueada. Export é exemplo de como autorização da rota não basta quando o artefato fica em public files.

Testes encontrados no source package dos três módulos:

- `opigno_module`: zero arquivos em `tests/`;
- `opigno_group_manager`: zero;
- `opigno_learning_path`: 14 arquivos Functional/FunctionalJavascript, incluindo casos de acesso/score/completion.

Isso não prova que não haja CI privada, mas a cobertura empacotada não basta para assumir um port/upgrade de plataforma.

Há advisory crítico histórico do Group Manager para versões abaixo de 3.1.1 por execução arbitrária de PHP em formulário administrativo. Os arquivos inspecionados são 3.2.8, acima da versão corretiva indicada; não afirmo que contenham a mesma vulnerabilidade. O projeto Community também está marcado sem desenvolvimento comunitário, o que torna manutenção e resposta a falhas responsabilidade do ACULTA.

Fontes: [SA-CONTRIB-2024-027](https://www.drupal.org/sa-contrib-2024-027), [estado do Opigno Community](https://www.drupal.org/project/opigno_lms).

## 8. Composer e sobreposição ACULTA

O profile inclui Commerce, Group 1.x, H5P alpha, PDF, Entity Browser, DropzoneJS, Message/Message Notify, JWT, Private Message, calendário, revisão de entidades, Search API e patches upstream. Core é fixado em 10.2 e há dependências alpha/dev.

Conflitos de ownership:
- Group/matrícula/turma e proveniência financeira: Group 3 + Commerce ACULTA;
- avisos/mensageria: Messenger ACULTA;
- H5P/upload: integração privada ACULTA;
- Media/File, imagens e PDF: pipeline/viewer ACULTA;
- calendário/eventos: Calendar/Events ACULTA;
- certificado: entidade e verificação ACULTA.

Configuração ou entidade Opigno não é intercambiável com implementação ACULTA mesmo se o pacote Composer coincidir.

Uma issue Drupal.org reporta falha de resolução do profile 3.2.7 por advisory na cadeia `drupal/jwt`/`firebase/php-jwt`. É um incidente a reproduzir num Composer audit isolado, não prova de que toda instalação atual falhará após atualização de dependências.

Fonte: [Composer issue #3574400](https://www.drupal.org/project/opigno_lms/issues/3574400).

## 9. Matriz de aproveitamento

| Área | Decisão |
| --- | --- |
| Tree/learning-path manager UX | **Prototipar em ACULTA** com contratos próprios; não copiar Angular. |
| Transições e passos obrigatórios | **Extrair requisitos**; portar regra isolada após revisão pedagógica/access. |
| Activity plugins e scoring | **Não portar em bloco**; comparar com Answer Plugins, Webform Score e H5P ACULTA. |
| Cursos, classes, memberships | **Não portar entidades**; manter Group 3 e Commerce ACULTA. |
| Catálogo/retomada | **Reaproveitar padrões**, conectados às fontes canônicas ACULTA. |
| SCORM/xAPI | **Adiar** até existir conteúdo/parceiro que exija; superfície de segurança e manutenção grande. |
| Export/import .opi | **Não reutilizar**; formato ACULTA versionado e storage privado. |
| Messaging/forum/profile/calendar | **Não portar serviços**; já há owners ACULTA. |
| Certificados | **Comparar UX/templates**; preservar emissão/QR/verificação/PDF ACULTA. |

## 10. Próximos gates para uma auditoria completa

1. Obter checkout isolado e lockfile da recipe; executar Composer audit e registrar exceções/advisories.
2. Inspecionar módulos adicionais necessários (certificate, class, catalog, calendar, SCORM, xAPI, H5P e migration) um a um.
3. Executar análise estática e testes upstream em Drupal 10/11 após resolver dependências; revisar cada patch manualmente.
4. Testar em ambiente descartável export direto, concorrência e ZIP bomb; sem dados reais.
5. Mapear endpoint → permissão Group → operação de entidade → cache invalidation.
6. Prototipar árvore de autoria ACULTA e comparar custo/UX com o manager Opigno.
7. Decidir port seletivo, fork ou descarte por componente; nunca migrar todo o motor por decisão genérica.

## 11. Decisão

**Não incorporar Opigno Community inteiro ao ACULTA.** Group/Core incompatíveis e manutenção comunitária encerrada tornam o custo/risco desproporcional. **Sim, prototipar a autoria de Learning Path**, reimplementada no ACULTA e inspirada na composição/regras Opigno. Port de backend fica condicionado à auditoria específica da capacidade e validação no Drupal 11.

## 12. Fontes

- [Opigno profile 3.2.7](https://git.drupalcode.org/project/opigno_lms/-/tree/3.2.7)
- [Opigno Module 3.2.8](https://git.drupalcode.org/project/opigno_module/-/tree/3.2.8)
- [Opigno Group Manager 3.2.8](https://git.drupalcode.org/project/opigno_group_manager/-/tree/3.2.8)
- [Opigno Learning Path 3.2.8](https://git.drupalcode.org/project/opigno_learning_path/-/tree/3.2.8)
- [SA-CONTRIB-2024-027](https://www.drupal.org/sa-contrib-2024-027)
- [Opigno Community status](https://www.drupal.org/project/opigno_lms)


## 13. Segunda passada — módulos opcionais e integração ACULTA

A segunda passada inspecionou os pacotes opigno_certificate, opigno_class, opigno_catalog, opigno_calendar, opigno_scorm, opigno_tincan_api, opigno_migration, opigno_messaging e opigno_commerce. Isso amplia a cobertura de código; continua sendo análise estática dos arquivos de release, sem instalação ou execução.

### OPA-07 — Certificado: TODO explícito de controle de acesso

Em opigno_certificate/src/Controller/CertificateController.php, view() chama o controller pai para renderizar o certificado e deixa dois TODOs explícitos: verificar acesso ao certificado antes da renderização e verificar se o usuário concluiu o learning path. A rota usa viewAccess(), que valida a entidade que referencia o certificado e a operação “view certificate” nela; não verifica diretamente a publicação/acesso da entidade certificado. (Linhas 80–96 e 113–138 no release 3.2.7.)

**Risco condicional:** depende das configurações de entidade, permissões e formas de acesso ao controller. O código justifica teste de autorização dedicado, mas não prova que usuário não autorizado consiga ver certificados em uma instalação configurada. Antes de qualquer port, testar certificado publicado/não publicado, conclusão do percurso, dono, educador, anônimo e rota direta. ACULTA deve manter sua emissão/verificação própria e adaptar apenas padrões de template se forem úteis.

Fonte: [CertificateController 3.2.7](https://git.drupalcode.org/project/opigno_certificate/-/blob/3.2.7/src/Controller/CertificateController.php).

### OPA-08 — SCORM: extração pública, limites ausentes e caminhos divergentes

OpignoScorm::unzipPackage() extrai ZIP para public://external_package_extracted/scorm_ID por padrão, após a validação de nome herdada. O código cria proteção .htaccess em diretório público, proteção que depende de Apache; não é garantia equivalente em Nginx ou outros servidores. A leitura estática não confirmou se todas as rotas de playback evitam acesso direto aos assets. Não vi limite aplicativo de quantidade de entradas, bytes expandidos ou razão de compressão antes de extractTo(), o que deve ser testado contra ZIP bombs.

Há também divergência no fluxo: scormExtract() chama unzipPackage($file) (pasta default external_package_extracted) e em seguida procura public://opigno_scorm_extracted/scorm_ID/imsmanifest.xml. Outro formulário/validador prepara a pasta opigno_scorm_extracted como efeito anterior. Isso parece acoplamento a uma etapa prévia; tratar como defeito latente de fluxo até teste dinâmico confirmar cenário de falha.

**Decisão:** não habilitar SCORM por cópia direta. Se houver demanda real, usar extração temporária privada e isolada, limites explícitos, validação de caminhos/symlinks, cleanup em erro e checagem de autorização dos recursos de playback, com testes de integração e infraestrutura Apache/Nginx.

Fontes: [OpignoScorm 3.2.7](https://git.drupalcode.org/project/opigno_scorm/-/blob/3.2.7/src/OpignoScorm.php), [módulo SCORM](https://git.drupalcode.org/project/opigno_scorm/-/tree/3.2.7).

**Atualização (2026-11-02):** o responsável determinou implementar SCORM dentro do `aculta_lms`, sem submódulo, o que
supera a decisão de adiar acima. Não foi copiado código do Opigno. A base foi entregue no `aculta_lms` 0.1.0-dev.106,
com leitor de manifesto, validador de pacote e extrator em diretório privado, sem usar `public://`. Ficam pendentes
o player e o lançamento, e a decisão de produto sobre onde registrar o progresso. Conflito registrado: a decisão
original de adiar por falta de demanda real é anterior à ordem do responsável.

### OPA-09 — Migração: desserialização de dados legados

opigno_migration/src/Plugin/migrate/source/User.php usa unserialize() sem allowed_classes => FALSE para o campo data de usuários e um valor de perfil (linhas 92 e 123). A superfície é o banco-fonte legado usado por operadores da migração, não uma rota web de uso comum; portanto o risco depende da confiança e integridade desse banco. Outra desserialização no subscriber de grupos passa allowed_classes => FALSE.

**Recomendação:** tratar somente bancos de origem verificados como confiáveis, substituir por parser restrito quando possível ou usar allowed_classes => FALSE com validação de schema, e manter migração em ambiente isolado com fixture malformada. Não importar usuários/dados de origem sem inventário e revisão.

Fonte: [User migration source 3.2.7](https://git.drupalcode.org/project/opigno_migration/-/blob/3.2.7/src/Plugin/migrate/source/User.php).

## 14. Módulos opcionais — cobertura e decisão por capacidade

| Pacote | O que oferece | Integração ACULTA | Evidência/decisão |
| --- | --- | --- | --- |
| opigno_certificate | Modelos e geração de certificado vinculados a percursos | Sobrepõe certificados ACULTA | Reutilizar requisitos de templates, não emissão/acesso sem revisão (OPA-07). |
| opigno_class / opigno_catalog | Turmas/coortes e catálogo de treinamentos | Sobrepõe Group 3, matrícula e descoberta ACULTA | Usar padrões de UX; não portar entidades ou regras de matrícula. |
| opigno_calendar | Eventos e calendário individual | Sobrepõe calendário/eventos ACULTA | Não integrar serviços; avaliar apenas conteúdo/UX. |
| opigno_scorm | Player e importador SCORM | Capacidade ainda não assumida pelo ACULTA | Adiar até demanda de conteúdo concreta; OPA-08 e cobertura de testes nula no pacote inspecionado. |
| opigno_tincan_api | Emissão de eventos xAPI para atividades e integração LRS | Potencial analítica, mas exige perfil xAPI, privacidade e destino LRS | Não adotar sem arquitetura de eventos ACULTA, consentimento/retention e contrato de dados; sem testes no pacote inspecionado. |
| opigno_migration | Importação de dados de versões/distribuições legadas | Útil apenas em migração pontual | Não é dependência de runtime; revisão e isolamento antes de qualquer execução (OPA-09). |
| opigno_messaging / opigno_commerce | Mensagens e compras/entitlements | Sobreposição direta a Messenger/Commerce ACULTA | Não portar. |

Os módulos opcionais inspecionados declaram compatibilidade Drupal ^9 || ^10; não declaram Drupal 11. A presença do código não significa compatibilidade com stack ACULTA. A distribuição habilita várias dessas capacidades, elevando a superfície operacional e de atualização.

## 15. Plano de validação para os achados novos

1. Criar ambiente descartável em versão upstream compatível e reproduzir a matriz de acesso de certificado; registrar caso concreto antes de classificar como vulnerabilidade.
2. Testar SCORM com ZIP de tamanho/ratio extremos, caminhos especiais e upload interrompido; mapear extração nas rotas e nos webservers usados pelo ACULTA.
3. Exercitar scormExtract() sem pré-extração e documentar o caminho chamado pelo formulário/entity lifecycle.
4. Executar migração somente com cópia sanitizada do banco legado; testar payload de objeto serializado sem objetos permitidos.
5. Se xAPI for priorizado, desenhar eventos e dados pessoais antes de selecionar LRS ou portar o módulo.
6. Atualizar a decisão de cada módulo depois desses testes, mantendo análise estática e comportamento reproduzido separados.



## 16. Decisão de extração visual e fronteiras Studio

A aplicação visual da auditoria foi detalhada em [ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md](../portal/ACULTA-STUDIO-EXPERIENCE-BOUNDARIES.md). A decisão divide as experiências em três áreas, sem portar o shell do Opigno:

- **Marley:** Revista, moderação do Fórum e Galerias.
- **Ganjalab Educador:** workspace separado para autoria/gestão educacional; o Course Builder visual pertence aqui.
- **Ganjalab Estudante:** jornada, sala de aprendizagem, progresso e materiais.

### Padrões visuais selecionados

1. **Etapas do percurso e próxima ação** para a jornada de estudante: estado real, foco na etapa atual, retomar aprendizado e índice recolhível no mobile.
2. **Árvore/hierarquia de autoria** para educadores: estrutura desktop e sequência Estrutura → Item → Edição no mobile, com mover por teclado/toque além de drag-and-drop.
3. **Inserção contextual e prévia do estudante** no Course Builder, somente para tipos e operações já suportados pelo LMS/providers ACULTA.
4. **Catálogo e retomada** conforme padrões funcionais, renderizados sobre Views e matrícula/status canônicos ACULTA.

Não portar visual de dashboard configurável, CSS, templates, Angular compilado, navegação administrativa, nem componentes pixel a pixel do Opigno. O ACULTA420 mantém shell, identidade, tokens e SDCs. Esta decisão é de design/arquitetura, não comprova implementação nem reabre a decisão de rejeitar a distribuição completa.

Alinhamentos normativos: [Course Builder Ganjalab Educador](../portal/STUDIO-LMS-COURSE-BUILDER-VISUAL.md), [experiência do estudante](../portal/GANJALAB-EXPERIENCE-IMPLEMENTATION-PLAN.md), [roadmap do LMS](ACULTA-LMS-ROADMAP.md) e [escopo Marley](../portal/STUDIO-MAGAZINE-ROADMAP.md).

## 17. Status de implementação ACULTA — 2026-11-01

O padrão 1 (etapas do percurso e próxima ação) foi aplicado sobre um contrato ACULTA próprio: `CourseJourneyReaderInterface` e o DTO `CourseJourney` fornecem à jornada do Ganjalab o percurso ordenado, a posição atual e os destinos autorizados sem expor entidades do LMS ao composer do Studio. As capacidades do Opigno continuam sendo referência de UX; nenhum código, template, CSS, Angular, entidade, armazenamento ou formato de export do Opigno foi portado. Inventário e plano de desacoplamento: [LMS-CONTRIB-DEPENDENCY-INVENTORY.md](LMS-CONTRIB-DEPENDENCY-INVENTORY.md).

## Auditoria do Opigno 7.x-1.23 (capacidades SCORM) — 2026-11-02

Pedido do responsável: auditar capacidades específicas do 7.x, sem usar o pacote como base. Decisão anterior (não incorporar o Opigno inteiro) permanece. Fonte lida de forma estática, em pasta própria fora do repositório. Nenhum código foi copiado.

Achados:

- **Extração sem limites (mesmo risco do OPA-08).** `opigno_scorm_extract()` chama `ZipArchive::extractTo()` direto em `OPIGNO_SCORM_DIRECTORY/scorm_<fid>`, sem limite de entradas, bytes, razão de compressão ou links simbólicos. Já coberto pelo `ScormPackageValidator` e pelo `ScormPackageExtractor` (aculta_lms 0.1.0-dev.106).
- **Caminho de lançamento a partir do diretório extraído.** O player monta `{extracted_dir}/{launch}` (`opigno_scorm_ui.player.inc`, linha 121). Não há checagem de autorização nesse ponto da leitura estática. Vai para a rota autorizada, não para caminho público.
- **Estado do aluno em tabela própria por SCO.** `opigno_scorm_ui_scorm_cmi_get()` lê cmi por usuário, pacote e chave. Em ACULTA o mesmo dado vai como dado da resposta do LMS (decisão tomada), sem tabela paralela.
- **Integração SCORM com quiz (`opigno_scorm_quiz`).** Registra caminhos de interação e objetivos, e converte respostas SCORM em respostas de quiz (`response.inc`, `question.inc`). Capacidade real, ainda não portada. Exige decisão de produto: nota do SCORM entra na avaliação do LMS? Hoje não está definido.
- **Árvore do player (`opigno_scorm_ui_player_scorm_tree`, `flatten_tree`, `start_sco`).** Organização dos SCOs e início automático do próximo. Entra junto com o player, ainda não feito.

Não usado: `XML2Array` (substituído por DOMDocument), formulários D7, `hook_menu`, `hook_field_*` e Rules. Todos são de D7.

Conclusão: o 7.x confirma o que já foi portado (API, manifesto, validação, extração privada) e aponta duas capacidades para decisão futura: a integração SCORM com quiz e a árvore do player. Não há código de D7 aproveitável diretamente em D11.

**Decisão do responsável (2026-11-02):** a nota do SCORM entra na avaliação do LMS, mapeando as interações do SCO como quiz, no modelo da integração do Opigno 7.x. Pendências dessa decisão: verificar no módulo `lms` contrib (não disponível no ambiente) o modelo de resposta de quiz antes de implementar; definir como a pontuação agregada se relaciona com a regra de tentativa (melhor ou última).
