# ACULTA Studio — Hub e Meu Espaço

## STUDIO-P2-A — página de edição privada adicionada (dev.12)

A nova rota `/studio/pessoal/perfil` abre um painel privado no
purpose `account`: compõe componentes do User para edição modal de
bio/redes, link para o editor oficial de foto/avatar, formulário atual
de apelido e link público autorizado exclusivamente pelo serviço
`aculta_user.public_profile_link`. **A página pública `/u/{apelido}`
continua separada** e nunca é inferida a partir do login.

A P2-A não migrou Form API, dados, shell nem o cadastro; P2-B deve
resolver navegação contextual, privacidade/apelido integrados e
fallback sem JS. Unit tests foram criados, mas PHPUnit, Kernel e
Runtime ainda não foram executados.

## Contrato de rotas vigente após limpeza global (dev.11)

Hub em `/studio` (`aculta_studio.hub`), Marley em
`/studio/marley` e Meu Espaço em `/studio/pessoal`; o perfil
público `/u/{apelido}` continua independente. Nenhum alias temporário
de capitalização, prévia ou caminhos anteriores é mantido. A política
em `AGENTS.md` aplica-se ao Studio e a todos os módulos. As descrições
de aliases em dev.7–10 abaixo são **históricas**, não destinos atuais.
Sem homologação Runtime nesta alteração.



**Arquitetura-alvo, 2026-10-24.** A fase documental P0 foi concluída; a P1-A já possui implementação inicial em código (`0.1.0-dev.8`), ainda **sem homologação PHPUnit/Runtime**. As demais fases são planejamento; nenhum dado, formulário ou shell antigo foi migrado.

## Atualização P1-C — hub real e entrada editorial minúscula (dev.10)

O código atual apresenta o hub em **`/studio`** e a entrada do Marley
em **`/studio/marley`**; o route ID `aculta_studio.home` mantém seu
controller editorial. `/studio/Marley` e `/studio/inicio` respondem
com 302 somente para GET/HEAD, respectivamente ao Marley minúsculo e ao
hub. Essas decisões substituem as descrições históricas de `/studio` como
entrada do Marley na P0/P1-A/P1-B.

**Os códigos e testes Unit foram atualizados no Git, mas não executados
no Drupal instalado.** A edição de perfil, incorporação de formulários
da Conta e a navegação contextual unificada permanecem previstas para
fases posteriores. Não confundir implementação de rotas com homologação.

## Direção e limites

- `/studio` já está implementado no Git como hub autenticado (P1-C, ainda sem homologação Runtime); `/studio/marley` é a entrada inicial do Marley, totalmente minúscula.
- `Meu Espaço`, provider `pessoal`, tem entrada mínima implementada em `/studio/pessoal` (purpose `account`, autenticação e permissão do Portal); não é ainda o hub unificado nem um editor de perfil.
- Navegação única: seletor de experiências e seções contextuais, sem aninhar shells de Conta e Studio.
- Editor de perfil dentro de Meu Espaço; perfil público independente em `/u/{apelido}`, no purpose `main`, sob `PublicProfilePolicy`.
- Marley, Ganjalab e WE SATIVA conservam seus nomes, acessos, URLs e módulos donos.
- Trata-se de migração de interface, navegação e apresentação, sem replicação de entidades, permissões, Form API, dados pessoais ou sistemas de autenticação.

## Ownership

| Dono | Continua responsável | Studio poderá oferecer |
| --- | --- | --- |
| `aculta_portal` | Domain/Purpose, dados cadastrais, segurança, formulários de conta e integração | Conteúdo de telas, URLs canônicas e ações autorizadas |
| `aculta_user` | Identidade, avatar, perfil público, bio, redes, visibilidade, amizade e atividade | Editor privado e link ao perfil público real |
| `aculta_messenger` | Avisos, conversas, preferências, contadores | Atalhos e resumos, sem inbox ou armazenamento novo |
| `aculta_mailer` | Transporte de e-mail | Nenhum SMTP paralelo |
| Commerce/apoio | Endereço de cobrança, pedidos, doações e assinaturas | Links e resumos autorizados |
| LMS/Group, Wiki, Revista e Fórum | Dados, acesso, publicações, moderação e atividades | Experiências especializadas, sem criar novas permissões |
| `aculta420` | Layout, SDC/UI Suite, responsividade e acessibilidade | Renderizar contratos neutros, sem lógica de domínio |

**Fonte única de verdade:** o Studio não possui CPF, endereço, senha, avatar, perfil ou mensagens. Sempre respeitar acesso, cache por usuário, propósito e entidades de origem.

## Incremento P1-A — implementação parcial verificada no Git (2026-10-24)

O código agora inclui `PersonalExperienceProvider`, `PersonalController`,
registro no `aculta_studio.experience_manager` e a rota explícita
`aculta_studio.personal` (`/studio/pessoal`, purpose `account`). A entrada
exibe apenas links para rotas existentes da Conta e aplica autorização dupla:
router e `StudioExperienceManager::access()`. Foram adicionados testes Unit
do provider, controller e isolamento de rota. **Os testes ainda não foram
executados em Drupal instalado; nenhuma homologação Runtime é reivindicada.**

`/studio` continua controlado pelo legado Marley; `/` do account,
`/minha-conta` técnico bloqueado, `/u/{apelido}` público, o shell
`AccountShellBuilder` e todos os formulários originais seguem como antes.
P1-B (ponte de Marley e hub) e P2–P7 permanecem para entrega futura.

## Incremento P1-B — ponte editorial e prévia protegida (2026-10-24)

Em `0.1.0-dev.9`, o código registra `MarleyExperienceProvider` no
`StudioExperienceManager`, reutilizando o `MarleyPresenter` para filtrar
áreas realmente disponíveis e autorizadas. `all()` expõe apenas o
inventário de providers, não uma autorização. A nova rota
**`/studio/inicio`** permite examinar o hub futuro sem substituir
`/studio`; a página exibe links para experiências autorizadas com rota
instalada e usa cache dinâmico `max-age: 0` nesta etapa.

Unit tests foram **criados**, mas PHPUnit, Kernel, Runtime e browser
ainda precisam ser executados e homologados. O controller original do
Marley, `/studio` legado, a Conta em `/`, `/u/{apelido}` e os
formulários continuam inalterados. Não confundir a prévia com a migração
completa do menu global ou a ativação do hub canônico.

## Arquitetura existente e evolução

Existem `StudioExperienceManager`, `StudioExperienceProviderInterface` e providers Ganjalab e WE SATIVA. Marley tem `MarleyTabManager` próprio, que não deve ser substituído. O futuro hub poderá cadastrar uma ponte de metadados para Marley e um provider `pessoal`, além de extensão opcional para navegação contextual. Uma experiência disponível não concede acesso a itens de conteúdo.

A Conta atual usa `AccountShellBuilder` e `account-navigation.js`; o Studio tem navegação própria. A migração deverá entregar **um shell por rota**, preservando Institution Bar e Domain Header. Não reaproveitar cegamente o AJAX que extrai `.portal-account__body`: formulários, uploads, segurança, teclado, histórico e navegação sem JavaScript exigem prova independente.

## Experiência pessoal

- **Meu Perfil:** foto, avatar, apelido, biografia, redes e privacidade via `aculta_user`; botão para abrir `/u/{apelido}` em `main`.
- **Minha Conta:** informações básicas, endereço, documentos opcionais e integrações via formulários originais do Portal/Profile.
- **Segurança:** e-mail, confirmação, senha, primeira senha OAuth e conexões, preservando seus handlers.
- **Atividades:** Wiki, Fórum, Revista e conquistas por dados dos módulos donos.
- **Apoio, pedidos e preferências:** resumo e navegação de services existentes.

Preservar `UserPictureForm`/`AvatarCropForm` e fluxo de sorteio/recorte, `AccountAddressForm` com `field_address` compartilhado com checkout, `ProfileVisibilityForm`, o aviso de e-mail não confirmado e o formulário único de aceite.

## Mapa futuro de rotas (não implementado)

| Destino | Proposta | Atual |
| --- | --- | --- |
| Hub | `/studio` | Compatibilidade Marley |
| Meu Espaço | `/studio/pessoal` | Raiz `/` do domínio account |
| Meu Perfil | `/studio/pessoal/perfil` | Editores e formulários User/Conta |
| Dados | `/studio/pessoal/conta` | `/dados` |
| Endereço | `/studio/pessoal/conta/endereco` | `/dados/endereco` |
| Documentos | `/studio/pessoal/conta/documentos` | `/dados/outros-dados` |
| Segurança | `/studio/pessoal/seguranca` | `/seguranca`, `/seguranca/senha` |
| Conexões | `/studio/pessoal/seguranca/conexoes` | `/conexoes` |
| Privacidade | `/studio/pessoal/privacidade` | `/configuracoes/privacidade` |
| Atividade | `/studio/pessoal/atividade` | `/atividade/{aba}` |
| Apoio/pedidos | `/studio/pessoal/apoio`, `/studio/pessoal/pedidos` | `/meu-apoio` e Commerce |
| Preferências | `/studio/pessoal/preferencias` | `/configuracoes` |
| Perfil público | `/u/{apelido}` **mantido** | Purpose `main` |

Não reativar `/minha-conta`: rota técnica bloqueada. Manter a raiz account `/` funcional até o corte. Não reutilizar `/studio/ganjalab` como editor. Preservar aliases históricos e evitar redirecionamentos automáticos de POST, OAuth, AJAX e formulários. Atualizar links por route IDs verificados; compatibilidade GET/HEAD só após testes.

## Roadmap em fases pequenas

| Fase | Entrega | Gate |
| --- | --- | --- |
| P0 | Documentação, inventário de rotas/formulários e owners | Sem mudanças funcionais |
| P1 | Hub, provider pessoal, ponte Marley e registro de experiências | Acesso/cache, sem quebra de rotas |
| P2 | Editor de perfil usando formulários User | Visibilidade, foto e perfil público intactos |
| P3 | Formulários de conta, endereço e segurança originais | Checkout, CEP/S-N, CSRF, OAuth e e-mail |
| P4 | Atividades, Messenger, apoio, pedidos e preferências | ACL, contadores, módulos opcionais |
| P5 | Menu unificado, um shell, AJAX progressivo e compatibilidade | Sem JS, teclado, back/forward e redirects seguros |
| P6 | Testes Unit/Kernel/Functional, Runtime e Firefox ESR/X11 quando autorizado | WCAG, cache, Domain, 320–1440 px, claro/escuro |
| P7 | Corte da navegação antiga após equivalência | Rollback e aceite operacional específico |

**Referências:** [identidade atual](../portal/ACULTA-STUDIO-IDENTITY.md), [arquitetura Studio](ACULTA-STUDIO-EXPERIENCE-PLATFORM.md), [menu atual](../portal/ACCOUNT-MENU.md), [endereço](../portal/ACCOUNT-ADDRESS.md), [perfil](../../web/modules/custom/aculta_portal/modules/aculta_user/docs/PRIVACY-AND-ACCESS.md) e [releases](../operations/RELEASE-LIFECYCLE.md).
