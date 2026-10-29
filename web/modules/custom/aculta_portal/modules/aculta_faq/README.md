# ACULTA FAQ

**Versão:** `0.1.0-dev.2`.

Fornece a página pública `/perguntas-frequentes`, o tipo de conteúdo de pergunta e o tipo de bloco editorial reutilizável.
Respostas e blocos aceitam somente o formato `aculta_editorial`: negrito, itálico, listas e links sanitizados pelo filtro
HTML do Drupal. Não habilita HTML livre, CSS, scripts nem mídia embutida.

O painel `/painel-administrativo/faq` exige `administer aculta faq`. A criação de conteúdo também exige as permissões
específicas do tipo e `use text format aculta_editorial`. Configurações versionadas em `config/install`; a ativação no
Runtime segue o procedimento de configuração do projeto.

O conteúdo aprovado está em `docs/CONTEUDO-PERGUNTAS-FREQUENTES.md` e foi
cadastrado no Runtime de teste como seis perguntas públicas. Produção não foi
alterada. O candidato fica em Dev até o gate global módulo–tema ficar verde.

