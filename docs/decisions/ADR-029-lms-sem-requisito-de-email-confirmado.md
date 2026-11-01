# ADR-029 — Participação no LMS sem requisito de e-mail confirmado

**Status:** vigente desde 2026-10-19, decisão F7.  
**Escopo:** matrícula e participação em cursos/turmas para Portal, User e LMS.  
**Origem:** “Não lançado — acesso do LMS sem confirmação de e-mail”, [histórico arquivado do Portal](../history/changelog/aculta_portal.md).

## Contexto

Exigir confirmação de e-mail além da matrícula impediria estudantes com acesso
legítimo de usar conteúdo adquirido ou concedido. Remover a exigência de e-mail
não pode, porém, remover as autorizações de curso nem o aceite aplicável.

## Decisão

1. Confirmação de e-mail não é requisito de matrícula nem de participação em
   curso/turma.
2. Acesso ao conteúdo continua condicionado à matrícula e ao `take` do Group
   no curso, avaliados no contexto atual.
3. O aceite vigente de Termos de Uso e Política de Privacidade continua
   obrigatório quando o fluxo de dados pessoais o exige. A matrícula não marca
   a conta como e-mail confirmado nem herda permissões desse estado.

## Evidência vigente

`CourseRoomBuilder` e os painéis do LMS revalidam `takeAccess()` no curso pai;
`PAPEIS-PARTICIPACAO.md` e `EMAIL-CONFIRMATION-POLICY.md` documentam a exceção.
O ledger de aceite permanece separado em `AcultaUserAgreementLedger`.
