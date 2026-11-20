from pathlib import Path
import os, subprocess, sys, tempfile, unittest
SCRIPT=Path(__file__).with_name('rei124-check-composer-inputs.py')
class InputsTest(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory(prefix='rei124-composer-');self.root=Path(self.temp.name);(self.root/'composer.json').write_text('{}');(self.root/'composer.lock').write_text('{}')
 def tearDown(self):self.temp.cleanup()
 def invoke(self,cwd=None):
  path=cwd or self.root;env=os.environ.copy();env['PWD']=str(path);return subprocess.run([sys.executable,'-B',str(SCRIPT)],cwd=path,env=env,capture_output=True,text=True,timeout=5)
 def symlink(self,target,link,is_dir=False):
  try:os.symlink(target,link,target_is_directory=is_dir)
  except (OSError,NotImplementedError):self.skipTest('links indisponiveis no host')
 def test_clean_workspace(self):
  r=self.invoke();self.assertEqual(r.returncode,0);self.assertTrue((self.root/'vendor').is_dir());self.assertNotEqual(self.invoke().returncode,0)
 def test_existing_vendor_untouched(self):
  v=self.root/'vendor';v.mkdir();sentinel=v/'keep';sentinel.write_text('preserve');self.assertNotEqual(self.invoke().returncode,0);self.assertEqual(sentinel.read_text(),'preserve')
 def test_manifest_symlink_rejected(self):
  target=self.root/'source';target.write_text('{}');(self.root/'composer.lock').unlink();self.symlink(target,self.root/'composer.lock');self.assertNotEqual(self.invoke().returncode,0);self.assertFalse((self.root/'vendor').exists())
 def test_vendor_symlink_rejected(self):
  target=self.root/'other';target.mkdir();self.symlink(target,self.root/'vendor',True);self.assertNotEqual(self.invoke().returncode,0);self.assertEqual(list(target.iterdir()),[])
 def test_workspace_symlink_rejected(self):
  alias=self.root.parent/(self.root.name+'-alias');self.symlink(self.root,alias,True)
  try:self.assertNotEqual(self.invoke(alias).returncode,0)
  finally:alias.unlink()
if __name__=='__main__':unittest.main()
