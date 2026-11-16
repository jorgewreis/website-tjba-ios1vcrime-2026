#!/usr/bin/env python3
"""Abre CSV sintético no LibreOffice isolado e inspeciona células XLSX."""
from pathlib import Path
import subprocess
import tempfile
import zipfile
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[2]
soffice = Path('C:/Program Files/LibreOffice/program/soffice.exe')
if not soffice.is_file():
    raise SystemExit('SKIP: LibreOffice indisponível')
with tempfile.TemporaryDirectory(prefix='csv-lo-') as tmp:
    directory = Path(tmp)
    php = "require 'app/helpers/ListExportHelper.php'; ListExportHelper::streamCsv('t','=TITULO','@FILTRO', [['key'=>'x','label'=>'+LABEL'],['key'=>'n','label'=>'N']], [['x'=>'=1+1','n'=>-42],['x'=>' -5','n'=>-1.5]]);"
    data = subprocess.run(['php', '-r', php], cwd=root, capture_output=True, check=True).stdout
    (directory / 'input.csv').write_bytes(data)
    profile = (directory / 'profile').as_uri()
    proc = subprocess.run([str(soffice), '-env:UserInstallation=' + profile, '--headless',
        '--infilter=Text - txt - csv (StarCalc):59,34,76,1', '--convert-to', 'xlsx',
        '--outdir', str(directory), str(directory / 'input.csv')],
        capture_output=True, text=True, timeout=45)
    output = directory / 'input.xlsx'
    if proc.returncode != 0 or not output.is_file():
        raise RuntimeError(f'LibreOffice falhou: {proc.stdout} {proc.stderr}')
    with zipfile.ZipFile(output) as book:
        sheet = ET.fromstring(book.read('xl/worksheets/sheet1.xml'))
        ns = {'x': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
        if sheet.findall('.//x:f', ns):
            raise AssertionError('Fórmula foi importada como executável')
        cells = {cell.attrib['r']: cell for cell in sheet.findall('.//x:c', ns)}
        strings_xml = ET.fromstring(book.read('xl/sharedStrings.xml'))
        strings = [''.join(node.itertext()) for node in strings_xml.findall('x:si', ns)]
        for coordinate in ('A5', 'A6', 'A9', 'A10', 'A11'):
            if coordinate not in cells or cells[coordinate].attrib.get('t') not in ('s', 'inlineStr'):
                raise AssertionError(f'{coordinate} não foi importada como texto')
        if cells['B10'].attrib.get('t') not in (None, 'n') or cells['B10'].find('x:v', ns).text != '-42':
            raise AssertionError('Número negativo tipado não foi preservado')
        value = strings[int(cells['A10'].find('x:v', ns).text)]
        if value not in ('=1+1', "'=1+1"):
            raise AssertionError('Texto da célula não foi preservado')
print('PASS importação sintética LibreOffice: células textuais sem fórmula e número negativo')
