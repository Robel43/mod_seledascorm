"""Regression for the missing timhirt-studio directory that caused a white screen."""
import importlib.util
import pathlib
import unittest

spec = importlib.util.spec_from_file_location('assets', pathlib.Path(__file__).parents[2] / 'scripts/verify-embedded-assets.py')
assets = importlib.util.module_from_spec(spec)
spec.loader.exec_module(assets)


class EmbeddedAssetsTest(unittest.TestCase):
    def test_missing_script_is_rejected(self):
        files = {'index.html': b'<script src="./timhirt-studio/missing.js?v=123"></script>'}
        with self.assertRaisesRegex(ValueError, 'timhirt-studio/missing.js'):
            assets.verify(files.__getitem__, lambda name: False)

    def test_query_strings_are_ignored_and_remote_urls_are_excluded(self):
        files = {'index.html': b'<link rel="stylesheet" href="./style/main.css?v=123"><script src="https://example.org/x.js"></script>',
                 'app/common/common.js': b'Seleda universal content-block usage v1'}
        assets.verify(files.__getitem__, lambda name: '?' not in name)

    def test_stale_runtime_is_rejected(self):
        files = {'index.html': b'', 'app/common/common.js': b'old runtime'}
        with self.assertRaisesRegex(ValueError, 'latest block-usage'):
            assets.verify(files.__getitem__, lambda name: True)


if __name__ == '__main__':
    unittest.main()
