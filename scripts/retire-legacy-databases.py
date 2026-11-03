"""Retire only historical receipts whose recorded creator belongs to the exact checkout."""
import json
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parent.parent
common = Path(subprocess.check_output(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], text=True).strip()).parent
checkout = Path(sys.argv[1])
for receipt in sorted((common / '.runs/database-runs').glob('*.json')):
    value = json.loads(receipt.read_text())
    if value.get('source') != str(checkout / 'bin/.disposable-database-lifecycle.py'):
        continue
    subprocess.run(['python3', '-B', str(root / 'bin/.disposable-database-lifecycle.py'), 'cleanup', '--receipt', str(receipt)], check=True)
