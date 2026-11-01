"""Check Git candidates for secret signatures without printing their values."""
import json, pathlib, re, subprocess
root = pathlib.Path(__file__).resolve().parent.parent
names = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=root).decode().split('\0')
patterns = {
    'private_key': re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
    'aws_access_key': re.compile(r'\bAKIA[A-Z0-9]{16}\b'),
    'github_token': re.compile(r'\b(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{50,})\b'),
    'literal_credential': re.compile(r'''(?i)["'](?:password|client_secret|api_key|access_token)["']\s*(?:=>|:|=)\s*["'](?![\[\{\$]|example|placeholder|changeme|YOUR_)[A-Za-z0-9+/=_-]{16,}["']'''),
}
findings=[]
for name in names:
    path=root/name
    if not name or not path.is_file(): continue
    if re.search(r'\.(?:sql(?:\..*)?|dump)$', name, re.I): findings.append({'file':name,'type':'database_dump'})
    if path.suffix in ['.png','.jpg','.jpeg','.woff','.woff2','.zip']: continue
    source=path.read_text(encoding='utf-8',errors='replace')
    for kind, pattern in patterns.items():
        if pattern.search(source): findings.append({'file':name,'type':kind})
result={'files_reviewed':len([name for name in names if name]),'findings':findings}
(root/'tmp/final-secrets-audit.json').write_text(json.dumps(result,indent=2))
print(json.dumps(result,indent=2))
raise SystemExit(bool(findings))
