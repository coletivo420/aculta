# Matriz de segurança, acesso e arquivos do LMS

Revisado em 2026-10-16. As linhas definem critérios de acesso e homologação.

| Recurso/ação | Fonte/controle | Estudante | Educador | Criador/admin de cursos | Verificação obrigatória |
| --- | --- | --- | --- | --- | --- |
| Catálogo | View `courses_catalog` + Group access | público conforme config da View | igual ao access concedido | igual ao access concedido | View access e Group entity access, conteúdo publicado |
| Metadados/curso privado | Group access + revision access | só membership/papel autorizado | papel da turma conforme Group | criador/admin conforme handler e papel | membro de outra turma, anônimo e não publicado |
| Matrícula | Group membership + LMS Classes | própria associação a turma do curso | criar/gerir turmas e alunos dos cursos autorizados | gestão conforme papel | rejeitar turma de outro curso, curso sem turma e alteração de membership por ID cliente; usuário A/B e educador A/B |
| Lições/atividades | LMS routes + TrainingManager + Group access | tentativa própria, sequência LMS | correção conforme permission `grade students` | conforme capability LMS/Group | acesso direto, delta alterado e turma cruzada |
| Respostas/status/notas | LMS entity access | próprio resultado permitido | só resultados de turma autorizada | acesso restrito e auditável | IDOR por answer/status/course e cache A/B |
| Arquivo de atividade submetido | Media/File privado + LMS File Upload | próprio arquivo | avaliador da turma, apenas após autorização | privilegiado conforme política | URL direta, expiração/revogação de membership, MIME/tamanho e upload malicioso |
| PDF incorporado em aula/revista/wiki | Media + File Access + Portal viewer | acesso definido pela origem/turma | conforme contexto | conforme permissão | preservar access da entidade origem, sem URL arbitrária, fallback seguro |
| Certificado | status final LMS + Entity Print futuro | próprio certificado | elegibilidade/verificação conforme política | emissão/revogação com permissão auditável | token não enumerável, usuário desativado, reversão de nota e cache |
| Notificação | Message + Message Notify | somente eventos próprios e ainda permitidos | público definido por evento aprovado | conforme função administrativa | revogar membership antes de queue dispatch, dedupe, e-mail mínimo |
| Dashboard | Views + serviço LMS + cache metadata | dados próprios | agregado apenas da turma autorizada | agregado conforme grants | verificar queries com `accessCheck` explícito e contextos/cache tags |

## Controles obrigatórios

- Não interpretar esconder link, campo, botão ou download como autorização.
- Revalidar acesso pelo handler de entidade e Group membership/papel no request atual. Flag/assinatura é preferência, nunca access grant.
- Queries EntityQuery declaram conscientemente `accessCheck(TRUE|FALSE)`; exceções limitam resultados a principal autorizado e passam por verificação de entidade/access posterior.
- Uploads avaliativos devem usar storage privado, allowlist específica, limite de bytes e MIME, nome seguro, sem executar conteúdo, scanner conforme disponibilidade e política de retenção. O tipo Media `document` atual usa `public://` e extensões amplas: não é destino apropriado para trabalhos privados.
- A URL de viewer/arquivo é gerada por `aculta_pdf_viewer`/Core a partir de referência Media/File; bloquear URLs externas/arbitrárias, path traversal e parâmetros de viewer que alterem source.
- Conteúdo privado jamais recebe cache compartilhado sem contextos corretos. Mudança de membership deve invalidar cache e cortar acesso a arquivo já conhecido.
- Log não registra resposta, nota, corpo de aviso nem token de acesso. Auditoria de correção/certificado limita campos e retém o mínimo necessário.
- CSP, MIME, iframe, PDF.js worker, `.mjs`, tamanho de PDF e timeouts são gates da fase PDF. H5P executável requer hardening e isolamento antes de habilitar bibliotecas ou upload de pacotes.

## Papéis definidos na configuração exportada

O estudante group `member` tem `take course`; educador `teacher` tem `grade students`, pode gerir membros e acessar revisões, além do papel global `lms_teacher`; `course_creator` é local e também pode corrigir/editar. `lms_admin` global cria `lms_course` e tem role de grupo admin interno/externo. Essa combinação exige verificar grants efetivos no Runtime e na versão upstream antes de abrir autoinscrição ou correção para novos papéis.

## Testes de isolamento que bloqueiam RC

Matriz mínima: anônimo; usuário autenticado sem membership; estudante da turma A; estudante da turma B; educador A; educador B; criador; admin de curso; conta bloqueada/desativada. Exercitar listagem, rota amigável, rota técnica, render cache frio/quente, documento público/privado, URL direta, download, resposta, status, nota, notificação na fila e revogação de membership. Confirmar 403/404 conforme política, sem disclosure de título/nota/arquivo em HTML, JSON, cabeçalhos ou logs.
