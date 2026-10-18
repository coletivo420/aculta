# Auditoria inicial de H5P para o ACULTA LMS

Revisão em 2026-10-18. Esta é a primeira entrega da F10; não habilita H5P nem
altera o Composer lock, configuração ou Runtime.

## Decisão técnica desta etapa

Não instalar nem habilitar `drupal/h5p` ou `drupal/lms_h5p` neste momento. A
versão Drupal 11 disponível consultada é `2.0.0-beta1`; a página do projeto
informa que não há release estável atualmente coberta pela política de
advisories. O release lista como issue conhecida o upload de pacotes `.h5p`
com bibliotecas de tipos restritos, apesar da permissão negar a criação desses
tipos. A issue #3548290 classifica o caso como possível bypass de acesso e
permanece sem correção publicada na versão beta consultada. Esse risco impede
um upload de autoria não restrito a administrador.

O conector `drupal/lms_h5p` também não tem cobertura da política de segurança,
está em alpha e depende de H5P e LMS XAPI. Ele envia respostas por statements
XAPI a um LRS e pode pontuar automaticamente no Drupal. Isso não satisfaz o
contrato ACULTA atual: LMS continua a fonte única da tentativa e da nota, e
nenhuma nota externa é aceita sem API confiável, identidade de tentativa,
origem/autenticidade, repetição e autoria demonstradas.

O projeto H5P não está no Composer lock nem instalado no Runtime; a verificação
local de `composer show --locked` confirmou ambos. Não foram resolvidas
dependências, baixadas bibliotecas H5P, instalados módulos ou criadas entidades.

## Evidências consultadas

- A página de projeto H5P consultada lista apenas `2.0.0-beta1` para Drupal 11,
  declara suporte `^10.2 || ^11`, registra que não há release estável suportada
  e remete ao Composer.
- As notas do `2.0.0-beta1` registram correções relevantes, incluindo vazamento
  entre usuários por ausência de cache context; também listam como conhecido o
  bypass das restrições de tipos H5P no upload de pacote.
- A issue [#3548290](https://www.drupal.org/project/h5p/issues/3548290)
  documenta a reprodução e chama o caso de potencial bypass de acesso.
- A página do projeto
  [LMS H5P](https://www.drupal.org/project/lms_h5p) declara ausência de
  cobertura de security advisory, release alpha, dependência do LMS XAPI e
  suporte a score automático via XAPI/LRS.
- A análise é documental. Código executável, configuração de upload, CSP,
  isolamento de iframe, política de bibliotecas e integração de respostas ainda
  não foram revisados porque o pacote não está instalado.

## Gate restante da F10

Antes de qualquer instalação ou ativação, repetir a pesquisa em Drupal.org e
Composer e exigir release/patch com política de segurança e a correção da issue
de upload demonstrada no tag exato. Em seguida, revisar source e testes de
permissões, ingestão/validação de `.h5p`, bibliotecas executáveis, storage,
cacheability, CSP e sandbox de iframe. Exercitar acesso do estudante/educador,
isolamento por turma e remoção/revogação. Manter score desativado; qualquer
integração de resultado exige contrato LMS confiável e aprovação específica.

Até esses gates passarem, a F10 permanece em auditoria com ativação bloqueada.
