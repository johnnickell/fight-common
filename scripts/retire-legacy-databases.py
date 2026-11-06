"""Retire only historical receipts whose recorded creator belongs to the exact checkout."""
import json
from pathlib import Path
import subprocess
import sys

tooling_checkout = Path(__file__).resolve().parent.parent
canonical_checkout = Path(subprocess.check_output(['git', 'rev-parse', '--path-format=absolute', '--git-common-dir'], text=True).strip()).parent
checkout = Path(sys.argv[1])
for receipt_path in sorted((canonical_checkout / '.runs/database-runs').glob('*.json')):
    receipt_data = json.loads(receipt_path.read_text())
    if receipt_data.get('source') != str(checkout / 'bin/.disposable-database-lifecycle.py'):
        continue
    subprocess.run(['python3', '-B', str(tooling_checkout / 'bin/.disposable-database-lifecycle.py'), 'cleanup', '--receipt', str(receipt_path)], check=True)
