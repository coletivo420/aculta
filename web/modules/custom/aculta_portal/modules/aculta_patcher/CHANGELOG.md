# Changelog — aculta_patcher

## 0.1.0-dev.4 — rolagem horizontal na tabela administrativa

- A tabela de registros é envolvida por contêiner rolável em viewports estreitos;
  estilo carregado apenas pela listagem do Patcher.
- Unit 38/65, lint 12/12 e PHPCS Drupal passaram. O Firefox autenticado anterior
  identificou overflow da tabela em 390/320px; repetir a validação após o cache
  rebuild do candidato no servidor de teste.


## 0.1.0-dev.3 — proteção da raiz de artefatos — 2026-10-26

- O exportador recusa diretórios `patches/` e `patches/aculta_patcher/` que resolvam por symlink para fora da raiz do projeto.
- Adicionado teste Unit de regressão para a fronteira e para exportação vazia local; não executa Composer nem Runtime.

## 0.1.0-dev.2 — aviso do formulário por token — 2026-10-19

- O aviso de "estado ativo" do formulário de patch usa `data-portal-ui="notice-warning"` no lugar de classes `messages`. Sem mudança de texto nem de comportamento.
- RUNTIME STATUS: DEFERRED.

## Não lançado — 0.1.0-dev.1

- Registro de patches como entidade de configuração com formulário Drupal/Claro,
  permissão administrativa restrita, status para o próximo build e exclusão
  confirmada.
- Comando Drush exporta somente registros ativos depois de validar versão no
  `composer.lock`, encontra o caminho instalado com `Composer\InstalledVersions`
  e valida compatibilidade com `git apply --check` em cópia temporária.
- Mantido um patch Composer para o upload AJAX do editor H5P; o upload do
  widget de campo está desabilitado no fork até integrar imagens com `aculta_image`. O exportador resolve
  pacotes por `Composer\InstalledVersions` e valida o patch em cópia temporária.
- RUNTIME STATUS: `aculta_patcher` habilitado no Runtime de teste após backup privado
  (0600); o registro obsoleto do pacote Drupal H5P foi removido. O patch do editor
  permanece registrado; H5P segue desativado e não foi habilitado no Runtime.
