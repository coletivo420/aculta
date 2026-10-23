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
| Domínio | ADR-013 registra que o Commerce resolve o tipo de item e o portal coordena. A troca de Domain foi verificada no navegador em 2026-10-23 (cenário 8 e 9.6); o isolamento entre lojas segue coberto em Kernel por `CartIsolationKernelTest`. |

### Cenários da seção 8 (pedido I-A)

| # | Cenário | Situação | Evidência |
|---|---|---|---|
| 1 | Loja e Doações coexistindo | Isolados por tipo de pedido | `CartProvider` |
| 2 | Anônimo antes do login | **PASS (navegador, 2026-10-23):** carrinho anônimo por sessão; o login não funde nem troca o pedido | Pedido 117: produto no host da loja, carrinho em `aculta.toca.net.br/carrinho`, cookie `SSESS…` de domínio `.aculta.toca.net.br`; depois do login, mesmo pedido e mesmo item |
| 3 | Login durante checkout | **PASS (navegador, 2026-10-23):** login no próprio pane avança o mesmo pedido | Pedido 119: `/carrinho/finalizar` → `/finalizar-compra/119/entrar`; login em `#edit-login-returning-customer-*` → `/finalizar-compra/119/informacoes-do-pedido?check_logged_in=1` |
| 4 | Retorno de `/dados/outros-dados` | **Implementado:** só aceita destino interno sob `/checkout/`; senão, volta ao próprio formulário | `AccountOtherDataForm::returnUrl`, commits `d46f39e8d` e `c729daafc` |
| 5 | Múltiplas abas | **Não exercitado** | A sessão é única por cookie de domínio `.aculta.toca.net.br`, mas duas abas simultâneas não foram testadas |
| 6 | Doação sem CPF | Permitido (não há exigência) | Código |
| 7 | Loja com CPF | **PASS (navegador, 2026-10-23):** o pane de identidade fiscal exige CPF/CNPJ na Loja e bloqueia a conclusão sem documento | Pedido 113: vazio e inválido permanecem em `revisao`; válido conclui (9.6) |
| 8 | Troca de Domain/Store | **PASS (navegador, 2026-10-23):** o carrinho do host da loja redireciona para o MAIN | `loja.aculta.toca.net.br/carrinho` → 302 → `https://aculta.toca.net.br/carrinho` |
| 9 | LMS | Consumidor implementado (`CourseEnrollmentPurchaseForm`) | Código |
| 10 | Logout/expiração | **PASS para logout; expiração por TTL não exercitada** | Após sair, `/finalizar-compra/119/revisao` responde 403 e `/carrinho` mostra "Seu carrinho de compras está vazio." |

Os cenários 2, 3, 8 e 10 foram re-verificados no navegador em 2026-10-23, depois da implementação do pane de identidade fiscal. Evidências completas em 9.6.

**Conclusão de I-A:** o isolamento por tipo de pedido existe na camada do Commerce. Na medição original, **nenhum checkout comercial exigia identidade fiscal**, então a política ainda não estava implementada para Loja nem LMS. *(Atualização de 2026-10-23: a política foi implementada em `aculta_portal` e verificada no navegador — ver 9.6.)*

### Divergências encontradas

- ADR-013 afirma que `aculta_commerce` está "ainda não habilitado", mas o Runtime local o lista como habilitado. Registrado para revisão humana; não foi alterado.
- `docs/modules/ACULTA-ASSOCIADO-ID.md` e os roadmaps tratam o associado como módulo ativo. Corrigido nesta rodada.
- `aculta_lms/docs/CONTRACT.md` não descreve a integração com Commerce. Registrado como pendência de documentação.

## 6. Contrato compartilhado proposto (I-B)

**Parcialmente implementado em `aculta_portal`:** estado, política e pane de checkout (criados antes da consolidação em `aculta_portal`). Os consumidores Loja e LMS usam o mesmo pane, por configuração de flow. Doações continuam fora. A tabela abaixo mantém a proposta original como referência.

| Peça | Dono | Responsabilidade |
|---|---|---|
| `AccountDocumentInterface` (existente) | `aculta_portal` | Contrato de leitura e gravação. Permanece como está. |
| `aculta_id_cpf.account_document` (existente) | `aculta_id_cpf` | Única implementação de CPF/CNPJ. |
| Estado de identidade fiscal (novo, somente leitura) | `aculta_portal` | Retorna `documento ausente` / `válido` / `inválido` / `indisponível`, tipo (CPF ou CNPJ) e representação mascarada. Não expõe o valor completo. |
| Política de exigência por contexto (nova) | `aculta_portal` | Recebe o **contexto validado do pedido** (tipo de pedido, loja, total > 0) e devolve se a exigência se aplica. Não lê URL, parâmetro de sessão nem indicação do navegador. |
| Checkout pane ou validação de pedido | `aculta_portal` (`FiscalIdentityPane`), habilitado nos flows de Loja e LMS | Mostra o aviso, aceita o primeiro cadastro e oferece `/dados/outros-dados` com retorno a este pedido (`abb8d80df`); revalida no servidor antes da conclusão. |
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
| `phpunit` Unit do `aculta_portal` (todos os testes Unit) | **PASS**: 324 testes, 810 asserções; 6 deprecações já existentes (medição final em 9.6 › Gates finais) |
| Testes Unit de Loja, LMS e Doações (caracterização, `CourseOrderEnrollmentManagerTest`, providers) | **PASS**: 43 testes |
| `FiscalIdentityPolicyTest` (novo) | **PASS**: 7 testes |
| `aculta_id_cpf/tests/run.php` | **PASS**: 248 verificações de domínio |
| `git diff --check` (commits desta rodada) | **PASS** |
| `php scripts/validate-project-status-index.php` | **PASS** (39 componentes). Falha anterior corrigida: criado o status de `aculta_commerce` e regenerada a tabela |
| `php scripts/validate-aculta-portal-drupal11.php` | **PASS** (1159 verificações). As falhas da linha de base foram corrigidas na rodada de 9.3 |
| PHPUnit Kernel do `aculta_portal` (suíte completa, com `SIMPLETEST_DB` do banco dedicado de teste) | **PASS**: 53 testes, 831 asserções; 90 deprecações registradas (as exibidas vêm de contrib), sem falhas |
| PHPCS Drupal/DrupalPractice nos arquivos novos e no formulário de Outros dados | **0 erros**; restam 16 avisos de linha longa (acima de 80 colunas), sem efeito funcional |
| PHPUnit Kernel (infraestrutura) | **PASS** em teste de fumaça (`EmailConfirmationPolicyKernelTest`, 3 testes), com `SIMPLETEST_DB` montado a partir da conexão do runtime de teste, só em variável de ambiente |
| PHPUnit Kernel do pane de identidade fiscal | **PASS** (6 testes), sem habilitar `aculta_portal`: classes instanciadas diretamente com o container do Commerce. Antes, a tentativa de habilitar o Portal esbarrava em dezenas de dependências. Histórico: **DEFERRED**: o container do `aculta_portal` depende de quase todo o ecossistema (`aculta_user`, `lms`, `group`, `aculta_mailer`, `token`, `social_api`…). Cada tentativa revelou um novo serviço ausente. O teste foi removido para não deixar vermelho. Próximo passo: Kernel sem `aculta_portal`, com o pane isolado, ou Functional |
| Config Sync | **Rodada anterior:** import parcial aplicado no ambiente de teste (`config:import --partial` só com `commerce_checkout.commerce_checkout_flow.default` e `commerce_order.commerce_order_type.curso`); flow `curso` removido com `config:delete`. Import completo **não** foi feito: ver 9.2. **Rodada de 2026-10-23:** nenhum import aplicado, nenhum arquivo de `config/sync` editado; os 46 itens divergentes foram reclassificados e a decisão por grupo está em 9.5. Nenhum item existe só no sync; os 31 só-no-banco incluem `core.extension`, que não deve ser importado. |
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
- **Kernel do pane:** PASS desde a rodada final, sem habilitar `aculta_portal`.

## 9.4 Estado final desta rodada (pendências da condição de objetivo)

- **Índice `search_api` `aculta_forum`:** corrigido. O processor `ForumPostSearchAccess` importava `ContainerFactoryPluginInterface` do namespace Symfony, então a classe não carregava e o plugin não era descoberto. Commit `4de26782`. A importação do índice passou a funcionar.
- **Import de configuração:** cada arquivo do drift foi importado individualmente, com `--partial` (sem deleções e sem `core.extension`). Todos os imports reportaram sucesso. Após o import, o `config:status` ainda mostra 35 diferentes e 16 só no sync. Causas identificadas (contagens do `config:status` neste bullet; a medição pelo conteúdo bruto, em 9.5, corrige para 0 só no sync e 31 só no banco):
  - Módulos contrib reincluem componentes ao salvar displays: `node_co_authors` (`co_authors`) e `content_moderation` (`moderation_state`) voltam ao display de `node.activity` logo após a gravação. O import não consegue deixar esses displays idênticos ao sync.
  - Itens "só no sync" que foram gravados continuam listados nessa categoria pelo `config:status`, mesmo com o item presente no banco. Não investigado até o fim.
  - `core.extension` segue excluído de propósito (desinstalaria módulos com dados).
  - Os 34 itens só no banco foram preservados, por segurança.
  
  Conclusão: a paridade total do drift não é alcançável só por import. Ela depende de decisões sobre os módulos contrib e sobre o sync.
- **Gate Drupal 11+:** PASS (1151 verificações). Três falhas foram corrigidas: caminho do `WikiController` (movido para `aculta_wiki`), registros de status dos helpers de checkout e do subscriber da ADR-013, e a contagem de subscribers, agora quatorze. Commit `e02608c0`.
- **Cenários de sessão:**
  - *Verificado em Kernel (Commerce, sem o Portal):* carrinhos de loja, curso e doação coexistem para a mesma conta; o mesmo tipo não é duplicado; carrinhos de lojas diferentes são separados. Arquivo `CartIsolationKernelTest`, 3 testes passando.
  - *Verificado em navegador no servidor de testes (2026-10-23):* carrinho anônimo preservado depois do login (pedido 117), login no pane durante o checkout (pedido 119) e troca de Domain (loja → MAIN). Evidências em 9.6.
  - *Não exercitado:* múltiplas abas simultâneas e expiração por TTL da sessão. O descarte da sessão por logout foi verificado; o vencimento por tempo exigiria aguardar o `cookie_lifetime` configurado.
- **Duplicação Loja × LMS:** removida na configuração (flow `curso` retirado, tipo `curso` aponta para o flow `default`). A rota de import foi aplicada no ambiente de teste.

## 9.5 Drift de configuração: classificação real e decisão (2026-10-23)

Medição refeita comparando o conteúdo bruto de cada objeto ativo (tabela `config` do banco de teste) com o arquivo correspondente em `config/sync`, sem depender do rótulo do `config:status`. Esta seção substitui a contagem anterior de 29 itens, que estava incompleta.

**Universo medido:** 46 objetos com conteúdo diferente (45 sem `core.extension`), 31 só no banco e **0 só no sync**. Como não há nenhum item só no sync, a direção que converge é reexportar o ativo; importar o sync por cima do banco aproxima no máximo os 45 e nunca elimina os 31 só-no-banco. Os 18 itens que o `config:status` listava como "só no sync" continuam confirmados como artefato de listagem, e os 3 removidos do repositório por outras frentes (`profile.type.customer`, `filter.format.basic_html`, `field.field.profile.customer.address`) não existem em nenhum dos lados.

**Passo 1 — classificação dos 46:**

- **Só ordem de chaves de mapa (7):** `core.entity_form_display.group.lms_course.default`, `core.entity_form_display.node.aculta_lms_topic.default`, `field.field.commerce_product.produto_loja.field_imagens`, `search_api.index.aculta_forum`, `views.view.aculta_forum_categories`, `views.view.aculta_forum_search` e `views.view.wiki_entries`. As chaves e os valores são os mesmos; só a ordem difere. O banco está na ordem canônica gravada por `preSave()` (`ksort` de `content`/`hidden` em `EntityDisplayBase`, `field_settings` em `search_api\Index`); o arquivo está na ordem de inserção manual.
- **Só ordem de lista (3):** `user.role.content_editor` (51 permissões), `user.role.email_confirmed` (25) e `search_api.index.aculta_conteudo` (bundles). Conjuntos idênticos; o banco está ordenado (`sort()` de `castValue()`) e o arquivo não.
- **Displays de entidade (23):** `core.entity_form_display.*` e `core.entity_view_display.*`. O banco tem componentes derivados de módulos ativos que o sync não tem (`entity_print_view_*`, `search_api_excerpt`, `co_authors`, `private_message_link`, `content_moderation_control`, `flag_sinalizar_post_forum`, `address`, `field_media_*`). O sync tem entradas obsoletas que o banco não tem (`hidden.revision_log`, `hidden.moderation_state`).
- **Campos, mídia e workflow (11):** 9 `field.field.*`/`field.storage.*`, `media.type.forum_attachment` e `workflows.workflow.aculta_forum_post`. Em 9 deles a única diferença é o `uuid` do banco, ausente no arquivo; em 2 há `settings` reais: `field.field.media_gallery.media_gallery.field_gallery_purpose` traz no sync `settings.max_length/is_ascii/case_sensitive`, que são *storage settings* de um campo `string`, e `field.storage.group.field_roster_visible` traz `settings.on_label/off_label`, que são *field settings* do plugin `boolean`. O banco tem `settings` vazio porque é o formato que o plugin aceita.
- **Formato de texto (1):** `filter.format.full_html` está `langcode: en` no sync, enquanto o banco está `pt-br` e os outros 7 formatos do sync já são `pt-br`.
- **`core.extension` (1, fora dos 45):** 23 módulos só no banco (`aculta_document_viewer`, `aculta_document_viewer_group`, `aculta_editor`, `aculta_lms_messenger`, `aculta_lms_resources`, `ckeditor5_plugin_pack` e 13 subplugins, `entity_print`, `google_tag`), nenhum só no sync, pesos e tema idênticos.

**Passo 2 — por que o import parcial não convergiu (provado no código e medido):**

1. **Itens de lista.** `ConfigImporter::importConfig()` não usa `updateFromStorageRecord()`: cria um `Config` puro, aplica `initWithData($old_data)` e `setData($source_data)` e chama `$config->save()`. Em `Config::save()`, quando os dados não são "trusted" — o caso do importador —, `castValue()` reordena as sequências conforme o `orderby` do schema. Medição: nos 3 itens o banco está ordenado e o arquivo **não**; os conjuntos são iguais. Logo, o import grava na ordem canônica e a comparação nunca zera **enquanto o arquivo estiver fora de ordem**. Para esse grupo, ordenar o arquivo é o único conserto; importar de novo é inútil.
2. **Itens de mapa.** Aqui são os módulos que reordenam ao salvar, como suspeitado: `EntityDisplayBase::preSave()` faz `ksort` de `content`/`hidden` e `search_api\Index::preSave()` faz `ksort` de `field_settings`. O banco está nessa forma; o arquivo, não. Vale o mesmo remédio do item 1.
3. **Itens de valor.** As 35 diferenças de valor fora de `core.extension` não mostram nenhum vestígio do import parcial: o banco ainda tem `uuid` onde o sync não tem, `filter.format.full_html` ainda está `pt-br` e os dois campos ainda têm `settings: []`. Conclusão: o import não chegou a esses itens — não é o `castValue` que os impede. Além disso, `StorageComparer::addChangelistUpdate()` só trata `uuid` diferente como recriação quando o **arquivo** tem `uuid`; sem `uuid` no arquivo, a operação seria uma atualização simples.
4. **Displays.** `EntityDisplayBase::init()` recria como `hidden` todo campo exibível do bundle ausente de `content`/`hidden`; o import copia esse `hidden` já recomposto. Remover componentes de um display por import é impossível por desenho.
5. **Campos.** `FieldConfig::preSave()` e `FieldStorageConfig::preSave()` filtram `settings` pelas chaves que o plugin suporta e repõem os defaults; chaves no lugar errado são descartadas ao salvar.

**Passo 3 — efeito nos três pontos pedidos:** nenhum. Em `user.role.content_editor` (51 permissões) e `user.role.email_confirmed` (25) o conjunto de permissões é idêntico ao sync; em `search_api.index.aculta_conteudo` os bundles são idênticos; em `search_api.index.aculta_forum` os campos dos processors e os datasources são idênticos; as dependências de todos os itens de ordem também. Não há mudança de permissão nem de indexação/busca decorrente do drift.

**Passo 4 — decisão:** `config/sync` é a fonte da verdade, mas a divergência não se resolve por import. Correção por grupo:

- **10 itens de ordem:** corrigir a ordem nos arquivos de `config/sync` para a forma canônica. Os 5 de `aculta_index`, `aculta_wiki` e fórum (`search_api.index.aculta_conteudo`, `search_api.index.aculta_forum`, `views.view.wiki_entries`, `views.view.aculta_forum_categories`, `views.view.aculta_forum_search`) ficam com os donos dessas frentes e não são tocados aqui. Os 2 form displays de LMS e `field.field.commerce_product.produto_loja.field_imagens` cabem a esta frente; os 2 papéis são globais e podem ser normalizados por quem responde por permissões.
- **23 displays e 11 campos/mídia/workflow:** o sync é mais antigo que o banco. O caminho é `drush config:export` desses itens no ambiente de teste, com revisão dos donos, e nunca importar por cima do banco. Para os 9 que diferem só por `uuid`, a reexportação grava o `uuid`; para os 2 com `settings`, grava o formato aceito pelo plugin.
- **`filter.format.full_html`:** trocar `langcode` para `pt-br` no arquivo do sync. Correção de uma linha, na rodada de configuração de quem responde pelos formatos.
- **`core.extension`:** não importar. Os 23 módulos a mais no banco são a causa raiz dos 23 displays e dos 31 itens só no banco; importar apagaria esses 31 objetos, entre eles `field.storage.profile.address` e os campos de `message.template.aculta_msg_lms_notice`.

**Execução da decisão (2026-10-23, mesma rodada):**

- **Corrigidos no sync, por reordenação textual** (sem reexportar e sem importar): os dois form displays de LMS (`group.lms_course.default`, `node.aculta_lms_topic.default`), `field.field.commerce_product.produto_loja.field_imagens` (posição de `third_party_settings`), os dois papéis (`content_editor`, 51 permissões; `email_confirmed`, 25) e `filter.format.full_html` (`langcode: en` → `pt-br`). As listas foram ordenadas pelo valor sem as aspas, que é a forma que `castValue()` grava; ordenar pelo texto com aspas deixava `access content` depois de `access content overview`.
- **Não tocados, por pertencerem a outras frentes:** `search_api.index.aculta_conteudo`, `search_api.index.aculta_forum`, `views.view.wiki_entries`, `views.view.aculta_forum_categories` e `views.view.aculta_forum_search`. O índice de conteúdo chegou a ser reordenado nesta rodada e foi revertido ao original ao se confirmar o dono.
- **Medição após as correções:** 40 objetos divergentes (39 sem `core.extension`), 31 só no banco e 0 só no sync — eram 46/45. Os seis itens corrigidos agora comparam idênticos ao ativo.
- **Gates e suítes:** `PROJECT STATUS INDEX: PASS (39 components)`; `ACULTA PORTAL DRUPAL 11+ GATE: PASS (1159 checks)`; Unit do `aculta_portal` `324 tests, 810 assertions` OK (6 deprecations); Kernel `53 tests, 831 assertions` OK (90 deprecations), com `SIMPLETEST_DB` só em variável de ambiente.
- **Nada foi importado e nenhum objeto do banco foi alterado.** O backup anterior continua idêntico ao ativo, conferido item a item.

## 9.6 Verificação de navegador da frente CPF + checkout (2026-10-23)

Ambiente: servidor de testes `*.aculta.toca.net.br`; Firefox ESR visível em `DISPLAY=:0` via geckodriver, perfil temporário em `/tmp`; conta `teste.comum` (uid 5), conforme `docs/operations/TESTE-COMUM.md`. Nenhuma configuração do ambiente foi alterada.

### Correções feitas antes dos testes

- **Autowiring do `CartCheckoutController`:** o serviço falhava com `AutowiringFailedException` ("Cannot autowire service ... argument `$consolidator`"), registrado às 09:35 de 2026-10-23. O `#[Autowire(service: 'aculta_portal.cart_consolidator')]` no construtor resolve. Sem ele, `/carrinho/finalizar` não carrega.
- **403 do checkout anônimo:** `ProtectedRouteAccessCheck::checkout()` combinava o acesso do Commerce com os checks de e-mail e termos. Para anônimo os dois são neutros, e neutro combinado com permitido continua neutro — e neutro é negado. Agora o anônimo devolve só o acesso do Commerce, que já decide pela sessão do carrinho; o autenticado continua exigindo e-mail confirmado e termos. Coberto por três testes Unit novos.

### Evidências

| Cenário | Resultado | Evidência |
|---|---|---|
| Carrinho anônimo por sessão | PASS | Produto no host `shop` (`loja.aculta.toca.net.br/product/9`) → carrinho em `aculta.toca.net.br/carrinho` (`views-form-commerce-cart-form-default-117`); cookie `SSESS…` com domínio `.aculta.toca.net.br` |
| Login no pane do checkout | PASS | Pedido 119: `/finalizar-compra/119/entrar`; login em `#edit-login-returning-customer-name` / `-password` → `/finalizar-compra/119/informacoes-do-pedido?check_logged_in=1` |
| Mesmo pedido depois do login | PASS | Pedido 117 mantém o id e o item depois do login pela conta |
| Troca de Domain | PASS | `loja.aculta.toca.net.br/carrinho` → 302 → `https://aculta.toca.net.br/carrinho` |
| Logout | PASS | Após "Sair": `/finalizar-compra/119/revisao` → 403 "Acesso negado"; `/carrinho` → "Seu carrinho de compras está vazio." |
| Pane fiscal — vazio | PASS | Pedido 113 em `revisao`: permanece no passo, "Informe seu CPF ou CNPJ para concluir a compra." |
| Pane fiscal — inválido | PASS | `123.456.789-00`: permanece em `revisao`, "Não foi possível salvar este CPF ou CNPJ. Verifique o número e tente novamente." |
| Pane fiscal — válido | PASS | CPF sintético válido (checksum de teste) → `/finalizar-compra/113/concluido`, h1 "Pedido concluído", sem mensagens |
| Documento já gravado | PASS | Pedido 114 em `revisao`: campo ausente e texto "Documento cadastrado: 111.44*.***-**. Não pode ser alterado." |
| Múltiplas abas | Não exercitado | — |
| Expiração por TTL | Não exercitado | Só o descarte por logout foi verificado |

### Limitações do ambiente (não são falhas desta frente)

- **Gateway de pagamento desabilitado:** o gateway `mercado_pago` está com `status=false`, então `PaymentInformation::buildPaneForm()` emite "Não existem gateways de pagamento disponíveis para este pedido." e o checkout não avança além de `informacoes-do-pedido`. Para exercitar o pane fiscal, o total dos pedidos de teste foi zerado. Nada na configuração foi alterado.
- **`profile.type.customer` ausente:** o pedido 113 falhou com 500 (`PluginNotFoundException: The "entity:profile:customer" plugin does not exist.`, log de 23-Oct-2026 10:13:26) ao tentar criar o perfil de cobrança. Os tipos existentes são `administrator, associado, authenticated, email_confirmed, estudante, gerente_loja, lms_teacher`. O tipo foi removido do repositório por outras frentes (`e85c3dbe`, `b66646ef`), como registra 9.5. É configuração de outra frente; não foi alterada.
- **403 em deep link depois do logout:** `/finalizar-compra/{id}/{step}` devolve 403 sem CTA de login, enquanto a entrada `/carrinho/finalizar` mostra o pane. Avaliar se convém redirecionar o deep link para a entrada.
- **LMS no navegador:** não testado nesta rodada; a cobertura de LMS vem do `FiscalIdentityPaneKernelTest` (12 testes, 343 asserções).
- **Olivero:** não testado nesta rodada. Trocar `system.theme` é global e afetaria o trabalho de outros agentes no mesmo ambiente.

### Gates finais

- `php scripts/validate-project-status-index.php` → `PROJECT STATUS INDEX: PASS (39 components)`.
- `php scripts/validate-aculta-portal-drupal11.php` → `PROJECT STATUS INDEX: PASS (39 components)` e `ACULTA PORTAL DRUPAL 11+ GATE: PASS (1159 checks)`.
- Unit: `324 tests, 810 assertions, OK` (6 deprecations), sem falhas.
- Kernel, com `SIMPLETEST_DB` exportado de `secrets/simpletest-db.env` apenas como variável de ambiente: `53 tests, 831 assertions, OK` (90 deprecations).

## 10. Pendências

1. **Ativação em runtime:** `config:import` autorizado no ambiente de testes antes de qualquer produção. Ver 9.1. Medição local de 2026-10-23: `aculta_portal.checkout_settings` **não existe** no runtime local nem em `config/sync`; valem os padrões de `config/install` (`common_checkout_enabled: true`, `shop_order_type: default`, `lms_order_type: curso`). O flow `default` do runtime local já contém `aculta_fiscal_identity` e o flow `curso` não existe, então a unificação está aplicada no banco local; nos servidores de teste e produção continua pendente de verificação.
2. **Kernel do checkout: COBERTO.** `FiscalIdentityPaneKernelTest` cobre visibilidade (Loja, LMS e Doações), bloqueio na revisão sem documento, aviso com link, documento mascarado, falha fechada e a garantia de que o checkout não grava documento já existente. 12 testes, 343 asserções, PASS com `SIMPLETEST_DB` de ambiente (mapeado de `secrets/simpletest-db.env`). O run emite 72 deprecations, todas do core 11.4 (`DefaultHtmlRouteProvider::getEntityTypeIdKeyType`), nenhuma dos arquivos desta frente.
3. **Retorno do link `destination`: COBERTO.** `AccountOtherDataForm::returnUrl` (`d46f39e8d`) aceita apenas caminho interno sob `/checkout/`; URL externa, relativa de protocolo, caminho fora do checkout, vazio e ausente voltam para `/dados/outros-dados`. `AccountOtherDataFormKernelTest` cobre os seis casos, PASS.
4. **Procedimento administrativo de correção** (permissão, justificativa, auditoria sem CPF/CNPJ em claro): escopo a definir.
5. **Avisos de linha longa: RESOLVIDO.** A medição encontrou **15 avisos** (não 16) em seis arquivos de identidade fiscal e no formulário, nenhum erro. Os docblocks foram encurtados (commit `76c194002`); `phpcs --standard=Drupal,DrupalPractice` nos sete arquivos agora termina com código 0, sem erros nem avisos.
6. **Runtime de teste e produção:** `aculta_associado_id` **não está habilitado** no runtime local (`pm:list` = 0) e **não aparece** em `config/sync/core.extension.yml`. O mesmo teste continua pendente nos servidores de teste (`*.aculta.toca.net.br`) e produção (`*.aculta.org`), que exigem acesso.
7. **Gate Drupal 11:** resolvido. O `WikiController` foi movido para `aculta_wiki` e o gate passa (1151 verificações).
8. **Divergência:** ADR-013 diz que `aculta_commerce` não está habilitado, mas o runtime local e o `core.extension` do sync o listam como habilitado. Registrado em adendo datado da própria ADR (2026-10-23, commit `84cc65051`), com a frase original preservada. A correção do texto antigo, se desejada, é decisão do responsável.
9. **Cenários de sessão da seção 5:** verificados no servidor de testes em 2026-10-23, com a conta `teste.comum` (uid 5). Detalhes em 9.6.
    - **PASS — login durante o checkout:** pedido 119; o login no próprio pane avança o mesmo pedido para `informacoes-do-pedido` com `?check_logged_in=1`.
    - **PASS — anônimo antes do login:** pedido 117 criado anonimamente e preservado depois do login.
    - **PASS — troca de Domain:** `loja.aculta.toca.net.br/carrinho` redireciona (302) para `aculta.toca.net.br/carrinho`.
    - **PASS — logout:** depois de sair, `/finalizar-compra/119/revisao` responde 403 e `/carrinho` mostra o carrinho vazio.
    - **Acesso:** já documentado e em uso nesta rodada. Host `conta.aculta.toca.net.br`, conta `teste.comum` (uid 5) e senha em `secrets/teste_comum.env`, conforme `docs/operations/TESTE-COMUM.md`; o próprio documento proíbe pedir outra senha ou copiá-la para saídas. Nada a solicitar ao responsável.
    - **Pendente — múltiplas abas:** não exercitadas. A sessão é única por cookie de domínio `.aculta.toca.net.br`, mas duas abas simultâneas (por exemplo, carrinho e checkout abertos ao mesmo tempo) não foram testadas.
    - **Pendente — expiração por TTL:** não exercitada. Não há `cookie_lifetime` em `config/sync` nem nos `settings.php` do repositório, então vale o padrão do Drupal (2 000 000 s, cerca de 23 dias); o valor efetivo do servidor de testes não foi inspecionado. Exercitar o vencimento exigiria encurtar a configuração do ambiente compartilhado ou aguardar o prazo, o que não foi feito para não alterar o ambiente.
10. **Drift dos 46 itens de configuração (45 sem `core.extension`):** reclassificado e decidido em 9.5. A contagem anterior de 29 estava incompleta — faltavam 6 diferenças de ordem de mapa, 9 campos/mídia/workflow que diferem só pelo `uuid` e `filter.format.full_html`. Não há item só no sync; o conjunto só-no-banco é de 31. Execução em 9.5: já corrigidos no sync 6 itens (2 form displays de LMS, 1 campo da Loja, 2 papéis e o `langcode` de `full_html`); nada importado e nenhum objeto do banco alterado. Medição final: 40 divergentes (39 sem `core.extension`), 31 só no banco, 0 só no sync. Pendente, por grupo: os 5 itens de ordem dos donos de `aculta_index`, `aculta_wiki` e fórum; reexportar do ativo, com revisão, os 23 displays e os 11 campos/mídia/workflow; e manter `core.extension` fora de qualquer import.
11. **Verificação de navegador no servidor de testes (2026-10-23):** Firefox ESR visível em `DISPLAY=:0`, via geckodriver, com `scripts/check-browser-hosts.sh`; sem processos do navegador restantes ao final.
    - **PASS — carregamento dos hosts:** `aculta.toca.net.br`, `links.aculta.toca.net.br`, `conta.aculta.toca.net.br/entrar` e `apoio.aculta.toca.net.br` carregaram (código 0, títulos esperados), com `example.com` como controle. A home da loja (`loja.aculta.toca.net.br/`) responde 200.
    - **PASS — rotas da frente, anônimo:** `conta.../dados` e `.../painel-administrativo/aculta/checkout` respondem 403; `loja.../checkout/{id}` responde 301 para `/finalizar-compra/{id}`, como em `CheckoutSlugMap` e `DomainPurposeRequestSubscriber`.
    - **Observação 1:** `loja.aculta.toca.net.br/loja-da-aculta`, listado em `docs/operations/NAVEGADOR-TESTES.md`, responde 404; a home da loja publica produtos em `/loja/produto/...`.
    - **Observação 2:** `/checkout` na loja, no apoio e na conta responde 302 para `https://aculta.toca.net.br/checkout`, que responde 403 para anônimo. É compatível com rota comum fechada; confirmar com acesso autenticado.
    - **PASS — cenários de sessão:** login durante o checkout, carrinho anônimo, troca de Domain e logout, com a conta de teste (item 9 e 9.6). Continuam pendentes múltiplas abas e expiração por TTL.
    - **PASS — pane de identidade fiscal na Loja:** pedido 113 em `revisao` recusa vazio e inválido, permanecendo no passo, e conclui com documento válido; o pedido 114 mostra o documento mascarado e sem campo de edição. Evidências em 9.6.
    - **Pendente — fiscal no LMS e tema Olivero:** não exercitados no navegador nesta rodada.
