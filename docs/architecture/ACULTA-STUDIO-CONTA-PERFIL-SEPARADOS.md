# ACULTA Studio — Minha Conta e Meu Perfil independentes

**Contrato arquitetural vigente desde 2026-10-24.** Substitui o antigo
planejamento que agrupava Conta e Perfil em `/studio/pessoal` e a hipótese
de retirar o legado. Entregas de código são distintas de homologação Runtime.

## Identidades e ownership

| Área | Entrada canônica | Dono dos dados | O que apresenta |
| --- | --- | --- | --- |
| Hub Studio | **`/` no Domain ACCOUNT** | `aculta_studio` | Catálogo autorizado; route ID `aculta_studio.hub`, caminho técnico `/studio` (404 em acesso direto) |
| **Minha Conta Studio** | `/studio/conta` | Portal, Core User/Profile, Commerce, Auth | Cadastro, endereço, documentos, segurança, conexões, apoio, pedidos e preferências da conta |
| **Meu Perfil Studio** | `/studio/perfil` | `aculta_user`, User/Profile, `aculta_image` | Nome público/apelido, foto, avatar, biografia, redes e visibilidade pública |
| **Minha Conta legada — PERMANENTE** | **`/conta-legada` no Domain ACCOUNT** | `aculta_portal` e owners existentes | Shell, menus, AJAX e formulários atuais, sem alterações |
| Perfil público | `/u/{apelido}` em MAIN | `aculta_user` e `PublicProfilePolicy` | Apenas informações autorizadas publicamente |
| Marley | `/studio/marley` | Owners editoriais | Trabalho editorial |
| Ganjalab | `/studio/ganjalab` | LMS | Aprendizagem |
| WE SATIVA | `/studio/we-sativa` | Wiki | Contribuição |

### Garantia permanente do legado

**Minha Conta não será removida ao fim do processo.** Continua acessível
em **`/conta-legada`** no domínio ACCOUNT, incluindo `AccountShellBuilder`,
`PortalController`, `account-navigation.js`, formulários, hooks,
integrações e estado de navegação. A rota técnica
`aculta_portal.dashboard` foi movida para `/conta-legada` (versão 0.4.1-dev.26).
A raiz `/` do Domain ACCOUNT passou a servir o hub do Studio. Requisições
HTTP diretas para `/minha-conta` ou filhos respondem 404, sem redirect nem alias.
Não habilitar esse caminho nem criar redirect/alias.
A interface legada será usada para comparar versões visual e
funcionalmente, inclusive depois de aprovado o Studio.

### Limites estritos de cada experiência

- **Conta** não administra bio pública, avatar de comunidade, redes de
  perfil ou seleção de campos visíveis. Continua podendo *apontar* para
  formulários preexistentes no legado enquanto suas seções Studio não são
  integradas, sem copiar armazenamento.
- **Perfil** não incorpora formulário de dados pessoais, endereço,
  CPF/CNPJ, e-mail, autenticação, pedidos nem pagamentos.
- A edição isolada do **apelido público** requer extração segura do campo
  hoje apresentado no formulário cadastral do Portal; não incorporar
  `PortalController::myData()` a Perfil.
- O Studio não cria entidades, tabelas, inbox, sistema de upload ou regras
  de privacidade alternativas. Reutilizar os serviços do owner.
- Nenhum alias transitório para `/studio/pessoal` será instalado:
  atualizar consumidores e testes diretamente para os nomes finais.

## SDCs preparatórios, antes da S-R4

O ACULTA420 `0.7.0-dev.1` contém quatro SDCs experimentais para
Meu Perfil privado: `profile-editor`, `profile-identity`,
`profile-bio`, `profile-social`. O tema é apenas visual:
o ACULTA User mantém dados, regras de publicidade, edição e avatar;
Studio fará a composição após validar os contratos. O cartão
`author-card` permanece separado para autoria contextual.
Foram registrados oito stories e testes Unit, **não executados**.
Nenhuma mudança foi feita no comportamento atual, no perfil público,
nos formulários ou na Conta legada.

## S-R3-B3 — pedidos, apoio e preferências (Studio dev.20, não homologado)

As novas rotas `/studio/conta/pedidos`, `/studio/conta/apoio` e `/studio/conta/preferencias` são privadas, exigem login/permissão Portal no Domain ACCOUNT e possuem `no_cache`. O Studio não cria outra lógica de Commerce, de assinaturas ou de preferências: resolve `PortalController::orders()` e `SupportController::mySupport()` via o class resolver e constrói `AppearanceForm` pelo Form API Core, preservando o POST na página atual e as tabs de configurações do Portal. A seção de pedidos mantém filtro por uid; apoio usa o histórico e as ações do owner; preferências usam `ColorModePreference` sem criar outro campo.

O portal original em `/conta-legada` e suas rotas `/meu-apoio`, `/configuracoes`, pedidos/checkout e assinatura continuam intactos. Links das configurações adicionais vêm de `SettingsTabs` do Portal e podem encaminhar às respectivas páginas originais. Conta e Perfil não são misturados e nenhuma nova entidade, alias, redirect ou tabela foi introduzida.

Testes Unit novos escritos, **não executados**. Kernel/Functional, acesso entre contas, validação das ações de assinatura, persistência real do formulário, cache, segurança CSRF, Domain ACCOUNT, comparação visual e Firefox ESR/X11 permanecem pendentes. A lacuna conhecida B1 de unicidade concorrente não foi modificada.

## S-R3-B1 — auditoria de documentos (dev.19/31, sem homologação)

A integração reutiliza o Form API do Portal, o serviço opcional de CPF/CNPJ e os campos existentes, sem publicar documentos no Perfil. Ausência total do serviço causa 404 no editor Studio após autorização; indisponibilidade de chaves em owner instalado mantém somente a explicação genérica do formulário original, enquanto o link some do menu. Retornos por checkout válido, Studio e legado conservam o contexto.

A verificação HMAC em `AccountDocumentStore` antecede o save e não garante unicidade transacional entre requisições concorrentes. Esse limite permanece no owner e depende de decisão separada sobre garantia forte. Testes Unit de acesso, máscara e destinos adicionados, **não executados**; faltam Kernel/Functional, PHP lint, HTTP, Firefox/Runtime, cache, CSRF e no-JS. A Conta original `/conta-legada` e o incremento B2 permanecem.

## S-R3-B2 — cadastro separado de apelido (dev.18/30/74, código não homologado)

- `/studio/conta/dados` delega o **mesmo Profile Form API de `PortalController::myData()`** ao Portal e usa seu `form_alter` por rota para apresentar nome e WhatsApp privados, sem `field_nickname`. Em `/dados` o formulário legado fica misto, inalterado.
- `/studio/perfil/apelido` é um Form API independente do owner `aculta_user`; edita somente `Profile.field_nickname` da conta atual. `NicknamePolicy` reutiliza a consulta de colisões entre perfis de papel, compartilhada com `AccountCreationHooks`, e `AccountNames` mantém validação/slug/reservados. Não há segundo campo ou alias da rota pública.
- Salvar retorna ao contexto da experiência; `/u/{apelido}` continua gerado por `PublicProfileLink` no MAIN e condicionado a `PublicProfilePolicy`. `/conta-legada` permanece permanente; `/` ainda é o hub do Studio.
- Testes Unit escritos, **não executados**. Segurança HTTP, duplicidade concorrente, privacidade, perfil obrigatório, cache, integração com legado, sem JavaScript, checkout, Kernel/Functional e Firefox ESR/Runtime ainda não homologados. Menções anteriores a apelido pendente são históricas.

## S-R3-B1 — Documentos privados no Studio (dev.17)

- `/studio/conta/documentos` reaproveita o mesmo
  `PortalController::otherData()` e `AccountOtherDataForm` do
  legado `/dados/outros-dados`.
- `aculta_id_cpf.account_document` é opcional e mantém exclusivamente
  validação, cifra, máscara e gravação única de CPF/CNPJ. Os links só
  aparecem quando `isAvailable()`; o owner ausente mantém 404.
- O retorno sem checkout fica na interface que originou o envio, sem
  alias de compatibilidade nem armazenamento duplicado.
- O Perfil Studio não exibe CPF/CNPJ; a Conta legada segue permanente
  em `/conta-legada`, enquanto `/` continua sendo o hub do Studio.
- Testes Unit escritos, mas os novos testes dev.17 ainda não foram
  executados nem homologados no Runtime/Firefox.

## Implementação inicial (S-R2 — no código Git; sem homologação)

- `AccountExperienceProvider` (`conta`) renderiza página
  `/studio/conta` e pontos de entrada para os formulários **originais**
  do Portal. Essa primeira composição ainda não equivale à versão
  final de cada formulário no shell Studio.
- `ProfileExperienceProvider` (`perfil`) registra a identidade
  independente e verifica que `aculta_user` está instalado.
- `StudioProfileController` apresenta apenas identidade comunitária;
  delega valores de bio/redes a `AccountBioCard`/`SocialLinksCard`;
  mantém foto/recorte do owner; usa `PublicProfileLink` para URL no
  purpose MAIN. O apelido ainda não ganhou formulário isolado no Studio.
- Rotas independentes de edição:
  `/studio/perfil/privacidade`, `/studio/perfil/bio` e
  `/studio/perfil/redes/{rede}`; mesmo Form API do `aculta_user`,
  com retorno contextual ao Studio e envio normal sem JavaScript.
  Minha Conta legada continua com seus próprios modais, tabs e redirects.
- `StudioProfileAccess` restringe operações ao módulo proprietário
  instalado, usuário autenticado e permissão `access aculta portal`.
- `/studio/pessoal/*` não é rota válida, nem recebe redirect.
  A rota pública `/u/{apelido}` permanece inalterada.

## Progresso S-R3-A — integração inicial dos formulários Conta (dev.14)

Em código, `StudioAccountSectionController` compõe os render arrays
**do mesmo `PortalController` original** nas rotas:

- `/studio/conta/endereco` — edição do mesmo Profile Commerce
  `authenticated.field_address`, incluindo país BR, Número/S-N e CEP;
- `/studio/conta/seguranca` — e-mail e confirmação do Portal;
- `/studio/conta/seguranca/senha` — senha e criação da primeira senha OAuth;
- `/studio/conta/conexoes` — conexões de autenticação.

O Studio oferece apenas navegação contextual própria; não envolve
`AccountShellBuilder` nem modifica o shell, os handlers ou os forms
originais. Os métodos do owner continuam responsáveis por ACL, CSRF,
campos, submissions e estados de erro. Os formulários privados são
reutilizados, não copiados.

**A página `/dados` ainda está no Portal legado**: o Profile form atual
inclui `field_nickname`, e sua cópia integral em Minha Conta Studio
reintroduziria a mistura entre cadastro privado e perfil público.
A separação funcional do campo é tarefa S-R3-B/S-R4.

O Portal passou a reconhecer ambos os **route IDs** de endereço, para não
perder `StreetNumberField`, os builders e o retorno contextual após
salvar. A Conta legada permanece intocada em `/conta-legada`, a raiz `/`
é o hub do Studio, e `/minha-conta` HTTP direto responde 404. Os demais destinos
(Apoio, pedidos, preferências e cadastro) ainda apontam para os owners
originais neste incremento, sem alegar migração concluída.

**Testes escritos/atualizados, mas não executados**: Unit do controller
de composição e redirect do endereço, rota/ACL e
`scripts/validate-account-address.php`. Dev.14 ainda necessita
PHPUnit/Kernel/Functional e homologação no Runtime/Firefox.

## Próximas entregas pequenas, sem retirar o legado

1. **S-R3 — Conta Studio:** extrair composição de formulário e navegação
   do Portal sem aplicar dois shells; seções privadas de dados, endereço,
   documentos opcionais, segurança/OAuth e pagamentos por owners.
   Separar visualmente o apelido público dos dados cadastrais sem mudar
   schema nem quebrar a Conta original.
2. **S-R4 — Perfil Studio:** componente/Form API de edição **isolada**
   do apelido respeitando normalização/duplicidade; refinar cartões,
   foto e edição de bio/redes com no-JS, estados e erros.
3. **S-R5 — comparação:** verificar alterações cruzadas no mesmo
   User/Profile entre legado, Conta Studio, Perfil Studio e página
   pública, com usuário normal/autor/editor/perfil privado.
4. **S-R6 — aprovação:** mostrar diferenças e pedir aceite explícito,
   conservando **permanentemente** Minha Conta legada em `/`.
   Não existe etapa futura de retirada do shell legado.

## Testes e não regressão

- O dashboard do Portal e `/` no purpose account continuam funcionando;
  `/minha-conta` direto é 404; `/conta-legada` é a Minha Conta original.
- `/studio/conta` e `/studio/perfil` têm providers independentes e
  links distintos no hub; nunca um provider `pessoal`.
- Conta nunca apresenta editor público próprio; Perfil nunca renderiza
  `PortalController::myData()`.
- Formulários legados mantêm os mesmos campos, validação, modal/JS,
  CSRF, submit/redirect, endereço/checkout e autenticação.
- Studio bio/redes/privacidade preservam validação/sanitização
  proprietária e uso sem JS; bloqueio por account, Domain e ausência
  do módulo User.
- ProfileLink/PublicProfilePolicy não inferem visibilidade pela
  permissão de editar; cache por usuário e entidades.
- Gates PHP/Unit/Kernel/Functional, Runtime Drupal autenticado e
  Firefox ESR/X11 precisam ser **executados**; verificações de arquivo
  no GitHub não equivalem a homologação.

## Histórico

O plano anterior [ACULTA-STUDIO-PERSONAL-EXPERIENCE.md](ACULTA-STUDIO-PERSONAL-EXPERIENCE.md)
permanece somente como histórico da decisão superada. O
[contrato de identidade](../portal/ACULTA-STUDIO-IDENTITY.md)
deve priorizar os nomes e caminhos desta especificação.
