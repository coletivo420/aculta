# ACULTA FAQ

Fornece a página pública `/perguntas-frequentes`, o tipo de conteúdo de pergunta e o tipo de bloco editorial reutilizável.
Respostas e blocos aceitam somente o formato `aculta_editorial`: negrito, itálico, listas e links sanitizados pelo filtro
HTML do Drupal. Não habilita HTML livre, CSS, scripts nem mídia embutida.

O painel `/painel-administrativo/faq` exige `administer aculta faq`. A criação de conteúdo também exige as permissões
específicas do tipo e `use text format aculta_editorial`. Configurações versionadas em `config/install`; a ativação no
Runtime segue o procedimento de configuração do projeto.

