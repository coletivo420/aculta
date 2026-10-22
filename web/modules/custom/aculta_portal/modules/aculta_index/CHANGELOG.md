# Changelog — ACULTA Content Index

## 0.1.0-dev.3 — ciclo inicial de gerações

- Adicionado `ContentIndexGenerationManager` para iniciar geração, registrar
  validação e trocar/retomar o ponteiro entre gerações validadas.
- A troca mantém a geração anterior em `retained`, atualiza o ponteiro por CAS
  dentro de transação e reverte integralmente se o estado de origem divergir.
- Kernel cobre o ciclo de ativação/rollback e a recusa transacional de geração
  ainda em construção.

## 0.1.0-dev.2 — iniciar ponteiro vazio de geração

- A instalação cria a linha singleton de `aculta_index_state` com geração ativa
  vazia e revisão zero. Nenhum conteúdo é considerado publicado no catálogo
  antes da futura validação e ativação de um rebuild.
- O provider registry valida as declarações de tipo de entidade e bundles.
- Os contratos públicos de provider, projeção e relação estão no namespace
  neutro do Portal; o submódulo permanece opcional.
- Schema instalado em Kernel no banco MariaDB de teste; Runtime não alterado.
