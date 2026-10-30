# ACULTA MCP

O módulo `aculta_mcp` é dono do endpoint MCP ACULTA e também funciona como cliente MCP de documentação do Mercado Pago, Melhor Envio e DHTMLX. Reutiliza `mcp/sdk` já instalado pelo `mcp_server` e adapta o fluxo HTTP do Drupal MCP Client 1.0.0-alpha3 (GPL-2.0-or-later), sem instalar o projeto contrib incompatível (`mcp/sdk ^0.7.1` e Drupal AI ausente). A extensão antiga `aculta_mcp_client` permanece como shim vazio para preservar referências de instalação; sua desinstalação exige uma ordem separada.

O plugin Resource Provider `public_capabilities` publica o recurso estático `aculta://public/mcp/capabilities`, que descreve as ferramentas e seus limites. O conteúdo não consulta entidades nem conteúdo do site. Sua leitura exige autenticação e autorização no endpoint MCP. Sua configuração está em `config/install/mcp_server.resource_providers.yml` e em `config/sync/mcp_server.resource_providers.yml`.

O módulo declara seis plugins da Tool API e instala configurações MCP Tool Configuration para eles:

- Busca na documentação do Mercado Pago, fixada no site Brasil (`MLB`). Usa a Key `mercadopago_test_access_token`, resolvida em tempo de chamada; o valor não entra em config exportada nem em logs.
- Busca por endpoints do Melhor Envio.
- Leitura de documentação de um endpoint Melhor Envio.
- Listagem de endpoints documentados Melhor Envio.
- Busca semântica ou por palavra-chave na documentação DHTMLX.
- Resposta a pergunta factual baseada na documentação DHTMLX.

As conexões externas têm endpoint fixo no código, limite global de chamadas e allowlist de nomes de ferramentas. O DHTMLX usa seu serviço MCP hospedado; a documentação do provedor informa que consultas podem ser registradas para melhoria do serviço. Nenhum input pode escolher URL, cabeçalho, ferramenta remota arbitrária, chamada transacional, criação de etiqueta, pagamento, cancelamento, estorno, conta ou credencial. Respostas dos provedores são texto externo e não confiável.

O Resource Provider publica três documentos estáticos: capacidades MCP, visão geral pública do ACULTA e catálogo resumido de integrações. O hook `mcp_server_instructions_alter` orienta clientes no handshake inicial para consultar o Resource de capacidades, não enviar dados privados e tratar respostas remotas como referência não confiável. O conteúdo é curado a partir das documentações e do código público; não carrega entidades nem configuração do Runtime.

O módulo também instala três MCP Prompts (`aculta_consultar_plataforma`, `aculta_planejar_integracao_publica` e `aculta_pesquisar_documentacao_mcp`). Eles recebem uma pergunta técnica explícita, anexam como conteúdo MCP o Resource público pertinente e instruem o modelo a não inventar fatos nem enviar dados privados. O prompt de pesquisa orienta o cliente a selecionar e chamar uma ferramenta de documentação; prompts MCP são modelos de mensagens, não executam ferramentas por conta própria. Para manter os anexos sincronizados, ao editar os Resources atualize os respectivos blocos `type: resource` em `config/install/mcp_server.mcp_prompt_config.*.yml`.

Na atualização de um site onde a extensão antiga já estava habilitada, `aculta_mcp_update_10001()` migra as referências dos plugins e prompts, remove o acesso MCP do papel anônimo e prepara o OAuth. Em uma instalação nova, os arquivos `config/install/` criam as configurações necessárias.

As seis MCP Tool Configurations ficam em `config/install/` para que o Tool Bridge as instale junto com este módulo; também aparecem em `/admin/config/services/mcp-server/tools`.

## Autenticação e autorização

O endpoint HTTP está disponível somente no domínio principal (`main`), em `/integracoes/mcp`; nos subdomínios, responde 404. Todo o endpoint exige autenticação e autorização. Para acesso externo, o servidor usa OAuth 2.1 Authorization Code com PKCE `S256`, identidade individual Simple OAuth e o escopo `aculta_mcp_access`. O escopo concede somente a permissão Drupal `access mcp server`; cada conta também precisa ter essa permissão.

Administradores selecionam as contas autorizadas em **Serviços web → MCP Server → Usuários autorizados**, rota `/admin/config/services/mcp-server/access`. A seleção atribui a role dedicada `mcp_external`; contas novas não recebem acesso automaticamente. Usuários internos com a permissão Drupal podem usar uma sessão `cookie`, sem OAuth. O transporte local `stdio` continua sujeito às permissões Drupal da conta que executa o processo.

Cada cliente OAuth deve ser pré-cadastrado como Consumer Drupal, com os grant types `authorization_code` e `refresh_token`, escopo `aculta_mcp_access`, PKCE obrigatório e redirect URIs exatos. O MCP Server OAuth depende de Simple OAuth Client Registration, mas o ACULTA remove as rotas públicas `/oauth/register` e `/oauth/register/{client_id}`; o metadata não anuncia registro dinâmico.

As ferramentas MCP Tool Bridge não exigem escopos individuais: a permissão da rota cobre conjuntamente Tools, Prompts e Resources e mantém disponíveis as sessões Drupal internas.

## Limites

Este módulo publica somente documentação de Mercado Pago, Melhor Envio e DHTMLX; não transforma os serviços de pagamento e frete do ACULTA em operações MCP. O OSM MCP e `aculta-drupal` continuam nos clientes locais; não são encaminhados por este endpoint. Credenciais, contas, pagamentos, pedidos, endereços, etiquetas e operações financeiras/logísticas não são expostos.

## Fonte adaptada

- Drupal MCP Client 1.0.0-alpha3, `src/MCPClient.php` (GPL-2.0-or-later): https://www.drupal.org/project/mcp_client
- A adaptação usa apenas o padrão HTTP do MCP SDK (`Mcp\\Client` e `HttpTransport`) e fixa a revisão de protocolo 2025-03-26, compatível com os servidores remotos verificados. Não copia as integrações Drupal AI/AI Agents do módulo original.
