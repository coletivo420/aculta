# ACULTA Studio — Minha Conta e Meu Perfil independentes

**Contrato arquitetural vigente desde 2026-10-24.** Substitui o antigo
planejamento que agrupava Conta e Perfil em `/studio/pessoal` e a hipótese
de retirar o legado. Entregas de código são distintas de homologação Runtime.

## Identidades e ownership

| Área | Entrada canônica | Dono dos dados | O que apresenta |
| --- | --- | --- | --- |
| Hub Studio | `/studio` | `aculta_studio` | Catálogo de experiências acessíveis |
| **Minha Conta Studio** | `/studio/conta` | Portal, Core User/Profile, Commerce, Auth | Cadastro, endereço, documentos, segurança, conexões, apoio, pedidos e preferências da conta |
| **Meu Perfil Studio** | `/studio/perfil` | `aculta_user`, User/Profile, `aculta_image` | Nome público/apelido, foto, avatar, biografia, redes e visibilidade pública |
| **Minha Conta legada — PERMANENTE** | **`/` no Domain ACCOUNT** | `aculta_portal` e owners existentes | Shell, menus, AJAX e formulários atuais, sem alterações |
| Perfil público | `/u/{apelido}` em MAIN | `aculta_user` e `PublicProfilePolicy` | Apenas informações autorizadas publicamente |
| Marley | `/studio/marley` | Owners editoriais | Trabalho editorial |
| Ganjalab | `/studio/ganjalab` | LMS | Aprendizagem |
| WE SATIVA | `/studio/we-sativa` | Wiki | Contribuição |

### Garantia permanente do legado

**Minha Conta não será removida ao fim do processo.** Continua acessível
na raiz `/` do domínio de conta, incluindo `AccountShellBuilder`,
`PortalController`, `account-navigation.js`, formulários, hooks,
integrações e estado de navegação. A rota técnica
`aculta_portal.dashboard` permanece declarada em `/minha-conta`,
**mas requisições HTTP diretas para `/minha-conta` ou filhos continuam
404**, como antes. Não habilitar esse caminho nem criar redirect/alias.
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
  `/minha-conta` direto é 404; nenhuma alteração no subscriber.
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
