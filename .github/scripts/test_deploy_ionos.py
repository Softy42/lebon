"""Local SFTP simulation: no credentials or network required."""
import errno
import importlib.util
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location("deploy", Path(__file__).with_name("deploy_ionos.py"))
d = importlib.util.module_from_spec(spec)
spec.loader.exec_module(d)


class SFTP:
    def __init__(self, root):
        self.root = root
        self.cwd = d.REMOTE_ROOT
        self.fail = None
        self.puts = []
    def path(self, name):
        return self.root / (name.lstrip("/") if name.startswith("/") else self.cwd.lstrip("/") + "/" + name)
    def lstat(self, name): return self.path(name).lstat()
    def chdir(self, name): self.cwd = name
    def open(self, name, mode): return self.path(name).open(mode)
    def mkdir(self, name, mode=0o755): self.path(name).mkdir(mode=mode)
    def chmod(self, name, mode): pass
    def put(self, local, remote, confirm=True):
        self.puts.append(remote)
        shutil.copyfile(local, self.path(remote))
    def posix_rename(self, source, target):
        if target == self.fail: raise OSError("simulated transfer failure")
        self.path(source).replace(self.path(target))
    def remove(self, name): self.path(name).unlink()


class DeploymentTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.local = self.root / "local"
        self.local.mkdir()
        self.sftp = SFTP(self.root / "server")
        self.site = self.sftp.path("index.html").parent
        self.site.mkdir(parents=True)
        (self.site / "index.html").write_bytes(b'https://www.maison-melina.fr/ old')
        (self.site / "unchanged.css").write_bytes(b'unchanged')
        (self.site / "untracked.txt").write_bytes(b'server only')
        (self.local / "index.html").write_bytes(b'https://www.maison-melina.fr/ new')
        (self.local / "popup-config.js").write_bytes(b'export const POPUP_ENABLED = false;')
        (self.local / "unchanged.css").write_bytes(b'unchanged')
        self.state = d.read_state(self.sftp)
        self.files = ['popup-config.js', 'unchanged.css', 'index.html']
    def make_plan(self): return d.plan(self.sftp, self.local, self.files)
    def publish(self): return d.publish(self.sftp, self.local, self.make_plan(), self.state, 'a' * 40)

    def test_preview_is_read_only_and_excludes_identical(self):
        changes = self.make_plan()
        self.assertEqual([x['path'] for x in changes], ['popup-config.js', 'index.html'])
        self.assertEqual(self.sftp.puts, [])
        self.assertFalse(self.sftp.path(d.BACKUP_ROOT).exists())

    def test_publish_and_restore_actual_server_version(self):
        before = {p.name: p.read_bytes() for p in self.site.iterdir()}
        self.publish()
        self.assertEqual((self.site / 'untracked.txt').read_bytes(), b'server only')
        self.assertEqual((self.site / 'index.html').read_bytes(), (self.local / 'index.html').read_bytes())
        state = d.read_state(self.sftp)
        self.assertEqual(state['revision'], 'a' * 40)
        d.restore(self.sftp, state)
        self.assertEqual(before, {p.name: p.read_bytes() for p in self.site.iterdir()})
        self.assertEqual(d.read_state(self.sftp), self.state)
        self.assertTrue(self.sftp.path(d.BACKUP_ROOT + '/' + state['latest'] + '/0.after').exists())

    def test_partial_failure_can_be_restored(self):
        self.sftp.fail = 'index.html'
        with self.assertRaises(OSError): self.publish()
        state = d.read_state(self.sftp)
        self.assertIsNotNone(state['pending'])
        self.assertTrue((self.site / 'popup-config.js').exists())
        self.sftp.fail = None
        d.restore(self.sftp, state)
        self.assertFalse((self.site / 'popup-config.js').exists())
        self.assertIn(b' old', (self.site / 'index.html').read_bytes())

    def test_backup_failure_changes_no_live_files(self):
        self.sftp.fail = d.BACKUP_ROOT + '/state.json'
        with self.assertRaises(OSError): self.publish()
        self.assertFalse((self.site / 'popup-config.js').exists())
        self.assertIn(b' old', (self.site / 'index.html').read_bytes())

    def test_restore_refuses_subsequent_manual_changes(self):
        self.publish()
        (self.site / 'index.html').write_bytes(b'manual edit')
        with self.assertRaises(RuntimeError): d.restore(self.sftp, d.read_state(self.sftp))
        self.assertTrue((self.site / 'popup-config.js').exists())
        self.assertEqual((self.site / 'index.html').read_bytes(), b'manual edit')

    def test_corrupt_backup_stops_before_any_restore(self):
        self.publish()
        state = d.read_state(self.sftp)
        self.sftp.path(d.BACKUP_ROOT + '/' + state['latest'] + '/1.before').write_bytes(b'bad')
        with self.assertRaises(RuntimeError): d.restore(self.sftp, state)
        self.assertTrue((self.site / 'popup-config.js').exists())

    def test_incremental_selection_and_lfs_guard(self):
        with patch.object(d.subprocess, 'check_output', return_value=b'index.html\0.github/scripts/deploy_ionos.py\0popup-config.js\0') as git:
            self.assertEqual(d.collect_files(self.local, d.INITIAL_REVISION), ['popup-config.js', 'index.html'])
            self.assertIn('--diff-filter=AMRT', git.call_args.args[0])
            (self.local / 'popup-config.js').write_bytes(b'version https://git-lfs.github.com/spec/v1')
            with self.assertRaises(RuntimeError): d.collect_files(self.local, d.INITIAL_REVISION)

    def test_path_and_host_guards(self):
        for path in ['../index.html', '/index.html', '.git/config', '.github/file.js', '.env.json']:
            self.assertFalse(d.public_path(path))
        self.assertTrue(d.public_path('.htaccess'))
        with self.assertRaises(RuntimeError):
            d.verify_host_key(type('Key', (), {'asbytes': lambda self: b'wrong key'})())

    def test_no_change_writes_nothing(self):
        result = d.publish(self.sftp, self.local, [], self.state, 'a' * 40)
        self.assertIn('Aucun', result)
        self.assertEqual(self.sftp.puts, [])


if __name__ == '__main__':
    unittest.main(verbosity=2)
