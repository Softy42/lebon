"""Contrôles locaux de structure et de publication, sans réseau ni email."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urljoin, urlsplit
import hashlib
import importlib.util
import json
import re
import subprocess
import sys

form = Path(__file__).resolve().parents[1]
repo = form.parent
spec = importlib.util.spec_from_file_location('deploy_structure', repo / '.github/scripts/deploy_ionos.py')
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)

class Contact(HTMLParser):
    def __init__(self):
        super().__init__()
        self.action = None
        self.scripts = []
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'form' and attrs.get('id') == 'contactForm':
            self.action = attrs.get('action')
        if tag == 'script' and attrs.get('src'):
            self.scripts.append(attrs['src'])

html = (repo / 'contact/index.html').read_text()
js = (repo / 'contact/formulaire.js').read_text()
page = Contact()
page.feed(html)
assert page.action, 'Action du formulaire absente'
endpoint = urlsplit(urljoin('https://www.maison-melina.fr/contact/', page.action))
assert endpoint.netloc == 'www.maison-melina.fr' and endpoint.path == '/api/formulaire.php'
assert (repo / endpoint.path.lstrip('/')).is_file()
scripts = [urlsplit(urljoin('https://www.maison-melina.fr/contact/', src)) for src in page.scripts]
assert any(src.path == '/contact/formulaire.js' and src.netloc == endpoint.netloc for src in scripts)
assert 'vercel.app' not in html + js, 'Ancien appel Vercel dans le formulaire public'
for public in ['contact/index.html', 'contact/formulaire.js', 'api/formulaire.php', 'api/.htaccess']:
    assert (repo / public).is_file() and deploy.public_path(public), public

vendor = form / 'prive/lib/PHPMailer'
origin = json.loads((vendor / 'ORIGINE.json').read_text())
for name, digest in origin['sha256'].items():
    assert hashlib.sha256((vendor / name).read_bytes()).hexdigest() == digest, 'Dépendance altérée : ' + name
assert (vendor / 'LICENSE').is_file()
for model in (form / 'config').glob('*.example.php'):
    text = model.read_text()
    for key in ['app_key', 'recaptcha_site_key', 'recaptcha_secret', 'password']:
        assert re.search(r"'" + key + r"'\s*=>\s*''", text), 'Secret non vide dans le modèle : ' + key

for path in form.rglob('*'):
    if path.is_file():
        assert not deploy.public_path(path.relative_to(repo).as_posix()), 'Source privée publiable : ' + str(path)
assert not list(form.rglob('config.php')), 'Configuration secrète dans les sources permanentes'
for private in ['prive/config.php', 'prive/donnees/limites.json']:
    result = subprocess.run(['git', 'check-ignore', '--quiet', str(form / private)], cwd=repo)
    assert result.returncode == 0, 'Exclusion Git manquante : ' + private
print('OK : sources publiques cohérentes ; sources privées non publiables ; dépendance intègre ; modèles sans secrets ; exclusions Git actives.')
