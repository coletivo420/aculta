# Changelog — aculta_content_admin

## 0.1.0-rc.1 — validar ADM5 no candidato servido (2026-10-29)

- Firefox ESR autenticado no Runtime: rota canônica 200; somente as seis
  coleções nativas acessíveis à conta de teste apareceram; foco visível 6/6;
  reflow sem overflow em 1280/390/320 CSS px (7/7 checks).
- `ModeratableCollectionAccessKernelTest`: 4 testes/56 assertions PASS; PHPCS
  Drupal/DrupalPractice PASS; PHP lint 3/3; gates de alias temporário, Drupal
  11+ e fronteira módulo/tema PASS. Sem conteúdo criado/editado.
- A conta UID 5 foi usada por ULI, sem alteração de papéis ou dados. Capturas
  ficaram fora do repositório em `$SUITE_OUT`.

## 2026-10-29 — evitar colisão com a listagem Core de conteúdo

- Movido o painel para `/painel-administrativo/colecoes-moderaveis`, pois o caminho anterior colidia com `view.content.page_1`.
- Sem alias para a rota anterior, conforme a política global de URLs durante o desenvolvimento.

## 2026-10-28 — painel geral por acesso de moderação (ADM5)

- Criado `/painel-administrativo/conteudo` com descoberta de Views editoriais e allowlist de coleções servidas por controllers.
- O painel só apresenta rotas cujo acesso nativo está permitido; sem uma coleção visível, o dashboard também nega acesso.
- Kernel verifica a área FAQ oculta/negada para usuário comum e disponível ao usuário com permissão editorial.

