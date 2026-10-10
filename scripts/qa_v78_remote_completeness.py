#!/usr/bin/env python3
"""Remote V78 integration gate. FAIL CLOSED until master files are actually synchronized."""
import json,hashlib,sys
from pathlib import Path
root=Path(__file__).resolve().parents[1]
errors=[]
def err(msg): errors.append(msg)
v=root/"VERSION.json"
if not v.exists(): err("VERSION.json missing")
else:
    try:
        version=json.loads(v.read_text(encoding="utf-8"))
        if version.get("version")!="V78":err("wrong version")
        if version.get("release_gate")!="NO-GO":err("release_gate is not NO-GO")
    except Exception as e:err("invalid VERSION.json: "+str(e))
manifest=root/"docs/RELEASE_PAYLOAD_V78.json"
if not manifest.exists():err("V78 SHA256 staging manifest missing; partial sync")
else:
    try:
        m=json.loads(manifest.read_text(encoding="utf-8"))
        if m.get("version")!="V78":err("wrong manifest version")
        if m.get("publication_gate")!="NO_GO":err("must keep NO_GO")
        entries=m.get("files",[])
        if len(entries)!=133:err(f"expected 133 files, got {len(entries)}")
        for entry in entries:
            rel=entry.get("path","")
            target=(root/rel).resolve()
            if not str(target).startswith(str(root.resolve())+"/"):err("path escape: "+rel);continue
            if not target.is_file():err("missing "+rel);continue
            digest=hashlib.sha256(target.read_bytes()).hexdigest()
            if digest!=entry.get("sha256"):err("hash mismatch: "+rel)
    except Exception as e:err("invalid manifest: "+str(e))
required=["_private/pages.php","_private/observatorio-espana.html","_private/assets.json","assets/observatorio/mapa-espana.svg","assets/observatorio/mapa-espana.js","tests/qa_v78_release.py","tests/qa_v78_author_load.mjs","tests/qa_v78_public_urls.php"]
for rel in required:
    if not (root/rel).is_file():err("missing "+rel)
print(json.dumps({"gate":"NO_GO" if errors else "PREPRODUCTION_SYNC_VALIDATED_NOT_DEPLOYABLE","errors_count":len(errors),"errors":errors[:25]},ensure_ascii=False,indent=2))
sys.exit(1 if errors else 0)
