# ACULTA Learning Contracts - módulo em desativação

As classes de leitura de cursos da Conta foram incorporadas a
`aculta_portal/src/Learning`. O `aculta_lms` implementa diretamente esse
contrato, sem herança de módulo intermediário.

Este diretório permanece provisoriamente no repositório apenas porque o módulo
ainda consta instalado em `config/sync/core.extension.yml`. O manifesto
`.info.yml` precisa permanecer disponível para desinstalação segura no Runtime.
Não adicionar serviços, classes nem consumidores a este módulo.

Procedimento final: `docs/operations/LEARNING-CONTRACTS-RETIRADA.md`.
