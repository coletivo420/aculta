# Procedimento de trabalho dos agentes

Vale para todos os agentes (Local 1, Local 2, Local 3) e para qualquer sessão.

## 1. Uma PR, um branch
- Todo trabalho entra como commit no branch chore/aculta-m0-integridade (PR #298).
- Não criar branch de frente, não abrir PR, não criar PR nova sem ordem do responsável.
- Branches temporários só existem enquanto o commit não foi integrado e não são
  publicados como destino final.
- Publicação sempre por fast-forward. Nunca force push. Nunca reescrever histórico
  publicado.
- Antes de publicar: git fetch e conferir que o head remoto é ancestral do seu commit.

## 2. Servidor de testes = checkout servido
- O servidor de testes é o checkout /home/piradopirata/projetos/aculta.
- Esse checkout deve estar SEMPRE no head da PR, em HEAD destacado.
- Não editar arquivos rastreados no checkout servido. Toda mudança entra por commit na
  PR e chega ao checkout por git checkout --detach <head>.
- Arquivos não rastreados no checkout (node_modules, package*.json, o widget não
  versionado) não são removidos, movidos nem adicionados ao git sem ordem.
- Antes de trocar o head: git status e conferência de que os caminhos não rastreados
  não conflitam com o novo head. Conflito: parar e relatar.
- Worktrees servem para leitura e para testes temporários. Não são o destino final.

## 3. Bancos: só os existentes
- Bancos permitidos:
  - aculta_runtime: banco do servidor de testes. Não escrever, não migrar, não
    importar config, sem ordem explícita do responsável e sem janela aberta.
  - aculta_simpletest: banco dos testes virtuais dos agentes, com prefixos por agente.
  - toca: pertence a outro projeto. Não tocar.
- Proibido criar banco novo, em qualquer situação.
- Proibido DROP DATABASE sem ordem explícita do responsável para aquele banco.
- Proibido site:install em qualquer banco que tenha tabelas.
- Proibido alterar grants sem ordem explícita.
- Conexão administrativa: usar o caminho autorizado pelo responsável
  (sudo -u mysql mariadb). Não ler credenciais de secrets/ e não colar hash ou senha em
  relatório.
- Antes de qualquer ação em banco: confirmar o nome e a contagem de tabelas. Se não
  bater com o registro, parar.

## 4. Gates antes de qualquer publicação
- Lista fixa, no head que será publicado: git diff --check; php -l nos PHP alterados;
  phpcs com zero avisos; testes JS do que foi alterado; PHPUnit do que foi alterado,
  rodado a partir do próprio head, sem bootstrap temporário; gates do tema
  (sdc-schemas, design-foundations e teste, browser-validators, module-theme-boundary,
  foundation, shell-contract e teste).
- Gate vermelho: não publicar. Registrar a saída literal e parar.

## 5. Registro
- Toda ação que altera estado (publicação, troca de checkout, exclusão, janela) entra
  em docs/operations/ACULTA-CONTINUOUS-EXECUTION.md, como commit de docs na PR.
- Separar o que foi VERIFICADO pelo agente do que foi RELATADO pelo responsável.
- Quando não for possível verificar, escrever "não verificado". Nunca completar com
  suposição.

## 6. Proibido sem ordem explícita do responsável
- Merge na main.
- Exclusão de branch ou de banco.
- updatedb, config:import ou pm:uninstall em aculta_runtime.
- Alteração de secrets/ ou de credencial.
- Abertura ou fechamento de PR.
- Tag de versão de estado não validado.

## 7. Relatório
- Sempre: SHAs antes e depois, arquivos alterados, saídas literais dos comandos, HEAD
  do checkout servido e o que ficou pendente.
- Se algo não estava na ordem, dizer antes de fazer, não depois.
