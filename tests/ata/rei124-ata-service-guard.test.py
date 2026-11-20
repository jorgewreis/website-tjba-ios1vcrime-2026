from pathlib import Path
import os, subprocess
script=Path(__file__).with_name('rei124-ata-service-mysql.test.php')
base=os.environ.copy()
for key in list(base):
 if key.startswith('REI124ATA_'): base.pop(key)
cases=[{}, {'REI124ATA_TEST_DISPOSABLE':'1','REI124ATA_MYSQL_DSN':'mysql:host=external;dbname=prod','REI124ATA_TEST_MARKER':'a'*64}, {'REI124ATA_TEST_DISPOSABLE':'1','REI124ATA_MYSQL_DSN':'mysql:host=127.0.0.1;dbname=rei124ata_test;charset=utf8mb4','REI124ATA_TEST_MARKER':'invalid'}]
for case in cases:
 env=base.copy();env.update(case)
 result=subprocess.run(['php',str(script)],env=env,capture_output=True,text=True,timeout=5)
 assert result.returncode!=0 and 'PASS ata service' not in result.stdout
 assert 'PDOException' not in result.stderr, 'guard deveria recusar antes de conectar'
http = Path(__file__).with_name('rei124-ata-http-mysql.test.php')
for case in ({}, {'REI124ATA_TEST_DISPOSABLE':'1','DB_HOST':'external','DB_NAME':'rei124ata_test','REI124ATA_TEST_MARKER':'a'*64}, {'REI124ATA_TEST_DISPOSABLE':'1','DB_HOST':'127.0.0.1','DB_NAME':'rei124ata_test','REI124ATA_TEST_MARKER':'invalid'}):
 env=base.copy();env.update(case)
 result=subprocess.run(['php',str(http),'setup'],env=env,capture_output=True,text=True,timeout=5)
 assert result.returncode!=0 and 'PDOException' not in result.stderr
print('PASS guard novo: recusa sem runner, host externo e marcador invalido antes de conectar')
