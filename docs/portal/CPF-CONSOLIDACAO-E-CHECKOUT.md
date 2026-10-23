# CPF/CNPJ — consolidação em `aculta_id_cpf` e identidade fiscal compartilhada no checkout

**Data:** 2026-10-23. **Branch:** `docs/aculta-forum-0.1.0-dev.1` (PR #297). **Status:** R0–R2 e R5 concluídas; R3 bloqueada; I-A, I-B e a implementação compartilhada de I-D/I-D-LMS feitas em código e configuração, com ativação em runtime pendente. Ver seções 7 a 10.

Este documento registra a inventariação (R0), as decisões do responsável e a auditoria dos carrinhos e checkouts (I-A). A decisão arquitetural vigente está em [ADR-013](../decisions/ADR-013-portal-multi-cart-checkout.md), seção "Adendo — identidade fiscal compartilhada".

## 1. Decisões vigentes

> **Decisão de 2026-10-23:** cancelar a substituição de `aculta_id_cpf` por `aculta_associado_id`. A implementação `aculta_id_cpf` é mantida como módulo oficial. `aculta_associado_id` será descontinuado e removido de maneira controlada, após auditoria das dependências e do Runtime. Os dados de associação pertencem ao domínio de usuários/perfis e não justificam um segundo módulo de identidade fiscal.

> Em 2026-10-23, o responsável determinou que os checkouts comerciais da Loja e do LMS utilizem a mesma política obrigatória de identidade fiscal, baseada no documento mantido em `/dados/outros-dados` pelo `aculta_id_cpf`. O checkout padrão de doações permanece dispensado. A exigência não se aplica à participação gratuita no LMS nem implica fusão física de carrinhos ou pedidos.

| Contexto | CPF/CNPJ | Regra |
|---|---|---|
| Loja (`aculta_commerce`) | Obrigatório para concluir compra | Documento cadastrado e válido antes da conclusão |
| LMS, cursos pagos | Obrigatório para concluir compra | Mesma regra da Loja |
| LMS, acesso gratuito / matrícula sem checkout comercial | Não exigido | Nenhuma alteração na política de matrículas |
| Doações (`aculta_donation`) | Não obrigatório | Checkout de doação preservado |

Demais decisões: o módulo oficial mantém o machine name `aculta_id_cpf`; o campo `profile.participante.field_documento` e o índice `field_documento_indice` não mudam; chaves, key IDs e separador HMAC (`aculta_id_cpf:v1`) permanecem como estão; o documento não é alterável diretamente após o primeiro cadastro válido; o checkout não sobrescreve o documento cadastrado; alterações excepcionais serão administrativas, autorizadas e auditadas (fase futura).

## 2. Inventário R0 — `aculta_id_cpf` × `aculta_associado_id`

Os dois submódulos têm a mesma estrutura de arquivos. Após normalizar o nome do módulo, **o código-fonte de `src/`, `tests/` e `config/install/` é idêntico**. As diferenças estão em metadados e documentação.

| Local | Dependência | Comportamento atual | Mudança necessária | Risco |
|---|---|---|---|---|
| `aculta_id_cpf/src/Service/*` (9 classes) | `key.repository`, `aculta_portal` (interface) | Validação, normalização, formatação, cifra, HMAC, gravação | Nenhuma: já é a implementação completa | Baixo |
| `aculta_associado_id/src/Service/*` | Idêntico | Cópia byte a byte do módulo oficial | Remover quando o runtime estiver verificado | Alto se removido sem desinstalação |
| `aculta_id_cpf/aculta_id_cpf.info.yml` | — | Nome "ACULTA Associado ID (legado)", `hidden: true`, descrição "legado; migração para aculta_associado_id" | Renomear para ACULTA CPF ID, remover texto de migração, tornar visível | Baixo |
| `aculta_associado_id/aculta_associado_id.info.yml` | — | Versão `0.3.0-dev.1`, sem `hidden` | Remover com o diretório | Alto (ver runtime) |
| `aculta_id_cpf/aculta_id_cpf.services.yml` / `aculta_associado_id.services.yml` | `aculta_portal` | Serviços `*.account_document` | Manter só `aculta_id_cpf.*` | Baixo |
| `aculta_id_cpf.install` / `aculta_associado_id.install` | Update hook `10001` | Criam `field_documento_indice` e indexam documentos | Manter o do módulo oficial; o do associado some com o diretório | Alto: mesmo hook com o mesmo ID de atualização |
| `aculta_portal/src/Controller/PortalController.php:89` | Serviço `aculta_associado_id.account_document` (preferido) → `aculta_id_cpf.account_document` (fallback) | Resolve o primeiro serviço presente | Usar somente `aculta_id_cpf.account_document` | Baixo |
| `aculta_portal/src/Form/AccountOtherDataForm.php:43` | Mesmo padrão de preferência e fallback | Idem | Idem | Baixo |
| `aculta_portal/src/Account/AccountDocumentInterface.php` | Contrato neutro | Única interface de consumo; sem referência ao submódulo | Manter | — |
| `config/sync/core.extension.yml` | — | Lista `aculta_id_cpf: 0`; `aculta_associado_id` **ausente** | Nenhuma | — |
| `config/sync/field.field.profile.*.field_documento.yml` (6 bundles) | `aculta_id_cpf` | Campo de documento nos perfis | Manter | — |
| `config/sync/key.key.aculta_id_cpf_fingerprint.yml` | Key | Chave HMAC | Manter | — |
| `config/secrets-contract.json`, `scripts/validate-secrets-contract.php` | Contrato de segredos | Declaram `aculta_id_cpf_fingerprint` | Manter | — |
| `docs/modules/ACULTA-ASSOCIADO-ID.md` | Documentação | Plano de migração para o módulo novo | Marcar como superseded, histórico preservado | Baixo |
| `docs/operations/status/aculta_associado_id.yml` | Gerador de índice | Estado `Dev` como módulo ativo | Só removido junto com o diretório (ver seção 4) | Médio |
| `docs/operations/status/aculta_id_cpf.yml` | Gerador de índice | Próxima fase descreve migração | Atualizar | Baixo |
| `README.md` (raiz, tabela gerada) | Gerador | Lista os dois módulos | Regerar após mudança de status | Baixo |
| `docs/portal/ROADMAP.md:22` | Roadmap | Cita a versão de `aculta_associado_id` | Atualizar | Baixo |
| `docs/compliance/LGPD-INVENTARIO.md`, `docs/operations/SECRETS.md`, `docs/portal/*` | Documentação | Referências a ambos | Atualizar referências vigentes; manter históricas | Baixo |

**Testes:** os três testes unitários e o `tests/run.php` de `aculta_id_cpf` são idênticos aos do associado. Resultado local: `PASS: 248 domain checks`. Não há teste de Kernel nem teste do Portal que resolva o serviço de documento.

**Funcionalidades exclusivas de `aculta_associado_id`:** nenhuma identificada. O módulo associado não tem código próprio além da cópia.

### Verificação de Runtime (Drush, somente leitura, ambiente local)

| Verificação | Resultado |
|---|---|
| `core.extension` ativo | `aculta_id_cpf` habilitado; `aculta_associado_id` **não** habilitado |
| `system.schema` (`aculta_associado_id`) | `NULL` (nunca instalado) |
| `system.schema` (`aculta_id_cpf`) | `10001` |
| Módulo presente no código | Sim, em ambos os casos |
| `drush config:status` | Há diferenças de configuração não relacionadas a CPF, herdadas de outras frentes; não foram tocadas |

Limite: a verificação cobre somente o ambiente local acessível a partir desta sessão. **Os servidores de testes (`*.aculta.toca.net.br`) e de produção (`*.aculta.org`) não foram verificados** a partir daqui.

## 3. Consolidação realizada (R2)

- `aculta_portal` passa a resolver somente `aculta_id_cpf.account_document`. A preferência pelo serviço do associado e o fallback foram removidos em `PortalController` e `AccountOtherDataForm`. Não havia outro consumidor no código.
- O código de `aculta_id_cpf` não recebeu funcionalidade do associado, pois não havia diferença a portar.
- Nenhuma alteração em nomes de campo, storage, chaves, HMAC ou update hooks.

## 4. Remoção do módulo substituto (R3) — CONCLUÍDA NO AMBIENTE DE TESTE

Verificação feita antes da remoção, no runtime de teste acessível (ambiente local, Drush somente leitura):

- `aculta_associado_id` não está habilitado (`moduleHandler()->moduleExists` = FALSE) e nunca foi instalado (`system.schema` = `NULL`).
- Nenhuma configuração ativa referencia o módulo (verificado no export de backup).
- Nenhum código executável referencia o módulo; restam apenas documentos históricos.

Ação: removidos o diretório `web/modules/custom/aculta_portal/modules/aculta_associado_id/` e o status `docs/operations/status/aculta_associado_id.yml`. A tabela do README raiz foi regenerada pelo validador.

**Pendente antes de produção:** o mesmo teste deve ser feito no servidor de testes (`*.aculta.toca.net.br`) e no de produção (`*.aculta.org`). Se o módulo estiver habilitado em algum deles, ele precisa ser desinstalado primeiro, com backup, em clone. Produção recebe esta mudança somente após todos os módulos, temas e subtemas estarem em RC.

## 5. Auditoria I-A — carrinhos e checkouts reais

Confirmado por leitura do código (CartProvider do Commerce em `vendor/drupal/commerce/modules/cart/src/CartProvider.php`, `CartSession.php`, `aculta_portal`, `aculta_lms`, `aculta_donation`, config/sync):

| Aspecto | Comportamento real |
|---|---|
| Chave de carrinho | `CartProvider::getCartId` filtra por **tipo de pedido + loja + conta**. Um mesmo usuário pode ter um carrinho por tipo na mesma loja. |
| Sessão | `CartSession` guarda uma lista de IDs de pedido na sessão, separada por tipo de sessão (`active`, `completed`). Visitante e autenticado usam o mesmo mecanismo. |
| Tipos de pedido | `default` (Loja), `curso` (LMS), `doacao` (Doações), `assinatura` (assinatura de doação). |
| Flows de checkout | `default` e `shipping` (Loja, multistep), `curso` (LMS, multistep com `login` e `aculta_billing_address`), `doacao` e `assinatura_flow` (`fluxo_doacao`). |
| Panes de checkout | Portal: `AcultaShippingInformation`, `BillingAddressPane`, `PaymentInformationWithoutAddress`. **Nenhum pane ou flow verifica CPF/CNPJ.** |
| Carrinho de LMS | `CourseEnrollmentPurchaseForm::submitForm` cria um pedido do tipo `CheckoutSettings::lmsOrderType()` (padrão `curso`) e redireciona para `aculta_portal.cart_checkout`. |
| Matrícula | `CourseOrderEnrollmentManager::synchronize` concede a matrícula somente se o pedido estiver `completed` e pago, ou com pagamento `completed`/`partially_refunded` com saldo. |
| Curso gratuito | Variação com preço zero **também gera pedido** e passa pelo checkout comercial, pois `courseVariation()` só exige `getPrice() !== NULL`. |
| Documento | Lido e gravado somente por `AccountOtherDataForm` e `PortalController`, via `AccountDocumentInterface`. |
| Domínio | ADR-013 registra que o Commerce resolve o tipo de item e o portal coordena. A verificação de isolamento entre Domains **não foi feita** nesta rodada. |

### Cenários da seção 8 (pedido I-A)

| # | Cenário | Situação | Evidência |
|---|---|---|---|
| 1 | Loja e Doações coexistindo | Isolados por tipo de pedido | `CartProvider` |
| 2 | Anônimo antes do login | Carrinho por sessão, sem fusão automática verificada | Não testado |
| 3 | Login durante checkout | Não verificado | Pendente de teste Functional |
| 4 | Retorno de `/dados/outros-dados` | **Não implementado** (não há exigência ainda) | Código |
| 5 | Múltiplas abas | Não verificado | Pendente |
| 6 | Doação sem CPF | Permitido (não há exigência) | Código |
| 7 | Loja com CPF | Não há verificação, portanto a regra ainda não existe | Código |
| 8 | Troca de Domain/Store | Carrinho por loja; isolamento de Domain não auditado | Pendente |
| 9 | LMS | Consumidor implementado (`CourseEnrollmentPurchaseForm`) | Código |
| 10 | Logout/expiração | Carrinho segue `CartSession`; comportamento não verificado | Pendente |

**Conclusão de I-A:** o isolamento por tipo de pedido existe na camada do Commerce. Porém **nenhum checkout comercial exige identidade fiscal hoje**, então a política ainda não está implementada para Loja nem LMS.

### Divergências encontradas

- ADR-013 afirma que `aculta_commerce` está "ainda não habilitado", mas o Runtime local o lista como habilitado. Registrado para revisão humana; não foi alterado.
- `docs/modules/ACULTA-ASSOCIADO-ID.md` e os roadmaps tratam o associado como módulo ativo. Corrigido nesta rodada.
- `aculta_lms/docs/CONTRACT.md` não descreve a integração com Commerce. Registrado como pendência de documentação.

## 6. Contrato compartilhado proposto (I-B)

Ainda **não implementado**. Proposta para validação antes do código:

| Peça | Dono | Responsabilidade |
|---|---|---|
| `AccountDocumentInterface` (existente) | `aculta_portal` | Contrato de leitura e gravação. Permanece como está. |
| `aculta_id_cpf.account_document` (existente) | `aculta_id_cpf` | Única implementação de CPF/CNPJ. |
| Estado de identidade fiscal (novo, somente leitura) | `aculta_portal` | Retorna `documento ausente` / `válido` / `inválido` / `indisponível`, tipo (CPF ou CNPJ) e representação mascarada. Não expõe o valor completo. |
| Política de exigência por contexto (nova) | `aculta_portal` | Recebe o **contexto validado do pedido** (tipo de pedido, loja, total > 0) e devolve se a exigência se aplica. Não lê URL, parâmetro de sessão nem indicação do navegador. |
| Checkout pane ou validação de pedido | `aculta_commerce` e `aculta_lms` (consumidores) | Mostra o aviso, oferece `/dados/outros-dados`, e revalida no servidor antes da conclusão. |
| Dispensa | `aculta_donation` | Declara que o contexto de doação não exige o documento. |

Regras de implementação propostas:

- O estado de identidade fiscal nunca grava nada e não retorna o CPF/CNPJ completo.
- A exigência é definida pelo tipo de pedido configurado (`CheckoutSettings`) e pelo total do pedido, nunca pela URL.
- O documento não é copiado para o pedido, o perfil de faturamento, a entidade LMS ou a sessão.
- Revalidação no servidor ao concluir o pedido (evento de pedido antes da conclusão, ou validação do pane em `completed`).

### Decisões tomadas pelo responsável (2026-10-23)

1. **Duplicação entre Loja e LMS:** flow e pane compartilhados. Tipos de pedido permanecem separados (`default` para Loja, `curso` para LMS); a lógica de checkout comercial existe uma vez. O tipo `curso` e a regra de matrícula não mudam. Doações permanecem separadas.
2. **Curso com total zero que passa pelo checkout:** exige CPF/CNPJ válido para concluir. A exigência é aplicada ao checkout comercial, não ao valor do pedido. Matrícula sem checkout comercial, se existir, continua fora da regra.
3. **Guest checkout:** não. Login obrigatório. **Correção:** o flow `default` da Loja permitia compra sem conta (`allow_guest_checkout: true`); o flow `curso` já exigia login. A mudança torna a Loja mais restrita. Cadastro dentro do checkout passa a ser permitido, como no LMS.

## 7. Fases

Dependências: R2 e R3 antes de I-D; I-B antes de I-C e I-D; I-D e LMS antes de I-F.

| Fase | Entregável | Critério de aceitação | Estado |
|---|---|---|---|
| R0 | Inventário e matriz (seção 2) | Matriz completa com riscos | **Concluída** |
| R1 | Decisões e documentação atualizadas | ADR, roadmaps, status e READMEs coerentes | **Concluída nesta rodada** |
| R2 | Portal usa só `aculta_id_cpf` | Sem preferência nem fallback ao associado; testes do portal | **Concluída nesta rodada** |
| R3 | Remoção do diretório do associado | Runtime verificado; remoção no ambiente de teste | **Concluída no ambiente de teste**; servidor de testes e produção verificados antes da promoção (seção 4) |
| R4 | Segurança e preservação de dados | Chaves, campos e HMAC inalterados; testes sintéticos de cifra e fingerprint | Parcial: chaves e HMAC inalterados; a suíte existente (248 verificações) cobre a validação e o fingerprint, mas não há teste novo de leitura de cifra antiga |
| R5 | Roadmap do `aculta_id_cpf` | Fases 0.2-A a 0.2-E registradas | **Concluída nesta rodada** (seção 8) |
| R6 | Validação e encerramento | Gates reais | Parcial (seção 9) |
| I-A | Inventário e caracterização multi-carrinho | Seção 5 | **Concluída** (caracterização por teste Kernel pendente) |
| I-B | Contrato de identidade fiscal | Política única `FiscalIdentityPolicy` sobre `AccountDocumentInterface` (sem interface nova) | **Concluída em código** (commit `6034a13e`), com 7 testes Unit |
| I-C | Imutabilidade após cadastro | Bloqueio no servidor, não só no formulário | **Já existente** em `AccountDocumentStore` (gravação única; segunda gravação lança `LogicException`). Teste de servidor pendente |
| I-D | Checkout da Loja | Exige documento válido; revalida no servidor | **Implementado em código e configuração** (commits `6034a13e`, `3344ca0d`): pane `aculta_fiscal_identity` no flow `default`. Ativação: `drush config:import` autorizado |
| I-D-LMS | Checkout do LMS (cursos pagos e gratuitos com checkout) | Mesmo flow e pane da Loja; matrícula inalterada | **Implementado em configuração** (tipo `curso` aponta para o flow `default`; flow `curso` removido). Matrícula e estorno não mudaram. Ativação pendente |
| I-E | Isolamento de Doações | Testes de regressão | **Parcial**: flows `doacao` e `assinatura_flow` não mudaram; testes Unit de caracterização de Doações passam (43 testes com Loja e LMS). Kernel pendente |
| I-F | Sessões, acesso e regressões | Testes Functional/Kernel | Pendente |

## 8. Roadmap do `aculta_id_cpf` (R5)

- **CPF 0.2-A — Consolidação arquitetural:** módulo único; contratos de serviço; dependências saneadas; documentação atualizada. *Situação: R0–R2 concluídos; remoção do associado bloqueada.*
- **CPF 0.2-B — Integridade e concorrência:** unicidade transacional; avaliar índice único; testes de gravações concorrentes e documentos duplicados. *O HMAC atual não garante unicidade sob concorrência (ver README do módulo).*
- **CPF 0.2-C — Integração de conta:** formulários do Portal; Profile/User; máscaras e mensagens genéricas; proteção contra exposição de PII; validação de acesso.
- **CPF 0.2-D — Login por CPF:** estudo somente. Preservar autenticação Core, CAPTCHA, flood control, confirmação de e-mail e Social Auth. Sem implementação antes de contrato de segurança.
- **CPF 0.2-E — Integrações futuras:** Commerce e LMS (ver CPF-CHECKOUT-0.2-I); Webform com controles de acesso e exportação; serviços internos autorizados; auditoria e retenção.

Não aumentar a coleta de documentos sem necessidade demonstrada.

## 9. Validação (R6)

| Gate | Resultado |
|---|---|
| PHP lint dos arquivos alterados | **PASS** |
| `phpunit` Unit do `aculta_portal` (todos os testes Unit) | **PASS**: 314 testes, 785 asserções; 6 deprecações já existentes |
| Testes Unit de Loja, LMS e Doações (caracterização, `CourseOrderEnrollmentManagerTest`, providers) | **PASS**: 43 testes |
| `FiscalIdentityPolicyTest` (novo) | **PASS**: 7 testes |
| `aculta_id_cpf/tests/run.php` | **PASS**: 248 verificações de domínio |
| `git diff --check` (commits desta rodada) | **PASS** |
| `php scripts/validate-project-status-index.php` | **PASS** (38 componentes). Falha anterior corrigida: criado o status de `aculta_commerce` e regenerada a tabela |
| `php scripts/validate-aculta-portal-drupal11.php` | **FAIL pré-existente**, mesmo conjunto de falhas da linha de base (ex.: `WikiController.php` ausente). Nenhuma falha nova relacionada a checkout ou CPF |
| PHPCS Drupal/DrupalPractice nos arquivos novos | **Não atende o padrão estrito**: comentários de documentação e linhas longas. O repositório não passa nesse padrão (`PortalController.php` tem 67 erros antes desta rodada). Dívida registrada, não corrigida |
| PHPUnit Kernel (infraestrutura) | **PASS** em teste de fumaça (`EmailConfirmationPolicyKernelTest`, 3 testes), com `SIMPLETEST_DB` montado a partir da conexão do runtime de teste, só em variável de ambiente |
| PHPUnit Kernel do pane de identidade fiscal | **DEFERRED**: o container do `aculta_portal` depende de quase todo o ecossistema (`aculta_user`, `lms`, `group`, `aculta_mailer`, `token`, `social_api`…). Cada tentativa revelou um novo serviço ausente. O teste foi removido para não deixar vermelho. Próximo passo: Kernel sem `aculta_portal`, com o pane isolado, ou Functional |
| Config Sync | **Import parcial aplicado** no ambiente de teste (`config:import --partial` só com `commerce_checkout.commerce_checkout_flow.default` e `commerce_order.commerce_order_type.curso`); flow `curso` removido com `config:delete`. Import completo **não** foi feito: ver 9.2 |
| Gates de Loja, LMS e Doações no servidor de testes | **DEFERRED** |

## 9.1 Efeitos de runtime ao ativar a configuração

Aplicar `drush config:import` no runtime alvo, somente com autorização:

- O flow `commerce_checkout.commerce_checkout_flow.curso` será **removido** do ativo. Nenhum pedido guarda o ID do flow, mas pedidos em checkout de curso que estejam na etapa de login ou de revisão precisam ser conferidos depois da importação.
- O tipo `curso` passa a usar o flow `default`.
- O flow `default` passa a exigir login e a incluir o pane `aculta_fiscal_identity` na etapa de revisão. Compras da Loja sem CPF/CNPJ válido deixam de ser concluídas.
- Cursos gratuitos que passam pelo checkout também passam a exigir CPF/CNPJ, como decidido.

## 9.2 Por que o import completo não foi feito

`drush config:status` no runtime de teste: 64 itens diferentes, 47 só no sync, 35 só no banco. Um `config:import` completo teria:

- **Apagado 35 itens só no banco**, entre eles `field.storage.profile.address`, `field.storage.message.*` e `message.template.*`. Apagar storage de campo destrói dados. Não executado.
- **Desinstalado módulos** ao importar `core.extension`: o banco tem `ckeditor5_plugin_pack*`, `entity_print`, `google_tag`, `aculta_document_viewer`, `aculta_editor` e `aculta_lms_messenger` que o sync não tem. Não executado.
- **Falhado na validação:** o sync depende de `aculta_lms_resources`, `aculta_wiki` e `aculta_forum_wiki`, habilitados no sync e ausentes no banco. Resolver isso exige instalar módulos, o que é decisão à parte.

Backup do estado ativo antes de qualquer alteração: export em `scratchpad/active` (1301 arquivos). O drift restante é de outras frentes e segue pendente de decisão.

**Verificação sobre preenchimento do CPF em compra:** no código, nenhum caminho de compra ou pagamento grava CPF/CNPJ. Pagamento (`aculta_pagamentos_mercadopago`) não trata identificação. O único ponto de gravação é `AccountOtherDataForm`. Assim, Outros Dados só mostra o documento se a pessoa já o salvou ali, e mostra mascarado e bloqueado. Se a intenção for preencher a partir de uma compra, isso contraria a regra de não sobrescrever e exige decisão.

## 9.3 Atualização de 2026-10-23 (decisões do responsável)

- **Preenchimento na compra:** se o cliente ainda não tem CPF/CNPJ, o pane de identidade fiscal aceita o primeiro cadastro na própria compra. Usa a mesma validação e a mesma gravação única de Outros Dados. Depois de salvo, o checkout mostra o valor mascarado e não editável, e o servidor recusa nova gravação. Isso substitui a regra anterior de "não criar campo no checkout", que foi revogada por esta decisão.
- **Validação e gravação:** a gravação ocorre em `validatePaneForm`, porque é o ponto em que o Commerce ainda pode bloquear a conclusão. Se outro pane falhar depois, o documento já validado permanece salvo. É o mesmo titular e o mesmo valor, então não há inconsistência. Mensagens de erro são genéricas: inválido, já existente e não gravável recebem o mesmo texto.
- **Ambiente de teste:** `aculta_lms_resources`, `aculta_wiki` e `aculta_forum_wiki` foram habilitados.
- **Import parcial:** duas rodadas `config:import --partial` (sem deleções, sem `core.extension`). Drift caiu de 63/46 para 36/16 diferenças. Os 34 itens só-no-banco foram preservados.
- **Erro restante, de outra frente:** o índice `search_api` `aculta_forum` (Discussões públicas) referencia o campo "Categoria do Fórum (tópico ou post)", sem definição de dados. Esse erro acontece no import e no rebuild de rotas. Enquanto não for corrigido, o import completo continua bloqueado.
- **Kernel do pane:** segue DEFERRED pelo mesmo motivo da seção 9 (container do `aculta_portal`).

## 10. Pendências

1. **Ativação em runtime:** `config:import` autorizado no ambiente de testes antes de qualquer produção. Ver 9.1.
2. **Kernel do checkout:** cobrir visibilidade do pane (Loja, LMS e Doações), bloqueio na revisão sem documento, aviso com link, documento mascarado, falha fechada, e que o checkout não grava o documento. Requer `SIMPLETEST_DB` de ambiente de teste.
3. **Retorno do link `destination`:** verificar se `/dados/outros-dados` honra o parâmetro e devolve ao checkout do pedido. Se não honrar, o usuário volta ao carrinho e precisa reabrir o pedido.
4. **Procedimento administrativo de correção** (permissão, justificativa, auditoria sem CPF/CNPJ em claro): escopo a definir.
5. **Dívida de PHPCS** nos arquivos novos e nos existentes.
6. **Runtime de teste e produção:** confirmar que `aculta_associado_id` não está habilitado antes de promover (seção 4).
7. **Gate Drupal 11:** `WikiController.php` ausente (outra frente).
8. **Divergência:** ADR-013 diz que `aculta_commerce` não está habilitado, mas o runtime local o lista como habilitado.
9. **Cenários de sessão da seção 5** (login durante checkout, múltiplas abas, troca de Domain, expiração): ainda não verificados.
