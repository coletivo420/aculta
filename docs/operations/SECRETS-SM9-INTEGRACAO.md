# Secrets Manager: integração, RC e relatório de segurança (SM-5 a SM-9)

Status: **0.1.0-dev.1. Não é RC.** Esta página registra o que foi validado, o que falta e o plano de
implantação. Nenhum valor real aparece aqui.


## Reexecução na PR #298 — 2026-10-26

- Unidade integrada: Unit Portal + Secrets 384 testes / 1.008 assertions PASS; consumidores Mailer, Statistics, CPF, Mercado Pago e Melhor Envio 403 / 1.100 PASS. Runner com autoloader explícito do worktree, necessário porque `vendor`/Core do worktree apontam ao checkout compartilhado.
- `SecretsRoutesKernelTest`: 1 teste / 4 assertions PASS em MariaDB `aculta_simpletest`, sem escrita no Runtime.
- PHPCS Drupal/DrupalPractice e PHP lint de `aculta_secrets_manager/src` e `tests` PASS. `validate-secrets-contract.php` e aliases de rota PASS. Gate admin Domain: 3.504 checks PASS via Drush na URI MAIN, somente leitura; esse gate observa o Runtime ativo, não demonstra deploy da branch.
- Drush read-only na URI MAIN reportou `aculta_secrets_manager` Enabled em `0.1.0-dev.1`. Nenhuma gravação no Runtime ocorreu.
- Os testes Firefox ESR (16 verificações de tela, 7 de popups) e HTTP autenticado (9 verificações) registrados abaixo foram executados na branch de origem. Não foram reexecutados na integração atual. Functional com sessão descartável, instalação limpa e rollback permanecem pendentes.

## SM-5: armazenamento e bootstrap

Preservado sem alteração de código:

- Loader pré-Drupal `web/sites/default/aculta.secrets.php`: continua fora do submódulo, sem depender
  de Drupal para inicializar. Exige caminho absoluto, arquivo fora do document root e permissões sem
  escrita de grupo e sem acesso de outros.
- Drupal Key com provider `env`, precedência das variáveis nativas e contrato JSON
  (`config/secrets-contract.json`).
- Escrita atômica do arquivo de credenciais (arquivo temporário, `fsync`, `rename`, modo `0640`).

Decisão: o modo `database` continua sem armazenamento próprio. O painel recusa gravar nele, e o
provisionamento segue com o ACULTA Deployer. Se for preciso um cofre criptografado persistente, a
decisão exige ADR separada. Nenhuma credencial foi migrada, rotacionada ou copiada entre ambientes.

## SM-7: matriz de testes

| Cenário | Cobertura | Onde |
| --- | --- | --- |
| 1, 2: aba correspondente ao ambiente ativo | Unit | `SecretsTabsTest` |
| 4: mesma variável, valores distintos | Unit | `SecretsManagerTest` |
| 5, 6: sem fallback entre ambientes | Unit | `SecretsFallbackAndRoutesTest` |
| 7, 8: aba do outro ambiente sem valor, máscara ou estado real | Unit | `SecretsTabsTest` |
| 9, 10, 11: editar, revelar e apagar fora do escopo negados | Unit | `SecretsTabsTest`, `SecretsManagerTest` |
| 12, 13, 14: permissão, anônimo e CSRF nas rotas | Unit (arquivo de rotas) | `SecretsFallbackAndRoutesTest` |
| 16: sem cache | Unit | `SecretsResponseSubscriberTest` |
| 15: segredo fora do HTML inicial | Unit (estrutura de render) | `SecretsTabsTest` |
| 17: contrato inválido | Gate | `scripts/validate-secrets-contract.php` |
| 19: escrita atômica | Unit (existente) | `SecretsManagerTest` |
| 3, 21, 22, 23: troca de aba sem alterar ambiente, AJAX, teclado, mobile | **Pendente** (browser) | — |
| 18, 20: arquivo inseguro, falha parcial | Parcial (regras existentes do loader) | — |
| 24: loader pré-Drupal | **Pendente** (Functional) | — |
| 25: consumidores sem regressão | Unit das suítes consumidoras: mailer (162), estatística (108), id_cpf (47), Mercado Pago (11), Melhor Envio (73), todas OK. Portal: 366 testes, 1 erro fora do escopo (`AccountOtherDataStudioReturnTest`, de outro trabalho, sem segredos) | `SecretsRevealTest` (SM-4, revelação com valor fictício) |

Kernel de rotas e permissões: passa no banco de teste dedicado (`docs/operations/BANCO-DE-TESTE.md`), com a
lista de módulos do `aculta_portal` mais `language`, `locale`, `text` e a pilha de commerce/LMS. Functional: pendente.

## SM-8/SM-9: plano de implantação

1. Deploy do código com `aculta_secrets_manager` presente. Só vai para produção depois que todos os
   módulos, temas e subtemas estiverem em RC (regra do responsável).
2. Habilitar `aculta_secrets_manager` depois do deploy, no ambiente alvo.
3. Conferir `/painel-administrativo/aculta/segredos`: abas, estado, sem valores, sem links quebrados.
4. Rollback: `drush pmu aculta_secrets_manager`. Os links do Portal para `aculta_portal.secrets_*`
   passam a falhar enquanto o módulo estiver desabilitado, por isso ele fica habilitado junto com o Portal.

Matriz de consumidores: `docs/operations/SECRETS.md` (seção "Painel por ambiente").

## Relatório de segurança (resumo)

- Escopo por ambiente no backend (núcleo), não só na interface.
- Sem fallback entre ambientes; sem cópia ou sincronização de valores.
- Revelação por clique com token CSRF, sem cache (`no-store`), registrada no log com nome e usuário,
  sem o valor.
- Nenhum valor nos logs, nas URLs, no Config Sync ou no HTML inicial (verificado no servidor de testes
  por leitura, sem imprimir valores).
- Pendências de segurança para RC: permissão única (decidido manter nesta versão), revisão de Form API
  nos popups no browser, teste funcional de CSRF com sessão real.

## Critérios de RC (ainda não atendidos)

- Kernel e Functional verdes no banco de teste dedicado.
- Revisão visual no Firefox ESR (DISPLAY=:0), larguras de celular, teclado, com Olivero e ACULTA420.
- Suíte dos módulos consumidores sem regressão.
- Revisão de código e de segurança concluída.

## Conta de teste para o painel (procedimento)

Procedimento seguido em 2026-10-26, conforme `docs/operations/TESTE-COMUM.md` e `docs/operations/TEST-DATA.md`:

1. A senha fica em arquivo local de permissão 600 fora do Git (`~/.config/aculta-homelab/`). Nunca é
   impressa nem colada em documento, commit ou saída de comando.
2. A conta é criada por script do Drush guardado fora do repositório, com e-mail no domínio reservado
   `@example.invalid` e papel `administrator`, que é o único com `administer aculta secrets`.
3. O login é feito por link de uso único (`drush uli --uid=<uid> --uri=https://conta.aculta.toca.net.br`).
   Esse link redireciona para o formulário de troca de senha, mas a sessão já fica ativa. Confirmado pelo
   acesso ao painel no host principal.
4. Ao final da rodada, remover ou desativar a conta de teste conforme `TEST-DATA.md`.

Observação: a conta criada recebeu, além de `administrator`, os papéis `content_editor`, `lms_teacher` e
`gerente_loja`, que não foram pedidos. Confirmar a origem (hook de atribuição automática) antes de reutilizar a conta.

Verificação por HTTP no servidor de testes (sem navegador): aba de produção sem botões, aba de testes ativa
com botões, nenhum valor real no HTML, cabeçalhos `no-store`.

Revisão no Firefox ESR (DISPLAY=:0), servidor de testes: `tests/browser/aculta-secrets-manager/painel-visual.py`,
16 verificações passaram: duas abas, aba do ambiente ativo selecionada, botões só na aba de testes, troca de aba sem
alterar o texto do ambiente, teclado (setas e Home), sem rolagem horizontal da página e tabela dentro da janela em
1280, 390 e 320 px. Com o tema Olivero ativo (troca temporária, restaurada ao final): 16 de 16 verificações visuais e 7 de 7 de popups (alterar e apagar abrem e fecham sem gravar).

Verificação HTTP com sessão real no servidor de testes (`tests/browser/aculta-secrets-manager/painel-http.py`), com aculta420 e com Olivero: 9 de 9. Substitui o SecretsPanelFunctionalTest, que dependia de site temporário sem cookie compartilhado nem domínios de produção. O caminho de sucesso da revelação (escape do valor, `no-store`, log só com o nome) é coberto por `SecretsRevealTest`, com valor fictício. Não se revela valor real no servidor de testes.
