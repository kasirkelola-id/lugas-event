"""Verify the candidate with synthetic files and loopback only; retain evidence."""
import argparse
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--nginx', required=True, type=Path)
    args = parser.parse_args()
    executable = args.nginx.resolve(strict=True)
    if executable.name.lower() not in ('nginx', 'nginx.exe'):
        parser.error('Provide the actual local Nginx binary')
    evidence = Path(tempfile.mkdtemp(prefix='kartar-nginx-probe-')).resolve()
    fixture = evidence / 'fixture'
    fixture.mkdir()
    allowed = ['/assets/synthetic.css', '/uploads/users/profile/synthetic.png',
               '/uploads/karang_taruna/logos/synthetic.webp']
    denied = ['/.env', '/.git/config', '/app/Config/private.json', '/vendor/autoload.php',
              '/writable/logs/private.log', '/tests/private.txt', '/tools/private.txt',
              '/build/private.txt', '/deploy/private.txt', '/composer.json', '/composer.lock',
              '/phpunit.xml', '/spark', '/env', '/backup.sql', '/private.ini',
              '/api/internal/socket-auth', '/internal/wheel-event',
              '/uploads/users/profile/synthetic.php', '/uploads/users/profile/synthetic.phtml',
              '/uploads/users/profile/synthetic.phar', '/uploads/users/profile/synthetic.php.png',
              '/uploads/users/profile/.synthetic.png', '/uploads/users/profile/synthetic.php/tail.png',
              '/assets/.private.css', '/other.php', '/index.php/tail']
    for url in allowed + denied + ['/index.php']:
        if '.php/' in url:
            continue  # PATH_INFO follows a file; it cannot also be a directory.
        target = fixture / url.lstrip('/')
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text('synthetic-private-marker', encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    shutil.copyfile(executable.parent / 'conf/fastcgi_params', evidence / 'fastcgi_params')
    locations = Path(__file__).resolve().parent / 'nginx/kartar-locations.conf'
    config = evidence / 'nginx.conf'
    config.write_text(
        f'worker_processes 1;\npid "{evidence.as_posix()}/nginx.pid";\n'
        f'error_log "{evidence.as_posix()}/error.log" warn;\n'
        'events { worker_connections 128; }\nhttp {\n'
        f'include "{(executable.parent / "conf/mime.types").as_posix()}";\n'
        "map $http_upgrade $kartar_connection_upgrade { default upgrade; '' close; }\n"
        f'server {{ listen 127.0.0.1:{port}; root "{fixture.as_posix()}";\n'
        f'include "{locations.as_posix()}"; }} }}\n', encoding='utf-8')
    command = [str(executable), '-p', str(executable.parent) + os.sep, '-c', str(config)]
    flags = subprocess.CREATE_NO_WINDOW if os.name == 'nt' else 0
    syntax = subprocess.run(command + ['-t'], capture_output=True, timeout=10, creationflags=flags)
    (evidence / 'syntax.log').write_bytes(syntax.stdout + syntax.stderr)
    if syntax.returncode:
        raise RuntimeError(f'Config syntax failed; evidence: {evidence}')
    rows = []
    stream = (evidence / 'process.log').open('wb')
    process = subprocess.Popen(command, stdout=stream, stderr=stream, creationflags=flags)
    # Avoid system HTTP proxies: every probe connects directly to loopback.
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))

    def fetch(url):
        try:
            response = opener.open(f'http://127.0.0.1:{port}{url}', timeout=3)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.code, response.read(), response.headers

    try:
        for _ in range(100):
            try:
                fetch(allowed[0])
                break
            except urllib.error.URLError:
                time.sleep(.05)
        cases = [(url, 200) for url in allowed] + [(url, 404) for url in denied]
        cases += [('/%2eenv', 404), ('/APP/Config/private.json', 404),
                  ('/uploads/users/profile/synthetic.php%2ftail.png', 404)]
        for url, expected in cases:
            status, body, headers = fetch(url)
            passed = status == expected and headers.get('X-Content-Type-Options') == 'nosniff'
            if expected == 404:
                passed = passed and b'synthetic-private-marker' not in body
            rows.append({'path': url, 'status': status, 'expected': expected, 'passed': passed})
        (evidence / 'http.json').write_text(json.dumps(rows, indent=2), encoding='utf-8')
        if not all(row['passed'] for row in rows):
            raise RuntimeError(f'HTTP protection failed; evidence: {evidence}')
        print(json.dumps({'passed': len(rows), 'failed': 0, 'evidence': str(evidence),
                          'classification': 'LOCAL SYNTHETIC ONLY; production verification required'}))
    finally:
        stop = subprocess.run(command + ['-s', 'quit'], capture_output=True, timeout=10, creationflags=flags)
        (evidence / 'shutdown.log').write_bytes(stop.stdout + stop.stderr)
        if stop.returncode:
            raise RuntimeError(f'Owned Nginx shutdown failed; evidence: {evidence}')
        process.wait(timeout=10)
        stream.close()
        for _ in range(100):
            with socket.socket() as sock:
                if sock.connect_ex(('127.0.0.1', port)) != 0:
                    break
            time.sleep(.05)
        else:
            raise RuntimeError(f'Owned loopback listener did not stop; evidence: {evidence}')


if __name__ == '__main__':
    main()
