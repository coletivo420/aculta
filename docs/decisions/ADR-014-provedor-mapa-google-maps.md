# ADR-014 — Provedor de mapa: Google Maps Platform como padrão

**Status:** aceito em 2026-10-28, pelo responsável (decisão D-08 de `docs/architecture/DECISOES-RESPONSAVEL-2026-10-28.md`).
**Escopo:** mapas de locais e eventos (`aculta_events`, fase F4) e geocodificação de endereços de local.
**Substitui:** a proposta de OpenStreetMap/Leaflet da auditoria do OpenCulturas (`AUDITORIA-OPENCULTURAS.md`, S6 e T4).

## Contexto

O OpenCulturas usa Leaflet com tiles e geocodificação do OpenStreetMap, carregados pelo navegador do visitante.
O ACULTA precisa de mapa de locais e eventos. O OpenCulturas prega evitar terceiros; o responsável escolheu um
provedor comercial com qualidade de mapa conhecida, e pediu que o mapa apareça já na exibição da página.

## Decisão

1. **Google Maps Platform** é o provedor padrão.
   - **Maps JavaScript API** para renderizar o mapa e os marcadores, com agrupamento de marcadores.
   - **Geocoding API**, chamada **no servidor**, para converter o endereço de um local em coordenadas. O resultado é
     guardado no local; o endereço não é reenviado a cada visualização.
2. **O mapa carrega junto com a página.** Não há botão de ativação. O aviso de privacidade fica visível na página do mapa,
   com link para a política de privacidade do site.
3. **Chave de API por ambiente:**
   - Produção: chave no banco, criptografada, provisionada pelo `aculta_deployer` após o deploy.
   - Teste: `secrets/`, somente no servidor de testes.
   - Restrição de referenciador por host (`eventos.aculta.org`, `eventos.aculta.toca.net.br` e os demais hosts do
     ambiente), e restrição de API às duas APIs acima.
4. **Hosts da restrição** são endereços dependentes de ambiente: ficam no mapeamento do `aculta_deployer`
   (`ENDERECOS-AMBIENTE.md`), e não fixos no código.
5. **Atribuição e termos:** o mapa exibe a atribuição obrigatória do Google, sem ocultá-la. Os termos de uso e a
   cobrança são conferidos antes da fase F4.

## Consequências

- **Dados enviados ao Google:** ao carregar a página, o navegador do visitante envia o endereço IP e o cabeçalho de
  navegador ao Google. Isso precisa constar na política de privacidade do site. Não há cookie próprio do ACULTA para
  esse fim.
- **Geocodificação:** o endereço de local, cadastrado pela organização, é enviado ao Google no servidor. Nunca dados
  de pessoa física, salvo endereço de local que a própria pessoa cadastrou como local público.
- **Custo e cota:** cobrança por uso. Limite diário configurado na conta do Google Cloud; geocodificação uma vez por
  local. Valores não são fixados neste ADR.
- **Código:** nada do OpenCulturas é copiado para o mapa (sem Leaflet, sem tiles do OSM). O JavaScript é próprio do
  ACULTA, sobre a Maps JavaScript API.
- **Revisão de LGPD:** antes do RC, a revisão precisa confirmar a base legal do envio de IP e a redação da
  política de privacidade.

## Alternativas consideradas

| Alternativa | Por que não | Observação |
| --- | --- | --- |
| OpenStreetMap com tiles públicos (Leaflet) | O IP do visitante também vai a terceiros (servidores do OSM). Política de uso dos tiles exige cuidado com volume. | Mesmo problema de privacidade, sem a qualidade e o suporte comercial |
| Tiles próprios (hospedados pelo ACULTA) | Operação, armazenamento e atualização de dados geográficos ficam com a associação | Cumpre a privacidade, custo de operação alto para a v1 |
| Sem mapa na v1 | Locais e eventos ficam só em lista | Mantido como plano B se a revisão de LGPD bloquear o Google |

## Verificações pendentes (antes da F4)

- Termos de uso e atribuição vigentes da Maps JavaScript API e da Geocoding API.
- Conta de cobrança, cota diária e alerta de custo.
- Lista exata de hosts para restrição de referenciador, no deployer.
- Redação da política de privacidade e base legal, com revisão de LGPD.

## Relação com outras regras do projeto

- Credenciais de produção seguem `credenciais-producao-banco`: no banco, criptografadas, nunca em arquivo versionado.
- Endereços de ambiente seguem `ENDERECOS-AMBIENTE.md`: nada fixo no código.
