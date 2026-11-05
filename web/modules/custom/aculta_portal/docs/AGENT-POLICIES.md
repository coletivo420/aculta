# Regras específicas — consulta sob demanda

Estas regras foram realocadas do arquivo histórico de instruções; aplique somente no escopo correspondente. O `AGENTS.md` raiz continua obrigatório.

## CTools como implementação do Portal

CTools é uma implementação suportada do projeto, não uma dependência a evitar
por padrão. Antes de criar configuração ou código para uma necessidade que ele
cobre, leia [`web/modules/custom/aculta_portal/docs/CTOOLS.md`](CTOOLS.md) e verifique o código
instalado e a documentação upstream. Use `ctools_views` para blocos de Views
quando for necessário configurar por colocação, por exemplo quantidade de itens
e pager; use a API de plugins/condições do CTools para integrações contextuais
compatíveis. Prefira essa implementação existente a recriar no Portal controles
equivalentes.

CTools não é um construtor geral de formulários nem substitui Views, Forum,
Form API, Webform, Entity Access, políticas de domínio ou cache. Mantenha filtros
de acesso e domínio fixos na View. Não libere `configure_filters`,
`disable_filters`, `hide_fields` ou `sort_fields` em blocos sem revisão explícita
de segurança e governança editorial. A integração documentada do Fórum
complementa a listagem nativa; não a substitui.

Regras resumidas:
- Core/contrib continuam fonte de verdade; não criar storage paralelo quando a capacidade já existir;
- nomes machine name de campos e IDs de bundles Drupal devem ter no máximo 32 caracteres; validar o comprimento antes de criar configuração ou instalar entidades, pois o Core rejeita valores maiores;
- preferir dependency injection em classes; não introduzir novos service locators `\Drupal::...` em `src/`;
- hooks runtime novos/refatorados usam OOP `#[Hook]` quando suportado pelo Core instalado; lifecycle permanece procedural quando exigido;
- tratar como deprecada no projeto qualquer prática runtime legada contrária ao padrão moderno Drupal 11+ (por exemplo `#[FormAlter]`, hook procedural migrável, novo callback global ou novo service locator), mesmo quando ainda tolerada por compatibilidade; exceções precisam de evidência da API upstream;
- EntityQuery declara `accessCheck(TRUE|FALSE)` conscientemente;
- Form API em Drupal 11.3+ prefere callbacks resolvidos pelo `CallableResolver` e serviços DI;
- subscribers usam `EventSubscriberInterface` e o tag `event_subscriber`; prioridades funcionais não mudam por estética;
- Render API, access e cacheability fazem parte do contrato funcional;
- nenhuma mudança é chamada de “Drupal 12/13 ready” sem verificar Core, change records e módulos contrib;
- execute `php scripts/validate-aculta-portal-drupal11.php` em mudanças do Portal e elimine, não expanda, a dívida técnica registrada pelo gate.

A documentação deve registrar o motivo arquitetural das regras, não apenas sua forma.

## Uploads de arquivos enviados por usuários

- Consumidores usam `aculta_file_uploader.consumer_policy`; não duplicar listas de extensões, limites ou validadores.
- Cada domínio continua dono da associação do arquivo, publicação, retenção, acesso e cache. Não criar leitor ou storage paralelo.
- Contrato e sequência: [`docs/portal/USER-UPLOADS.md`](../../../../../docs/portal/USER-UPLOADS.md).

## Fronteira de autenticação e anti-bot

- Core/contrib implementam protocolos e desafios; `aculta_portal` mantém política e integração; ACULTA420 cuida apenas da apresentação.
- Não mover OAuth/CAPTCHA para o tema, consultar configuração de integração no tema, nem adicionar dependências ou segredos de autenticação ao tema.
- Credenciais ficam no arquivo de segredos do servidor, lidas pelo `aculta_portal.secrets_manager`; a exceção de envio de e-mail no banco do Runtime está na regra de segredos acima. Consulte `docs/integrations/AUTHENTICATION.md` e `docs/integrations/CAPTCHA.md`.

- Para detalhes do contrato neutro, Bootstrap/SDC, tokens semânticos, modos de cor e gates, consulte `docs/portal/COMPONENT-DESIGN-SYSTEM.md` e `web/themes/custom/aculta420/docs/design-system.md`.
- Novos componentes usam tokens semânticos e SDC quando houver contrato reutilizável; não criam lógica de domínio no tema nem seletores de modo fora da política documentada.
- Antes de criar markup/CSS, verifique Bootstrap, SDC e componentes existentes. Não adotar uma suíte concorrente sem decisão do responsável.
- O Portal prepara dados, URLs, acesso e cache; ACULTA420 seleciona e renderiza a apresentação. O Portal não referencia componentes SDC do tema.
- Gates de tema e da fronteira Portal–tema permanecem obrigatórios conforme os documentos e scripts de validação correspondentes.

## Política de domínio administrativo

- Regras e exceções: `docs/portal/ADMIN-DOMAIN-POLICY.md`, `CROSS-DOMAIN-REQUEST-POLICY.md` e `PAYMENT-DOMAIN-POLICY.md`.
- Escrita mutável no purpose errado falha fechada; não redirecionar escrita entre hosts. Redirect entre hosts usa `DomainPurposeManager` e `TrustedRedirectResponse`, nunca `RedirectResponse` cru.
- ACULTA420 não participa da política de domínio; consome a apresentação preparada pelo Portal.

## Integração entre módulos e capacidades (regra permanente)

- Antes de criar uma capacidade, inventariar Core/contrib, `aculta_portal` (incluindo submódulos aninhados), `aculta420` e a documentação de integração: dono de dados e política, APIs e serviços, entidades e campos, eventos, Views, formatters, cartões, componentes, cache, acesso, purpose do Domain e consumidores atuais. Consultar contratos, roadmaps, CHANGELOGs e frentes abertas antes de propor código.
- Reutilizar o que tem contrato compatível; não duplicar entidades, storage, processamento, política de upload, renderers, notificações, cartões ou componentes visuais. Lacuna justifica capacidade nova, construída sobre os contratos existentes, com dono, consumidores e fronteira declarados. Reuso não contorna acesso, cache, moderação, privacidade, domínio ou política do módulo responsável.
- Donos já estabelecidos: `aculta_image` (ingestão e política de imagens); `aculta_gallery` (`media_gallery`, galerias e PhotoSwipe); `aculta_pdf_viewer` (leitura de PDF); `aculta_user` (identidade e autoria); `aculta_messenger` (avisos); ACULTA420 (apresentação). Cada consumidor preserva acesso, cache, domínio, publicação e retenção do próprio contexto.
- Integrar por contexto: purpose/Domain, entidade hospedeira e estado de publicação ou moderação. Isolar dados e ações de outros purposes, grupos/turmas, Wiki, Revista, LMS, Fórum e Loja quando os contratos exigirem. Dependências opcionais são unidirecionais, não quebram quando desabilitadas e não criam ciclos.
- Apresentação: procurar primeiro cartões SDC, slots, UI Patterns/UI Styles, Bootstrap, tokens, diálogos Core e componentes do tema. Usar VVJ só em Views e finalidades compatíveis (VVJB para carrossel editorial, VVJS para sequência fotográfica), nunca como substituto de SDC, formulário, view mode ou navegação. Componentes próprios são neutros e reutilizáveis quando há contrato real, preservam render arrays e metadados de access/cache, e deixam a escolha visual ao ACULTA420.
- Toda mudança de integração registra produtor, consumidor, política de acesso/publicação e testes de cada fronteira. Ao concluir uma fase, auditar integrações anteriores pendentes (sem consumidor, contrato quebrado, dívida) e atualizar os roadmaps. Integrar só o que cabe na fase; não marcar capacidade como integrada só por haver código instalado.
- Fórum: fechar primeiro upload contextual de imagens, integração Gallery e Office (DOCX e planilhas, conforme extensões habilitadas). A visualização de PDF pelo Document Viewer é a última etapa; não duplicar leitor ou conversão de PDF no Fórum.

## Código no Aculta Portal, Design no ACULTA420 (regra permanente)

> **Código no Aculta Portal, Design no ACULTA420.**

- Contrato canônico: `docs/portal/FRONTEIRA-MODULO-TEMA.md`. Mapa a atualizar no mesmo commit: `docs/portal/MAPA-MODULO-TEMA.md` (gate `php scripts/validate-module-theme-map.php`).
- Gates de fronteira: `php scripts/validate-module-theme-boundary.php` e `php scripts/validate-aculta-portal-drupal11.php`.
- Alterar `web/themes/custom/aculta420/**` exige autorização explícita na solicitação. Tarefa de Portal não autoriza mudança no tema.

## HTMX como padrão do tema (regra permanente)

- Regra de HTMX e AJAX: `AGENTS.md` (raiz). Aqui vale só a parte do Portal: o Portal entrega fragmentos e respostas HTMX (`Drupal\Core\Htmx\Htmx`) com validação, acesso e cache, sem depender do tema.
- Progresso e exceções: ADR-050 e roadmap do ACULTA420 V2.

## Papéis e hierarquia (regra permanente)

Não crie hierarquia paralela nem conceda acesso por nome de papel. Para funcionalidade nova, conceda a permissão adequada e valide por `hasPermission()`/Entity Access. A configuração e as decisões vigentes estão em `config/sync/aculta_portal.role_hierarchy.yml` e `docs/portal/PAPEIS-PARTICIPACAO.md`; não duplique aqui a lista de papéis. Mudanças na hierarquia exigem avaliação de herança, atualização da decisão e execução de `php scripts/validate-role-hierarchy.php`.
