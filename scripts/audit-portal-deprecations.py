#!/usr/bin/env python3
"""Audit aculta_portal for calls to symbols that Drupal Core marks @deprecated.

Deterministic, standard library only. It indexes every @deprecated class,
method and function in web/core and matches them against the Portal source:
class imports, static and instance method calls, and procedural functions.
It does not replace upgrade tooling; it reports what it can see. Exit code 1
when any finding exists, so it can be used as a gate.
"""
import sys
import os, re
ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..'))
CORE = [f'{ROOT}/web/core/lib', f'{ROOT}/web/core/modules']
MODULE = f'{ROOT}/web/modules/custom/aculta_portal'
doc_re = re.compile(r'/\*\*((?:(?!\*/).)*?)\*/\s*(?:#\[[^\]]*\]\s*)*((?:(?:public|protected|private|static|final|abstract|readonly)\s+)*)(class|interface|trait|enum|function|const)?\s*(\w+)?', re.S)
ns_re = re.compile(r'^namespace\s+([\w\\]+);', re.M)
cls_re = re.compile(r'^(?:#\[[^\n]*\]\s*)*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+(\w+)', re.M)
fn_re = re.compile(r'^function\s+(\w+)\s*\(', re.M)

dep_classes = {}   # FQCN -> text
dep_methods = {}   # (FQCN, method) -> text
dep_funcs = {}     # function name -> text
for base in CORE:
    for dp, _, files in os.walk(base):
        for f in files:
            if not f.endswith(('.php','.module','.install','.inc','.theme')): continue
            p = os.path.join(dp, f)
            src = open(p, encoding='utf-8', errors='ignore').read()
            if '@deprecated' not in src: continue
            ns = ns_re.search(src); ns = ns.group(1) if ns else ''
            cm = cls_re.search(src); cname = cm.group(1) if cm else None
            fqcn = f'{ns}\\{cname}' if cname and ns else cname
            # class-level deprecation: docblock immediately before class keyword
            for m in re.finditer(r'/\*\*((?:(?!\*/).)*?)\*/\s*(?:#\[[^\]]*\]\s*)*(?:abstract\s+|final\s+)?(class|interface|trait|enum)\s+(\w+)', src, re.S):
                if '@deprecated' in m.group(1) and ns:
                    dep_classes[f'{ns}\\{m.group(3)}'] = ' '.join(m.group(1).split('@deprecated',1)[1].split())[:200]
            for m in re.finditer(r'/\*\*((?:(?!\*/).)*?)\*/\s*(?:#\[[^\]]*\]\s*)*((?:(?:public|protected|private|static|final|abstract)\s+)*)function\s+(\w+)', src, re.S):
                if '@deprecated' not in m.group(1): continue
                text = ' '.join(m.group(1).split('@deprecated',1)[1].split())[:200]
                if fqcn and cname:
                    dep_methods[(fqcn, m.group(3))] = text
                elif not cname:
                    dep_funcs[m.group(3)] = text

# Module files
mod_files = []
for dp, _, files in os.walk(MODULE):
    for f in files:
        if f.endswith(('.php','.module','.install','.theme')):
            mod_files.append(os.path.join(dp, f))

findings = []
for p in mod_files:
    src = open(p, encoding='utf-8').read()
    rel = p.replace(ROOT+'/','')
    imports = {}
    for m in re.finditer(r'^use\s+([\w\\]+)(?:\s+as\s+(\w+))?;', src, re.M):
        short = m.group(2) or m.group(1).split('\\')[-1]
        imports[short] = m.group(1)
    nsm = ns_re.search(src); own_ns = nsm.group(1) if nsm else ''
    for short, full in imports.items():
        if full in dep_classes:
            findings.append(('class', rel, full, dep_classes[full]))
    for m in re.finditer(r'(?<![\w$\\])\\?([A-Za-z_][\w\\]*)::(\w+)\s*\(', src):
        cls = m.group(1)
        full = imports.get(cls, cls)
        if (full, m.group(2)) in dep_methods:
            findings.append(('static-method', rel, f'{full}::{m.group(2)}', dep_methods[(full, m.group(2))]))
    for m in re.finditer(r'(?<![\w$\\:>])(\w+)\s*\(', src):
        if m.group(1) in dep_funcs and not re.search(r'function\s+'+m.group(1), src):
            findings.append(('function', rel, m.group(1), dep_funcs[m.group(1)]))
    # instance calls: variables typed through constructor promotion / params
    for short, full in imports.items():
        if (full) in {k[0] for k in dep_methods}:
            for mname in {k[1] for k in dep_methods if k[0]==full}:
                if re.search(r'->'+mname+r'\s*\(', src):
                    findings.append(('method', rel, f'{short}->{mname}', dep_methods[(full, mname)]))

print('deprecated classes indexed', len(dep_classes), '| methods', len(dep_methods), '| functions', len(dep_funcs))
seen = set()
for kind, rel, sym, text in findings:
    key = (kind, rel, sym)
    if key in seen: continue
    seen.add(key)
    print(f'[{kind}] {sym}  @ {rel}\n      {text}')
print('total findings', len(seen))
sys.exit(1 if seen else 0)
