# ADR-017 — Avisos educacionais somente para eventos acionáveis

**Status:** aceito em 2026-10-21 pelo responsável.
**Escopo:** eventos de turma produzidos pelo LMS e entregues pelo Messenger.
**Origem:** `0.1.0-dev.17`, `0.1.0-dev.18`, `0.1.0-dev.63` e decisões F13 em
[histórico do LMS](../history/changelog/aculta_lms.md).

## Contexto

Avisos causados por qualquer salvamento editorial gerariam ruído e não
representariam uma mudança útil para estudantes. Prazos também precisam
pertencer à turma, pois uma atividade pode ser reutilizada em turmas diferentes.

## Decisão

1. Notificar mudanças acionáveis e persistidas: conteúdo liberado, novos envios
   ou retornos publicados e criação/alteração do prazo de uma atividade na
   turma. Edições editoriais sem mudança acionável não geram aviso.
2. Armazenar prazo na relação Group entre turma e atividade, sem alterar a
   entidade global da atividade; o valor pode variar por turma.
3. Avisos e lembretes revalidam matrícula, acesso e estado atual antes de serem
   exibidos ou persistidos. O aviso não inclui o texto privado do envio ou
   comentário.
4. A janela de lembrete é de 24 horas antes do prazo; este aviso é interno e
   não envia e-mail transacional.

## Consequências

- O LMS produz eventos a partir de alterações persistidas e mantém prazos em
  Group; o Messenger é dono de mensagens/avisos e das preferências de entrega.
- Os contratos de produtor, autorização e lembrete ficam detalhados em
  [LMS-INTEGRATION-CONTRACTS.md](../portal/LMS-INTEGRATION-CONTRACTS.md).

## Verificação no código

O campo `field_activity_due_at` pertence à relação turma–atividade; os hooks e
workers LMS produzem apenas eventos persistidos e revalidam acesso. O
Messenger mantém os avisos no armazenamento Message e não envia o corpo privado
do envio/retorno.
