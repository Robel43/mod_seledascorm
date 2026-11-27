#!/usr/bin/env python3
"""Fail a Moodle build when its static HTML references missing local assets."""
import argparse
import pathlib
import sys
import zipfile
from html.parser import HTMLParser
from urllib.parse import unquote, urlsplit


class Assets(HTMLParser):
    def __init__(self):
        super().__init__()
        self.urls = []

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'script' and values.get('src'):
            self.urls.append(values['src'])
        if tag == 'link' and values.get('href') and values.get('rel') in (
                'stylesheet', 'icon', 'apple-touch-icon', 'modulepreload'):
            self.urls.append(values['href'])


def verify(read, exists):
    html = read('index.html').decode('utf-8')
    parser = Assets()
    parser.feed(html)
    missing = []
    for url in parser.urls:
        parts = urlsplit(url)
        if parts.scheme or parts.netloc or not parts.path:
            continue
        relative = unquote(parts.path).removeprefix('./').lstrip('/')
        if '..' in pathlib.PurePosixPath(relative).parts or not exists(relative):
            missing.append(relative)
    required = ['timhirt-studio/authoring-shell.js', 'timhirt-studio/authoring-shell.css',
                'timhirt-studio/block-library-drawer.js', 'timhirt-studio/block-library-drawer.css',
                'timhirt-studio/section-editing-controls.js', 'timhirt-studio/themes-interface.js',
                'timhirt-studio/native-reporting.js', 'app/yjs/exporters.bundle.js']
    missing.extend(name for name in required if not exists(name))
    if missing:
        raise ValueError('Missing embedded assets: ' + ', '.join(sorted(set(missing))))
    common = read('app/common/common.js').decode('utf-8')
    if 'Seleda universal content-block usage v1' not in common:
        raise ValueError('Embedded editor does not contain the latest block-usage runtime')
    print('PASS: embedded entry assets and latest reporting runtime are present')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('path', help='Static editor directory or complete Moodle plugin ZIP')
    args = parser.parse_args()
    path = pathlib.Path(args.path)
    if path.is_dir():
        verify(lambda name: (path / name).read_bytes(), lambda name: (path / name).is_file())
    else:
        with zipfile.ZipFile(path) as archive:
            names = set(archive.namelist())
            prefix = 'seledascorm/dist/static/'
            verify(lambda name: archive.read(prefix + name), lambda name: prefix + name in names)


if __name__ == '__main__':
    try:
        main()
    except (OSError, KeyError, ValueError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
