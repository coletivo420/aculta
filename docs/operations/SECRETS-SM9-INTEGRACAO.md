# Secrets Manager: integração, RC e relatório de segurança (SM-5 a SM-9)

Status: **0.1.0-dev.1. Não é RC.** Esta página registra o que foi validado, o que falta e o plano de
implantação. Nenhum valor real aparece aqui.

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
| 24: loader pré-Drupal | **Pendente** (Kernel/Functional) | — |
| 25: consumidores sem regressão | **Pendente** (suíte dos módulos consumidores) | — |

Kernel e Functional: o banco de teste dedicado (`docs/operations/BANCO-DE-TESTE.md`) funciona, mas o
contêiner do `aculta_portal` exige a pilha ACULTA inteira. Pendente.

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
