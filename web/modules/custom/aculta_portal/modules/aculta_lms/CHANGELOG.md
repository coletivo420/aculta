# Changelog — aculta_lms

## 0.1.0-dev.2 — F6: página de curso e sala (implementação inicial)

- Acrescentado `CourseRoomBuilder` e campo extra configurável na página canônica
  de curso; o índice usa conteúdo e access do Drupal LMS/Group e liga para a
  rota nativa da primeira atividade de cada lição.
- A seção não expõe lições quando a conta não tem `take course`; resultados de
  access, curso e lições alimentam a cacheabilidade.
- Sem alteração de entidades LMS, matrícula, status, resposta ou storage.
- RUNTIME STATUS: DEFERRED; smoke test com membro, não membro e visitante
  pendente.

## 0.1.0-dev.1 — Fundação F1

Submódulo opcional, publisher DI e evento imutável de conclusão aprovada do
Drupal LMS. Testes Unit (2/2) e Kernel de instalação (1/1) PASS. Nenhuma
persistência, rota, configuração ou integração Messenger ativa é criada.
RUNTIME STATUS: DEFERRED; o submódulo não foi habilitado no Runtime.
