# Roadmap — ACULTA Game

Escopo de produto aprovado em 2026-10-15. G0 documenta o projeto; G1 inicia a
implementação. Estado e evidências em [EXECUCAO.md](EXECUCAO.md). Não instalar
módulos candidatos antes da fase e da decisão correspondente. Por autorização
do responsável, as fases são commits próprios na PR #286 existente.

| Fase | Entrega | Critério de aceite |
| --- | --- | --- |
| G0 — pesquisa e abertura | README, decisões, arquitetura, licenças e evidências de pacotes | Distinguir fonte existente, proposta e compatibilidade ainda não homologada |
| G1 — concluída; inventário e fonte única | Auditar concessões atuais e ownership; escolher transição/integração | Preservar IDs/datas/revogações; nenhum segundo backend; atualizar fontes de verdade |
| G2 — implementada; homologação pendente | Aprovação tardia de comentário, revisão Wiki, status LMS e critérios existentes | Repetição/concorrência não duplicam; importações e legados não recebem novas conquistas |
| G3 — conta/perfil verificados; acessibilidade completa pendente | Minha Conta, perfil e escolhas de exibição pública; caixas adicionais em G7 | Público por padrão com ocultação, destaques e ordem; zero evidência privada publicada; acessibilidade e cache |
| G4 — implementada e verificada no Homelab | Três missões unitárias, critérios versionados e progresso das conquistas reais | Sem XP/ranking; configuração, navegação, POST, privacidade e acesso verificados |
| G5.1 — implementada/verificada | Criação/edição de conquistas, critérios, concessão/revogação manual, motivos e histórico na área Game | Permissão própria; fontes reais, dados preservados; HTTP 34/34 |
| G5.2 — implementada; entrega de e-mail não verificada | Avisos dispensáveis no Messenger e preferência individual por e-mail | Privacidade, revalidação e envio enfileirado; sem conceder privilégios editoriais |
| G5.3 — perfil público implementado | Cartões de conquistas obtidas em `/u/<apelido>` | Imagem, título, descrição e destaque; preferências respeitadas; evidências privadas excluídas |
| G6 — automação opcional | Spike ECA ou outro backend apenas se houver lacuna demonstrada | Composer e licenças revistos, ownership único, testes de paridade; não instalar ECA e Rules para a mesma regra |
| G7 — integração continuada | Fórum atual, Wiki, Revista, cursos e participação cultural | Mesma conquista e mesma privacidade em todos os consumidores; legado tratado como memória |

G3 pode ser prototipada visualmente enquanto G1/G2 são resolvidas, sem publicar
dados sem a política de acesso. G6 é condicional, não requisito de lançamento.
Open Badges interoperável não faz parte da primeira linha do projeto.

## Área administrativa aprovada

Entrada **Game** no menu administrativo principal, imediatamente depois de
**Avisos** (pesos 9 e 8), em `/painel-administrativo/game`, purpose MAIN.
GET/HEAD em outro host seguem a canonicalização administrativa existente;
requests mutáveis no host errado falham fechado. Permissão restrita
`administer aculta game`.

G4 entrega ativação/desativação das três missões; não desativa os gatilhos de
conquistas nem apaga histórico. G5 amplia a área para gestão manual com motivo,
ator e auditoria, sem CRUD paralelo às entidades Drupal existentes.

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
Não criar tags sem pedido explícito. A PR #286 recebe a implementação por fases autorizada.

Aplicar os gates Drupal 11+, fronteira módulo/tema, slugs e endereços quando
pertinentes. Tema também exige Foundation. Alterações de entidade/provider
exigem plano de atualização e recuperação comprovado antes de `updb`/importação.

Para operações de Runtime: confirmar host, diretório, branch, Drush, URI e banco;
inspecionar config drift e efeitos; aplicar incremento coerente no Homelab durante
a implementação. Registrar `RUNTIME STATUS: DEFERRED` para casos não executados,
sem chamar metadata de compatibilidade de teste em PHP 8.5 ou Drupal 12.
