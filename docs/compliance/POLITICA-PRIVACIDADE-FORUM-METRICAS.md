# Rascunho de privacidade — métricas do Fórum e busca

**Estado:** rascunho para revisão humana e jurídica. Não publicado. A coleta de busca permanece ligada apenas no Runtime de desenvolvimento.

## Finalidade

Medir a saúde e a participação do Fórum público para priorizar respostas, moderar conteúdo e dimensionar a comunidade. As métricas são agregadas e não são usadas para perfilamento individual, publicidade ou ranking de pessoas.

## Dados tratados

- Contagens diárias de tópicos, respostas, categorias ativas, tópicos sem resposta, reações e posts pendentes.
- Total de pessoas distintas que contribuíram em janelas de 7 e 30 dias. O agregado guarda apenas o número; o UID não é persistido na central.
- Mediana e percentil 90 do tempo até a primeira resposta pública de outra pessoa.
- Contagem de buscas e de buscas sem resultado. O termo digitado, a sessão, o IP e o user-agent não são gravados.
- Contagem interna de denúncias abertas e resolvidas por conteúdo distinto, sem denunciante, alvo nominal ou parecer.

## Base legal e retenção

- Métricas agregadas e busca: legítimo interesse (LGPD, art. 7º, IX), com finalidade limitada e minimização.
- Denúncias: contagem interna para exercício regular de direitos e moderação.
- Retenção padrão de 90 dias por métrica, configurável no painel. Dados de aluno no LMS seguem regra própria já publicada.

## O que não é feito

- Não se grava termo de busca, IP, e-mail, sessão, user-agent ou texto de conteúdo nas métricas globais.
- Uma métrica agregada não concede acesso ao conteúdo de origem.
- Não há rastreamento oculto, pixel, beacon de visita ou clique nesta fase.
- Não se publica lista nominal de participantes, denunciantes ou pessoas moderadas.

## Antes de produção

1. Revisar e publicar este texto na Política de Privacidade.
2. Confirmar a base legal e o prazo de 90 dias com a assessoria.
3. Manter `forum_discovery.enabled` desligado fora do desenvolvimento até a publicação.
4. Revisar a retenção e o inventário LGPD após a primeira coleta em produção.
