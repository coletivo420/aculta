# ADR-030 — Provedor de mapa: Leaflet com OpenStreetMap como padrão e Google Maps como opção

**Status:** aceito em 2026-10-28, pelo responsável. Substitui a escolha de provedor do ADR-014 e a decisão D-08.
**Escopo:** mapas de locais e eventos (`aculta_events`, fase F4), configuração no painel administrativo e
geocodificação de endereços de local.

## Contexto

O ADR-014 escolheu o Google Maps como padrão. O responsável avaliou que o Google inviabiliza o uso do mapa no
projeto (custo, conta de cobrança obrigatória e envio de IP ao Google em toda visualização). A pesquisa de
alternativas registrou que o OpenCulturas usa Leaflet, tiles do OpenStreetMap e o provedor Photon para busca. O
responsável decidiu copiar esse método.

## Decisão

1. **Padrão: Leaflet** (biblioteca livre, BSD-2-Clause) com **tiles do OpenStreetMap públicos**
   (`tile.openstreetmap.org`), com atribuição visível, como no OpenCulturas.
2. **Opção: Google Maps**, escolhida no painel administrativo, para o mapa e a geocodificação. Só é usada se o
   responsável a selecionar.
3. **Seleção no painel:** um formulário de configuração em `/painel-administrativo/eventos/mapa` define o provedor
   (`openstreetmap` ou `google`), o centro e o zoom iniciais, e o zoom para "mostrar no mapa". Sem caminho `/admin`.
4. **Chave do Google no `aculta_secrets`:** a chave `GOOGLE_MAPS_API_KEY` já está declarada em
   `config/secrets-contract.json` (sensível, produção e teste). É lida com `SecretsManager::value()`. Nunca fica no
   formulário, na configuração exportada, no repositório nem no HTML. Se a opção for Google e a chave faltar, o
   mapa mostra a lista e um aviso, sem erro.
5. **Carregamento na página** (D-08 vale): o mapa carrega junto com a página em ambas as opções. Aviso de
   privacidade visível, com link para a seção “Privacidade” de `/termos-de-uso` (caminho indicado pelo responsável
   em 2026-10-28). O conteúdo deve mencionar os provedores e os dados técnicos enviados antes de ativar o link no mapa.
6. **Geocodificação:** no servidor, uma vez por local, guardada no local. Não é feita a cada visualização. Com
   OpenStreetMap, a geocodificação usa o Nominatim público, com User-Agent identificando o ACULTA, no máximo uma
   requisição por segundo, sem autocompletar (ver Riscos).
7. **Atribuição obrigatória** ("© OpenStreetMap contributors") em todo mapa que use tiles do OSM.
8. **Código:** o sistema de mapa do OpenCulturas (`openculturas_map`, GPL-2.0-or-later) é adaptado, não copiado. Cada
   arquivo derivado leva o cabeçalho de atribuição e uma linha em `ORIGEM-OPENCULTURAS.md`. O que sai do
   OpenCulturas: Leaflet, agrupamento de marcadores, filtro de locais e bloco de mapa. O que não sai:
   `openculturas_openstreetmap` (edição no OSM, ver E10) e as configurações de ícone por arquivo.

## Consequências

- **Sem custo de API** para o padrão. Nenhuma conta de cobrança é necessária.
- **IP do visitante vai ao OpenStreetMap** ao carregar os tiles. Isso precisa constar na política de privacidade.
  Este é o mesmo ponto de privacidade que o OpenCulturas registra; a diferença do Google é que o dado vai a uma
  organização sem fins lucrativos, sem cadastro de usuário.
- **Política de uso dos tiles do OSM:** o servidor público não aceita uso pesado. Pela política, é exigido cache,
  User-Agent e atribuição, e é proibido o download em massa. Se o tráfego crescer, o caminho é hospedar os tiles
  (ver `docs/architecture/PLANO-INCORPORACAO-OPENCULTURAS.md`). A troca de provedor é uma opção do painel, sem
  reescrever o código.

## Riscos aceitos pelo responsável

| Risco | Mitigação |
| --- | --- |
| Tráfego alto no servidor público do OSM, com bloqueio do IP do ACULTA | Cache do lado do navegador, sem recarregar tiles sem interação; monitorar; caminho de tiles próprios documentado |
| Geocodificação pelo Nominatim público além do limite | Uma vez por local, com cache no banco; nunca em lote; campo de busca sem autocompletar |
| Dado do visitante enviado ao OSM | Aviso na página e na política de privacidade |
| Geocodificação com Google exige chave e cobrança, se a opção for escolhida | Chave só do `aculta_secrets`; o painel mostra o estado da chave, sem valor |

## Alternativas consideradas

| Alternativa | Por que não agora |
| --- | --- |
| Google Maps como padrão (ADR-014) | Conta de cobrança obrigatória e envio de IP ao Google; decisão do responsável foi revista |
| Tiles próprios (PMTiles) já na v1 | Exige gerar o recorte e servir o arquivo. Fica como caminho para quando o tráfego crescer |
| Photon próprio | Exige RAM acima da disponível no servidor de testes (o Photon indica 64 GB para o planeta) |

## Relação com outras regras

- Credenciais seguem `credenciais-producao-banco`: no banco, criptografadas, em produção; em `secrets/`, só no teste.
- Endereços de ambiente seguem `ENDERECOS-AMBIENTE.md`.
- O ADR-014 continua como registro da análise do Google. Sua escolha de provedor está superada por este ADR.

## Atualização de decisão — D-18 (2026-10-28)

O responsável fixou a configuração final do mapa:

1. **Padrão e fallback:** Leaflet com OpenStreetMap público. Se a opção Google estiver escolhida e a chave faltar ou a API falhar, o mapa volta ao OpenStreetMap, sem erro para o visitante.
2. **Opção Google Maps API:** escolhida no painel. A chave `GOOGLE_MAPS_API_KEY` vem do `aculta_secrets`.
3. **Link de rota do Google sem API:** disponível em qualquer modo. Abre o Google Maps numa nova aba, com o endereço ou a coordenada do local. Não carrega script do Google na página.
4. **URL de rota configurável** no painel do mapa, no mesmo padrão da configuração de links de rota do OpenCulturas (`openculturas_address_links`). O administrador troca o modelo de URL sem alterar código.

Consequência: o visitante só sai do ACULTA por clique, em qualquer modo. Não há dado de visitante enviado ao Google sem ação dele.

## Localização do código (D-19, 2026-10-28)

O mapa e a geocodificação ficam no submódulo `aculta_maps`, e não em `aculta_events`. As regras deste ADR (provedores,
chave no `aculta_secrets`, fallback, link de rota e privacidade) valem para o submódulo. Plano e roadmap:
`web/modules/custom/aculta_portal/modules/aculta_maps/docs/`.
