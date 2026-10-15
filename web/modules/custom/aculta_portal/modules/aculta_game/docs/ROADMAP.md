# Roadmap — ACULTA Game

Escopo de produto aprovado em 2026-10-15. G0 documenta o projeto; G1 em diante
ainda não foram implementadas. Não instalar módulos candidatos antes da fase e
da decisão correspondente. Cada entrega funcional terá PR rastreável.

| Fase | Entrega | Critério de aceite |
| --- | --- | --- |
| G0 — pesquisa e abertura | README, decisões, arquitetura, licenças e evidências de pacotes | Distinguir fonte existente, proposta e compatibilidade ainda não homologada |
| G1 — inventário e fonte única | Auditar concessões atuais e ownership; escolher transição/integração | Preservar IDs/datas/revogações; nenhum segundo backend; atualizar fontes de verdade |
| G2 — integridade dos eventos | Aprovação tardia de comentário, revisão Wiki, status LMS e critérios existentes | Repetição/concorrência não duplicam; importações e legados não recebem novas conquistas |
| G3 — experiência e privacidade | Minha Conta, caixas, catálogo e escolhas de exibição pública | Público por padrão com ocultação, destaques e ordem; zero evidência privada publicada; acessibilidade e cache |
| G4 — missões iniciais | Critérios e progresso sobre fontes reais aprovadas | Sem XP/ranking; limiares aprovados; fonte, escopo e versão do critério rastreáveis |
| G5 — administração e avisos | Concessão/revogação manual, motivo, auditoria e avisos permitidos | Permissões restritas, deduplicação e preferências; sem conceder privilégios editoriais |
| G6 — automação opcional | Spike ECA ou outro backend apenas se houver lacuna demonstrada | Composer e licenças revistos, ownership único, testes de paridade; não instalar ECA e Rules para a mesma regra |
| G7 — integração continuada | Fórum atual, Wiki, Revista, cursos e participação cultural | Mesma conquista e mesma privacidade em todos os consumidores; legado tratado como memória |

G3 pode ser prototipada visualmente enquanto G1/G2 são resolvidas, sem publicar
dados sem a política de acesso. G6 é condicional, não requisito de lançamento.
Open Badges interoperável não faz parte da primeira linha do projeto.

## Matriz mínima de evidência

- Primeiro comentário salvo não publicado e aprovado depois: uma concessão.
- Edição repetida, reenvio de fila e dois workers: nenhuma duplicação.
- Autor real da revisão Wiki, com estado aceito; não premiar mero save do Node.
- LMS `passed`, sem prêmio por compra/matrícula; evidência reservada protegida.
- Conta bloqueada/legada e importação Growroom: nenhuma concessão contemporânea.
- Ocultar conquista ou todos os itens invalida perfil, caixas, contadores e previews.
- Destaques não fazem reaparecer uma conquista oculta nem um perfil privado.
- Revogação manual preserva histórico; automática segue a política existente.
- Desativar Game não apaga concessões nem bloqueia o Portal, User, Fórum ou LMS.

## Versionamento e Runtime

Esta abertura documental não muda `.info.yml`. Antes de codar, declarar a
classificação e a subversão alvo; conferir a sequência de merge contra `main`.
Não criar tags sem pedido explícito. A PR #286 recebe apenas a abertura autorizada.

Aplicar os gates Drupal 11+, fronteira módulo/tema, slugs e endereços quando
pertinentes. Tema também exige Foundation. Alterações de entidade/provider
exigem plano de atualização e recuperação comprovado antes de `updb`/importação.

Para operações de Runtime: confirmar host, diretório, branch, Drush, URI e banco;
inspecionar config drift e efeitos; aplicar incremento coerente no Homelab durante
a implementação. Registrar `RUNTIME STATUS: DEFERRED` para casos não executados,
sem chamar metadata de compatibilidade de teste em PHP 8.5 ou Drupal 12.
