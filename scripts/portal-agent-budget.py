#!/usr/bin/env python3
"""Read-only tier suggestion and concise handoff context for ACULTA agents."""
import argparse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TIERS = {
    'research': ('economico', 'Pesquisar e citar caminhos/linhas relevantes.'),
    'docs': ('economico', 'Editar documentos com diffs pequenos.'),
    'basic': ('economico', 'Executar tarefa deterministica e verificar.'),
    'implementation': ('intermediario', 'Validar diffs, contratos e testes.'),
    'review': ('avancado', 'Revisao independente de riscos e regressao.'),
    'final-review': ('avancado', 'Revisao formal, release e merge.'),
    'security': ('avancado', 'Access, privacidade e cache sensivel.'),
    'auth': ('avancado', 'Identidade, OAuth, sessoes e contas.'),
    'payment': ('avancado', 'Commerce, segredos e fail-closed.'),
}

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    route = sub.add_parser('route')
    route.add_argument('task', choices=TIERS)
    context = sub.add_parser('context')
    context.add_argument('phase')
    context.add_argument('max_chars', nargs='?', type=int, default=3000)
    args = parser.parse_args()
    if args.command == 'route':
        tier, reason = TIERS[args.task]
        print(f'tier={tier}\nnote={reason}\nselection=manual')
        return 0
    if args.max_chars < 200:
        parser.error('max_chars must be >= 200')
    content = [
        'Projeto: Modernização Drupal 11+ Aculta Portal',
        f'Fase: {args.phase}',
        'Portal portal-v0.1.0; conferir HEAD antes de atuar.',
        'Drupal 11+ moderno, DI, access/cache e gates; sem merge.',
        'Portabilidade entre bancos pertence a DBTNG-2.',
        'Consultar os arquivos-alvo e documentação original antes de editar.',
    ]
    for relative in ('docs/portal/ROADMAP.md', 'docs/operations/DEBT-REGISTER.md'):
        file = ROOT / relative
        if file.is_file():
            matches = [line.strip() for line in file.read_text(encoding='utf-8').splitlines() if args.phase.lower() in line.lower()]
            content.append(f'Fonte: {relative}')
            content += [line[:220] for line in matches[:3]]
    output = '\n'.join(content)
    if len(output) > args.max_chars:
        output = output[:args.max_chars - 14] + '\n[TRUNCADO]'
    print(output)
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
