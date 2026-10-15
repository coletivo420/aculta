# Decisões — ACULTA Game

Data: 2026-10-15. Fonte: respostas explícitas do responsável.

## Aprovado

| ID | Decisão | Aplicação |
| --- | --- | --- |
| G-D01 | `aculta_game` será submódulo opcional de `aculta_portal` | Documentação agora; implementação por fases |
| G-D02 | Conquistas e missões, sem ranking | Sem comparação pública ou placar entre pessoas |
| G-D03 | Sem XP/níveis nesta escolha de escopo | Progresso da missão é diferente de saldo de pontos |
| G-D04 | Reconhecimento e progresso sem benefícios financeiros | Sem desconto, crédito, moeda, prêmio monetário ou vantagem de compra |
| G-D05 | Conquistas públicas por padrão | Nova decisão de apresentação para o projeto futuro |
| G-D06 | Pessoa pode ocultar, selecionar destaques e organizar a exibição | Privacidade prevalece nos perfis, caixas, feeds e notificações públicas |
| G-D08 | Apoiadore por pedido concluído, apoio direto no sistema de doações ou administrador | Critérios combináveis; registros passados preservados |
| G-D09 | Criar e editar conquistas na administração Game | Formas de concessão predefinidas, disponibilidade e catálogo próprios; sem expressão executável |
| G-D07 | Reutilizar capacidades e pesquisar licenças antes de adaptar código | Um dono por fato; código de terceiros com proveniência e obrigações respeitadas |

G-D05/06 atualizam as restrições antigas de exibição de badges do plano do
`aculta_user`. A decisão não publica dados privados de evidência: uma conquista
genérica de aprendizagem não revela curso reservado, avaliação ou nota.

## Base preservada até migração própria

`aculta_badge_award` já registra concessões, datas e revogações manuais. A política
registrada do User mantém conquistas automáticas como fatos históricos e permite
revogação administrativa das manuais, sem apagar o registro. Alterá-la exigirá
nova decisão. Regras de participação e permissões não se confundem com conquistas.

## Recomendações técnicas

- Reutilizar inicialmente o backend atual e corrigir aprovação tardia, condições
  de eventos, concorrência e exposição antes de ampliar o catálogo.
- Definições/critério por configuração; progresso consultado das fontes reais;
  concessão histórica persistida uma única vez. Sem linguagem de programação de
  regras própria nem novo saldo derivado de cada clique.
- ECA opcional para automação visual se houver necessidade administrativa real;
  não instalar simultaneamente outro motor concorrente para a mesma regra.
- Importação de Growroom e usuários legados bloqueados não concedem conquistas
  atuais. O reconhecimento memorial é apresentação histórica, sem ranking antigo.
- Evitar missões baseadas só em login repetido, gasto ou preenchimento de dados
  pessoais desnecessários; avaliar qualidade e acesso das contribuições.

## Decisões para a fase de produto

| Tema | O que precisa ser escolhido |
| --- | --- |
| Primeiro catálogo de missões | Nomes, critérios, limiares e quais fontes já têm evidência suficiente |
| Autoria administrativa de regras | Formulário com formas predefinidas implementado em G5.1; ECA segue opcional para lacuna futura |
| Participação opcional | Possibilidade de desligar a experiência além de ocultar conquistas; não decidida nesta sessão |
| Reconhecimento de apoio existente | Decidido: pedido concluído, apoio direto ou administrador; sem faixas de gasto ou benefícios financeiros |
| Perfil básico existente | Rever a exigência de dados pessoais para esse reconhecimento antes de ampliá-lo |
| Uso de backend contrib | Só substituir o atual após prova de conceito e migração de todos os fatos, sem duplicação |

Estas decisões futuras não bloqueiam pesquisa, documentação ou inventário.
