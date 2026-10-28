# Plano de implantação — ACULTA Eventos

Escopo: subdomínio de eventos (`eventos.aculta.*`), com **Catálogo de Eventos**, **Locais com
Mapa** e **Calendário**. Base de portabilidade: OpenCulturas 3.1.4 (GPL-2.0-or-later),
referência em `/home/piradopirata/projetos/aculta-openculturas/` (ver `LEIA-PRIMEIRO.md`).

Submódulos:

- `aculta_events` (este documento): modelo de dados, catálogo, locais, mapa e integração de purpose.
- `aculta_calendar` (`../../aculta_calendar/docs/PLANO-IMPLANTACAO.md`): visões de agenda, filtros,
  exportação `.ics` e incorporação por iframe.

Roadmap único do projeto: `ROADMAP.md`. Origem e licença: `ORIGEM-OPENCULTURAS.md`.
Modelo de dados: `MODELO-DE-DADOS.md`. Visual: `VISUAL.md`.

---

## 1. Escopo

### Dentro da v1

- Purpose novo `eventos`, servido em `eventos.aculta.toca.net.br` (teste) e `eventos.aculta.org` (produção).
- Três conceitos, herdados do OpenCulturas e separados como lá: **Evento** (descrição reutilizável),
  **Data** (ocorrência com início, fim, local e status) e **Local** (endereço e coordenadas).
- Catálogo de eventos com filtros, cartões e página do evento.
- Locais com mapa (Google Maps JavaScript API, marcadores com agrupamento) e lista sincronizada.
- Calendário (submódulo `aculta_calendar`), no subdomínio `eventos` e também no `main`.
- Integração com o Portal: rotas por purpose, sitemap, robots, busca interna, autoria, deployer.

### Fora da v1 (decisão a confirmar, ver seção 3)

- Venda de ingressos, inscrições pagas ou reservas (passa por `aculta_commerce` e Mercado Pago; exige plano próprio).
- Sincronização com OpenStreetMap (`openculturas_openstreetmap`): depende de OAuth externo; se entrar, segue o padrão `/integracoes/<nome>/<ação>` e a janela de popup.
- Presença, lista de espera, certificados e Calendário pessoal por conta.
- Conteúdo de exemplo do OpenCulturas (não importar nada para produção).
- Idiomas além do português.

---

## 2. Princípios herdados (e confirmados pelo ACULTA)

- **Privacidade por padrão:** o OpenCulturas evita CDN e terceiros (`docs/DEVELOPMENT.md`). A
  decisão D7 (Google Maps) é uma exceção consciente a essa regra: o mapa carrega recursos do Google
  e envia o IP do visitante. Por isso o mapa só é carregado sob ação do usuário (ver riscos).
- **Acessibilidade:** teclado, WCAG 2.2 AA, alvos de 44 × 44 px, lista alternativa ao mapa,
  cor nunca como único sinal (ver `VISUAL.md`).
- **Reutilizar antes de criar:** campos, views e modos de exibição do Drupal Core/contrib quando
  bastarem, em vez de módulos novos.
- **Fronteiras do ACULTA:** purpose decide URL e presença; o tema não escolhe identidade pelo host
  (`docs/architecture/multidomain.md`); integrações e endereços de ambiente seguem as regras do
  `aculta_deployer`.

---

## 3. Decisões a confirmar pelo responsável

Recomendação em negrito. Até a confirmação, a implementação não começa (fase F0).

| # | Decisão | Opções | Recomendação |
| --- | --- | --- | --- |
| D1 | Nome interno do purpose | `eventos` / `events` | **`eventos`** (decisão do responsável; difere dos purposes em inglês como `main`, `shop`, `courses`; URLs públicas continuam em português) |
| D2 | Domain ID | `eventos_aculta_org` | **`eventos_aculta_org`** (decidido; padrão do Domain 3.0.1 gerado do host de produção) |
| D3 | Módulos | Um módulo / dois submódulos | **Dois, decidido**: `aculta_events` (dados, catálogo, locais, mapa) e `aculta_calendar` (agenda, ICS, embed) |
| D4 | Tipos de conteúdo | Nomes | **`aculta_event`**, **`aculta_event_date`**, **`aculta_event_location`** (mesmo prefixo dos tipos atuais, como `aculta_forum_topic`) |
| D5 | Identidade visual | Tokens ACULTA420 / marca própria | **Tokens do ACULTA420** (`--aculta-*`), sem paleta do OpenCulturas (ver `VISUAL.md`) |
| D6 | Calendário no `main` | Só no subdomínio / também no `main` | **Decidido: também no `main`.** Mesmas rotas de agenda nos dois purposes; canonical e sitemap seguem a regra de produção (ver `aculta_calendar/docs/PLANO-IMPLANTACAO.md`) |
| D7 | Provedor do mapa | Google Maps / Leaflet com OSM / tiles próprios | **Decidido: Google Maps Platform** (Maps JavaScript API para o mapa; Geocoding API no servidor para coordenadas). Exige chave de API, restrição por domínio em cada ambiente e aviso de privacidade (ver riscos e fase F4) |
| D8 | Dependências contrib | `smart_date`, `geofield`, mapa | **`smart_date` e `geofield` para avaliação**; mapa com JavaScript próprio sobre a Maps JavaScript API (ou módulo contrib, se compatível com Core ^11.4). ICS próprio em vez de `addtocal_augment`. Leaflet não entra |
| D9 | Fuso e recorrência | Fuso do site / por local | **`America/Sao_Paulo` do site** (`config/sync/system.date.yml`); recorrência fica para fase posterior |
| D10 | Organizadores | Texto livre / perfil | **Referência a perfil** (`aculta_user`), com opt-in de identidade pública |
| D11 | Estratégia de branch | Nova PR / PR atual | **Decidido: commits na PR atual** (`chore/aculta-m0-integridade`), publicados por fast-forward, sem force |

---

## 4. Mapeamento OpenCulturas → ACULTA

| OpenCulturas (3.1.4) | ACULTA (destino) | Ação |
| --- | --- | --- |
| `node.type.event` | `aculta_event` | Reimplementar com a mesma semântica (descrição reutilizável) |
| `node.type.date` | `aculta_event_date` | Reimplementar; campos de datas com `smart_date` |
| `node.type.location` | `aculta_event_location` | Reimplementar; endereço (`address`) e coordenadas (`geofield`) |
| `taxonomy event_type`, `location_type`, `category` | `aculta_event_type`, `aculta_location_type`; `category` só se a taxonomia do ACULTA não bastar | Reimplementar (verificar vocabulário existente em `config/sync`) |
| `views.view.event_catalogue` | `aculta_event_catalogue` | Reimplementar com a View do ACULTA; filtros sem `better_exposed_filters` se o Core bastar |
| `views.view.locations`, `locations_opening_hours` | `aculta_event_locations` | Reimplementar |
| `views.view.related_dates_archive`, `related_dates_export`, `related_date` | `aculta_calendar` (arquivo, exportação) e `aculta_events` (datas relacionadas) | Reimplementar |
| `openculturas_map` (bloco, filtro, views `oc_map_*`, `SmartDateUpcomingFormatter`, `ImageThumbnailUrlFormatter`) | `aculta_events`: `src/Plugin/Block/`, `src/Plugin/Field/` | **Não copiar** JS, CSS nem Twig (decisão D7: Google Maps). Usar só como referência para filtros e views. Auditoria: `docs/AUDITORIA-OPENCULTURAS.md` |
| `openculturas_calendar_widget` | `aculta_calendar`: rota de incorporação | Reimplementar; caminho público em português (ex.: `/calendario/incorporar`) |
| `openculturas_custom` `DateEventHooks`, `LocationPrecisionHooks` | `aculta_events` | **Não copiar** `nodePrepareForm` (falha de acesso, achado S1). `LocationPrecisionHooks` trata áreas desenhadas, não privacidade: não portar. Demais hooks só reescritos (ver auditoria) |
| `openculturas_openstreetmap` | Fora da v1 | Registrar como item futuro |
| `opcult` / `openculturas_base` (temas) | SDC em `aculta420` | Padrões visuais; sem cópia de tema, fonte nem paleta |
| `cookies_popover`, `dark_mode_toggle` | Não portar | O ACULTA já tem aceite único e modo escuro por tokens |
| `openculturas_content` (conteúdo de exemplo) | Não portar para produção | Dados de teste apenas em ambiente de teste, se necessário |

---

## 5. Integração com o Portal (checklist obrigatório)

Cada item abaixo vira tarefa do roadmap. Os arquivos citados já existem no repositório.

1. **Domain e purpose:** `DomainPurposeManager::DOMAIN_IDS` (`web/modules/custom/aculta_portal/src/Domain/DomainPurposeManager.php`) ganha `'eventos' => 'eventos_aculta_org'`.
2. **Política de rotas:** `DomainRoutePolicy` e `_aculta_domain_purpose` em cada rota nova. O subscriber bloqueia host errado.
3. **Front do purpose:** `PurposeFrontPathProcessor` e `FrontPathPolicy`, para que `/` do subdomínio mostre a agenda em destaque.
4. **Apresentação:** `DomainPresentationBuilder` entrega marca e navegação do purpose ao tema. `scripts/validate-domain-presentation-contract.php` precisa passar.
5. **Propriedade de conteúdo:** `ContentPurposeResolver` define o purpose de cada tipo novo.
6. **Sitemap:** `aculta_portal_sitemap` (`PurposeNodeUrlGenerator`) lista as URLs canônicas de produção do purpose.
7. **Busca interna:** provedor de índice em `aculta_index` (`Provider/`), no padrão dos outros tipos.
8. **Autoria:** organizadores via `aculta_user` (identidade mínima, opt-in).
9. **Deployer** (`aculta_deployer/config/deploy.json`):
   - `host_rules`: a regra `aculta.toca.net.br` → `aculta.org` já preserva o prefixo, então `eventos.*` funciona sem regra nova.
   - `domain_transition.entries`: nova linha `eventos` com `test_host` e `production_host`.
   - `robots_policy` (teste `noindex`, produção indexável) e `sitemap`: incluir os hosts do purpose.
   - `environment_bound`: `config/sync/domain.record.eventos_aculta_org.yml` e a ação de domínio correspondente.
   - Antes de qualquer host novo, `scripts/validate-environment-addresses.php` precisa passar.
10. **Configuração exportada:** `config/sync/domain.record.eventos_aculta_org.yml`, `system.action.domain_source_set_action.eventos_aculta_org.yml`, aliases de teste conforme o padrão de `cursos`.
11. **Rotas e slugs:** registrar tudo em `docs/architecture/ROUTES-SITEMAP.md` (identificadores `eventos.aculta`, nunca hostname). Gates: `validate-public-slugs.php` e `validate-no-temporary-route-aliases.php`.
12. **Decisão arquitetural:** ADR novo em `docs/decisions/` e atualização da tabela de purposes em `docs/architecture/multidomain.md`.
13. **Infraestrutura do servidor de testes:** vhost e certificado do host de teste (ver seção 7).

---

## 6. Dependências

| Pacote | Uso | Situação |
| --- | --- | --- |
| `drupal/smart_date` | Datas, horários e intervalos | Não está no `composer.json` do ACULTA. Avaliar compatibilidade com Core ^11.4 |
| `drupal/geofield` | Coordenadas | Não está no `composer.json`. Avaliar |
| Google Maps Platform (JavaScript API e Geocoding API) | Mapa, agrupamento de marcadores e geocodificação de endereços | Serviço externo, sem pacote Composer. Chave de API por ambiente: produção no banco, criptografada, provisionada pelo `aculta_deployer` após o deploy; teste em `secrets/`. Restrição de referenciador por host, declarada no deployer. Termos e cobrança do Google a conferir antes de F4 |
| `drupal/address` | Endereço | Já é usado pelo OpenCulturas; verificar se o ACULTA já tem endereço (`aculta_portal_cep` e `aculta_commerce` tratam CEP) |
| `drupal/addtocal_augment` | Exportação | Não usar (ICS próprio, decisão D8) |

Gerar dependências só depois da decisão D8, com `composer require` no fluxo normal e registro em
`composer.lock`. Não instalar nada a partir do pacote do OpenCulturas.

---

## 7. Infraestrutura (ambientes)

- **Teste (esta máquina):** host `eventos.aculta.toca.net.br`. Vhost HTTP já instalado (`/etc/apache2/sites-available/eventos-aculta-http.conf`, `DocumentRoot /home/aculta/public_html`, `X-Robots-Tag: noindex`). Pendências: registro DNS no Cloudflare (`eventos.aculta`, `A 45.191.204.31`, sem proxy) e certificado Let's Encrypt (webroot, como o `growroom420`), depois o vhost HTTPS.
- **Produção:** `eventos.aculta.org`. DNS, certificado e VirtualHost ficam fora do repositório, como em `docs/architecture/environments.md`. Só são feitos no RC.
- **Deploy:** nenhum deploy de produção até todos os módulos, temas e subtemas estarem em RC (preferência do responsável).

---

## 8. Riscos

| Risco | Efeito | Mitigação |
| --- | --- | --- |
| Privacidade do Google Maps | O IP e dados do navegador vão ao Google; quebra da regra de privacidade do OpenCulturas | Mapa carregado só após ação do usuário (botão "Mostrar mapa") ou consentimento pelo aceite único; aviso de privacidade na página; registrar a exceção em decisão (ADR) |
| Chave de API exposta ou usada fora do domínio | Consumo indevido e custo | Restrição por referenciador por host (`eventos.aculta.org`, `eventos.aculta.toca.net.br` etc.) e restrição de API; chave fora do repositório |
| Custo e cota da API | Cobrança inesperada | Cota diária configurada no Google Cloud; geocodificação feita uma vez por local e guardada |
| Geocodificação do Google no servidor | Envio do endereço ao Google | Só endereço de local cadastrado pela organização; nunca dados de pessoa |
| Localização exata do local | Exposição de endereço de pessoa física (local em residência) | Campo de precisão próprio do ACULTA (sem equivalente no OpenCulturas, achado T9) e visibilidade do endereço |
| Recorrência e fuso | Datas erradas no calendário e no `.ics` | Fuso do site; testes com datas de fronteira (DST não se aplica ao Brasil desde 2019, mas testar mudanças de horário) |
| Contrib sem compatibilidade com Core ^11.4 | Bloqueio de F2 | Verificar versão antes de F0 e registrar no `DEBT-REGISTER.md` se necessário |
| Escopo crescer para ingressos | Atraso e risco financeiro | Fora da v1; plano separado |
| Duplicar a agenda do Magazine ou do Studio | Dois lugares para o mesmo evento | Decidir na F0 se a Revista/Studio referencia eventos (`aculta_event`) em vez de criar outro tipo |
| Licença do material copiado | Obrigação GPL não cumprida | Preservar cabeçalhos; `ORIGEM-OPENCULTURAS.md`; revisão antes do RC |
| Visual sem Olivero | Quebra se o tema for removido | Testes com Olivero além do ACULTA420 (regra do responsável) |

---

## 9. Fases

Os critérios de entrada e saída estão no `ROADMAP.md`.

| Fase | Conteúdo | Saída |
| --- | --- | --- |
| F0 | Decisões D1–D11; ADR; revisão de licença e versões contrib | Decisões registradas |
| F1 | Purpose `eventos`: Domain, rotas-esqueleto, deployer, sitemap, robots, gates | Host de teste servindo página vazia com HTTP/HTTPS |
| F2 | Modelo de dados (tipos, campos, vocabulários, permissões), testes Kernel | Cadastro funcional no ambiente de teste |
| F3 | Catálogo de eventos: View, cartão, página do evento | Catálogo navegável com filtros |
| F4 | Locais com mapa: geofield, Google Maps (JavaScript API), bloco, lista sincronizada | Mapa com agrupamento e alternativa textual; carregamento sob ação do usuário |
| F5 | Calendário (`aculta_calendar`): agenda, filtros, `.ics`, embed | Calendário e exportação |
| F6 | Visual e acessibilidade: SDC em `aculta420`, stories, dark mode, 320 px | Revisão visual com Firefox X11 |
| F7 | SEO: sitemap, metadados, dados estruturados de evento | Gates de sitemap e robots verdes |
| F8 | Piloto de conteúdo no ambiente de teste | Validação com usuários |
| F9 | Fechamento: gates, documentação, changelogs, RC | Pronto para RC; deploy só com todos os módulos em RC |

---

## 10. Testes e gates

- **Kernel:** banco `aculta_simpletest` (nunca `aculta_runtime`). Cobrir purpose, rotas, acesso por purpose, datas e fuso.
- **Unit:** regras puras (ICS, filtros, precisão de localização).
- **Navegador:** Firefox ESR visível (`DISPLAY=:0`, geckodriver). Scripts em `tests/browser/aculta-events/` com `README.md`. Não usar Chromium nem headless.
- **Temas:** rodar catálogo, mapa e calendário com **ACULTA420 e com Olivero**.
- **Gates existentes:** `validate-public-slugs.php`, `validate-environment-addresses.php`, `validate-no-temporary-route-aliases.php`, `validate-domain-presentation-contract.php`, `validate-single-acceptance.php` (se houver formulário ou consentimento), `validate-project-status-index.php` (índice gerado, nunca editado à mão).
- **Acessibilidade:** teste de teclado e leitor de tela no mapa e no calendário.

---

## 11. Documentos deste submódulo

| Arquivo | Conteúdo |
| --- | --- |
| `README.md` | Índice e estado do submódulo |
| `PLANO-IMPLANTACAO.md` | Este plano |
| `ROADMAP.md` | Roadmap único (inclui `aculta_calendar`) |
| `ORIGEM-OPENCULTURAS.md` | Origem, licenças, mapa de cópia |
| `MODELO-DE-DADOS.md` | Entidades, campos e relações |
| `VISUAL.md` | Esquema visual, tokens e componentes |
| `upstream-openculturas/` | Cópia da documentação de origem (README, DEVELOPMENT, CONTRIBUTING, SECURITY, CHANGELOG, LICENSE.txt) |
