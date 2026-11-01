"""Compare Composer-installed Drupal sources with their cached release ZIPs. Read only."""
import hashlib, json, pathlib, zipfile

root = pathlib.Path(__file__).resolve().parent.parent
cache = pathlib.Path('C:/Users/PiradoPirata/AppData/Local/Composer/files')
report = []
for package in json.loads((root / 'composer.lock').read_text())['packages']:
    name = package['name']
    if name == 'drupal/core':
        target = root / 'web/core'
    elif package.get('type') == 'drupal-module':
        target = root / 'web/modules/contrib' / name.split('/')[-1]
    elif package.get('type') == 'drupal-theme':
        target = root / 'web/themes/contrib' / name.split('/')[-1]
    else:
        continue
    archive = cache / name / (hashlib.sha1(package['dist']['url'].encode()).hexdigest() + '.zip')
    archives = [archive] if archive.exists() else list((cache / name).glob('*.zip'))
    if len(archives) != 1:
        report.append({'package': name, 'version': package['version'], 'status': 'CACHE_MISSING_OR_AMBIGUOUS'})
        continue
    changed = []
    count = 0
    with zipfile.ZipFile(archives[0]) as release:
        prefix = release.namelist()[0].split('/')[0] + '/'
        for member in release.infolist():
            if member.is_dir():
                continue
            relative = member.filename.removeprefix(prefix)
            installed = target / relative
            if not installed.is_file() or hashlib.sha256(installed.read_bytes()).digest() != hashlib.sha256(release.read(member)).digest():
                changed.append(relative)
            count += 1
    report.append({'package': name, 'version': package['version'], 'files': count, 'differences': changed, 'status': 'OK' if not changed else 'DIFFERENT'})
(root / 'tmp/final-package-integrity.json').write_text(json.dumps(report, indent=2))
print(json.dumps(report, indent=2))
raise SystemExit(any(item['status'] != 'OK' for item in report))
