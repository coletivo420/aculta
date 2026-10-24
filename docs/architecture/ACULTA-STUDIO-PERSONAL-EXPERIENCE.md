# ACULTA Studio — Hub e Meu Espaço

**Planejamento arquitetural, 2026-10-24; não implementado.** Esta fase alinha apenas documentação. Não modifica código, rotas, dados nem Runtime.

## Direção e limites

- `/studio` será futuramente o hub geral autenticado; hoje continua sendo entrada de compatibilidade do Marley.
- `Meu Espaço`, provider planejado `pessoal`, será acessível a membros autorizados em `/studio/pessoal`.
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
