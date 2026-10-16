# ADR-005: Drupal LMS como fonte de verdade

Status: Accepted. Revisão técnica: 2026-10-16.

## Contexto

O ACULTA já usa Drupal LMS e Group. O Portal agrega catálogo, conta, rotas e apresentação em uma arquitetura multidomínio. Armazenar cópias locais de cursos, memberships, progresso ou nota criaria estado concorrente e risco de acesso divergente.

## Decisão

- Group, bundle `lms_course`, é dono dos grupos/cursos e das memberships/papéis.
- Drupal LMS é dono de lições, atividades, respostas, tentativas, estados e notas. Usar as entidades e serviços públicos do LMS, especialmente `TrainingManager`, e preservar seus access handlers.
- `aculta_lms` será um módulo integrador opcional: políticas educacionais que não existam no upstream, adapters DI e contratos de eventos. Não criará storage paralelo de matrícula, resposta, progresso, nota ou certificado enquanto LMS/Core/contrib cobrir o dado.
- `aculta_portal` continua dono de Domain/purpose, URLs, acesso a rotas, projeções para conta e integração neutra. ACULTA420 escolhe e apresenta SDCs; não acessa entidades, serviços, permissões ou host.
- Media/File é dono de documentos e metadados de arquivo; a mesma entidade Media pode ser referenciada por aula, revista, wiki, fórum, projeto e conteúdo institucional. O contrato compartilhado de PDF está em `docs/portal/PDF-VIEWER-INTEGRATION.md`.
- `aculta_user` é dono de perfil, amizades e definições/concessões de conquistas. `aculta_messenger` é dono dos contratos de comunicação e integra Message/Message Notify; LMS não mantém notificações próprias.
- Certificado é uma representação emitida a partir de conclusão LMS confirmada. Não altera gradebook nem cria segundo estado de progresso. F11 deve decidir se o PDF é derivado regenerável ou registro imutável/versionado, como revogar e auditar antes de qualquer implementação.
- H5P permanece não ativado até auditoria de compatibilidade, upload, segurança de bibliotecas executáveis, permissões, CSP, armazenamento e integração de resultado. Se não houver interface de nota confiável integrada ao LMS, H5P começa sem nota oficial.
- Não instalar LMS Messages, Friendship, Open Social, Opigno inteiro, nem infraestrutura paralela de relacionamento. Google Classroom não pertence à versão inicial e só poderá entrar como integração opcional de publicação de link de atividade após homologação e nova decisão.

## Consequências

Consultas e alterações seguem API pública, Entity API, Views e plugins nativos; EntityQuery declara `accessCheck()` conscientemente. Apresentações carregam access/cacheability. As projeções de conta verificam `Group::access()` antes de ler metadados e score. Slugs humanos continuam em português e os endereços de cursos usam `DomainPurposeManager`.

Trabalhos enviados e documentos privados exigem File API privada, acesso por entidade/turma, URL controlada e revalidação no request. Não se considera ocultar um botão de download como proteção.

## Evidência no código e configuração

`composer.lock` fixa Drupal LMS 1.2.3, Group 3.3.5 e Webform 6.3.1. LMS `lms_answer_plugins` e `lms_classes` são submódulos do pacote `drupal/lms`; `drupal/lms_file_upload` é um pacote independente. O curso piloto e as integrações de catálogo/conta estão documentados em [LMS-GROUP.md](../modules/LMS-GROUP.md). A matriz de projetos e requisitos está em [LMS-COMPATIBILITY.md](../modules/LMS-COMPATIBILITY.md).

## Critérios de revisão

Revisar esta ADR se o LMS deixar de cobrir uma capacidade, se uma integração externa exigir novo dono de dados ou se uma regra de certificado/aferição criar persistência adicional. Toda exceção deve descrever dado, autoridade, ciclo de vida, access, cache, atualização e rollback.
