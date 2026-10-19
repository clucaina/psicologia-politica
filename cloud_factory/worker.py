#!/usr/bin/env python3
"""Independent cloud worker: read-only source inventory, structural checks, previews."""
import argparse
import datetime as dt
import html
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys

IGNORE={".git","node_modules",".next",".wrangler","dist","build","vendor","__pycache__",".venv"}
CODE={".php",".js",".jsx",".ts",".tsx",".py",".html",".css",".mjs"}
MEDIA={".png",".jpg",".jpeg",".webp",".svg",".glb",".blend"}
REQUIRED={
  "neurocity":["demos/npl-world/index.html","demos/npl-world/open_city_pipeline/build_blender.py","tools/factory/city_quality.py"],
  "politica":["index.php","VERSION.json","tests"],
  "neuropsicolucia":["package.json","app","content","public","tests"]
}
SHIFT=("Fuentes y continuidad","Riesgos y regresiones","Accesibilidad","Integración y producto")
NEXT={
  "neurocity":["Contrastar fachada y elevación con fuentes medibles","Probar cámara y FPS reales","Integrar NPL y Conflictómetro como capas trazables"],
  "politica":["Resolver bloqueos V78 antes de cualquier despliegue","Validar fuentes editoriales con fechas","Exponer Conflictómetro como componente verificable de NeuroCity"],
  "neuropsicolucia":["Comparar producción con la web antes de cambios","Validar compilación existente sin publicar","Preservar recursos, navegación y contenidos"]
}
def audit(project, root, run_number):
    paths=[]
    for base,dirs,files in os.walk(root):
        dirs[:]=[x for x in dirs if x not in IGNORE]
        for f in files:
            p=Path(base)/f
            if p.is_symlink() or not p.is_file(): continue
            paths.append((p.relative_to(root).as_posix(),p))
    names={n for n,p in paths}
    missing=[x for x in REQUIRED[project] if x not in names and not (root/x).is_dir()]
    exposed=sum(1 for n,p in paths if p.name in (".env",".env.local","id_rsa","credentials.json"))
    code=[(n,p) for n,p in paths if p.suffix.lower() in CODE]
    media=[n for n,p in paths if p.suffix.lower() in MEDIA]
    php=[(n,p) for n,p in code if p.suffix.lower()==".php"]
    failed=[]
    if php and shutil.which("php"):
        for n,p in php[:250]:
            try:
                r=subprocess.run(["php","-l",str(p)],capture_output=True,timeout=7)
                if r.returncode: failed.append(n)
            except (OSError,subprocess.TimeoutExpired): failed.append(n)
    version={}
    if project=="politica" and (root/"VERSION.json").is_file():
        try:
            j=json.loads((root/"VERSION.json").read_text(encoding="utf-8"))
            version={k:j[k] for k in ("version","status","release_gate","hostinger_deployment") if k in j}
        except (ValueError,UnicodeError): version={"error":"VERSION.json not parseable"}
    html_files=[(n,p) for n,p in code if p.suffix.lower()==".html" and p.stat().st_size<1500000]
    img_without_alt=0; without_lang=0
    for n,p in html_files:
        try: t=p.read_text(encoding="utf-8",errors="replace")
        except OSError: continue
        img_without_alt+=sum(not re.search(r"\balt\s*=",tag,re.I) for tag in re.findall(r"<img\b[^>]*>",t,re.I|re.S))
        without_lang+=bool(re.search(r"<html\b",t,re.I) and not re.search(r"<html\b[^>]*\blang\s*=",t,re.I|re.S))
    warnings=[]
    if missing: warnings.append("Rutas esperadas ausentes: inventario, NO regresión demostrada")
    if exposed: warnings.append("Nombres de archivos sensibles presentes; su contenido NO se ha leído")
    if failed: warnings.append("Sintaxis PHP fallida: no desplegar")
    if version.get("release_gate")=="NO-GO": warnings.append("La fuente declara NO-GO: publicación bloqueada")
    warnings.extend({
      "neurocity":["Navegación real, FPS, render PBR e integración NPL/Conflictómetro no certificados"],
      "politica":["Sin verificación de hosting ni pruebas funcionales de editor/mapa"],
      "neuropsicolucia":["Sin ejecutar compilación, navegador ni comparar contenido con web publicada"]
    }[project])
    revision=subprocess.run(["git","rev-parse","HEAD"],cwd=root,capture_output=True,text=True)
    sha=revision.stdout.strip() if revision.returncode==0 else "unknown"
    status="REVIEW_REQUIRED" if (missing or exposed or failed or version.get("release_gate")=="NO-GO") else "STATIC_CHECKS_COMPLETED"
    return {
      "project":project,"time_utc":dt.datetime.now(dt.timezone.utc).isoformat(),
      "source_sha":sha,"shift":SHIFT[run_number%len(SHIFT)],"status":status,
      "version":version,"metrics":{"source_files":len(code),"media_files":len(media),
        "html_files_scanned":len(html_files),"images_missing_alt_static":img_without_alt,
        "html_missing_lang_static":without_lang,"php_linted":min(250,len(php)) if shutil.which("php") else 0,
        "php_syntax_failures":len(failed),"sensitive_filenames_count":exposed},
      "missing_expected_paths":missing,"php_failed_paths":failed[:8],
      "warnings":warnings,"next_steps":NEXT[project],
      "NOT_PERFORMED":["AI code generation","browser visual inspection","production deployment","clinical or forensic validation"]
    }
def output(rep, directory):
    directory.mkdir(parents=True,exist_ok=True)
    (directory/"report.json").write_text(json.dumps(rep,ensure_ascii=False,indent=2),encoding="utf-8")
    m=["# Fábrica independiente: "+rep["project"],"",
       "Turno: "+rep["shift"]+" | Estado: "+rep["status"],
       "Fuente verificada: "+rep["source_sha"][:12],"",
       "## Métricas verificadas"]
    m.extend("- "+k.replace("_"," ")+": "+str(v) for k,v in rep["metrics"].items())
    m+=["","## Advertencias"]
    m.extend("- "+x for x in rep["warnings"])
    m+=["","## Siguiente trabajo"]
    m.extend("- "+x for x in rep["next_steps"])
    m+=["","**Sin** publicación, mejora de código por IA ni pruebas visuales certificadas."]
    (directory/"report.md").write_text("\n".join(m)+"\n",encoding="utf-8")
    esc=html.escape
    metrics="".join("<div class='metric'><strong>"+esc(str(v))+"</strong><small>"+esc(k.replace("_"," "))+"</small></div>" for k,v in rep["metrics"].items())
    bad="".join("<li>"+esc(x)+"</li>" for x in rep["warnings"])
    todo="".join("<li>"+esc(x)+"</li>" for x in rep["next_steps"])
    page="""<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>body{background:#13151d;color:#f2eeec;font:16px system-ui;margin:0;padding:32px}main{max-width:960px;margin:auto}
header{background:linear-gradient(120deg,#55283e,#243644);padding:35px;border-radius:20px}h1{font-size:clamp(28px,4vw,46px)}
small{color:#bebac0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:12px;margin:25px 0}
.metric,.panel{background:#222832;border:1px solid #3c4652;padding:20px;border-radius:14px}.metric strong{font-size:28px;display:block}
.columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
li{margin-bottom:12px;line-height:1.5}footer{margin-top:25px;color:#b2adb5}</style><main><header><small>FÁBRICA LUCÍA · TURNO AUTOMÁTICO</small><h1>"""+esc(rep["project"])+"""</h1><p>"""+esc(rep["shift"])+""" · """+esc(rep["status"])+"""</p><small>"""+esc(rep["source_sha"][:12])+"""</small></header><section class="grid">"""+metrics+"""</section><section class="columns">
<div class="panel"><h2>Bloqueos y límites</h2><ul>"""+bad+"""</ul></div>
<div class="panel"><h2>Siguientes trabajos</h2><ol>"""+todo+"""</ol></div></section>
<footer>Informe estructural. No certifica interfaz, rendimiento, veracidad científica ni despliegue.</footer></main></html>"""
    (directory/"preview.html").write_text(page,encoding="utf-8")
def main():
    a=argparse.ArgumentParser()
    a.add_argument("--project",choices=REQUIRED,required=True)
    a.add_argument("--source",type=Path,required=True)
    a.add_argument("--output",type=Path,required=True)
    a.add_argument("--run",type=int,default=0)
    args=a.parse_args()
    rep=audit(args.project,args.source,args.run)
    output(rep,args.output)
    print((args.output/"report.md").read_text(encoding="utf-8"))
if __name__=="__main__":main()
