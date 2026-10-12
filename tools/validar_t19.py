#!/usr/bin/env python3
"""Auditoría estructural del lote T19. No publica ni importa registros."""
import argparse
import collections
import hashlib
import json
import math
from datetime import datetime, timezone
from pathlib import Path

REQUIRED=("observation_id","indicator_id","geo_code","geography","period","value","unit","source_id","source_url","source_locator","dataset_code","methodology_flag","batch_id")

def validate(data):
    errors=[]
    if not isinstance(data,dict) or not isinstance(data.get("observations"),list):
        return {"passed":False,"errors":["No hay observations válidas"],"warnings":[],"metrics":{}}
    rows=data["observations"]
    ids=set()
    cells=set()
    indicators=collections.Counter()
    regions=set()
    units=collections.defaultdict(set)
    flags=collections.Counter()
    for position,item in enumerate(rows,1):
        tag=f"Fila {position}"
        if not isinstance(item,dict):
            errors.append(f"{tag}: no es un objeto"); continue
        for field in REQUIRED:
            if item.get(field) is None or str(item[field]).strip()=="":
                errors.append(f"{tag}: falta {field}")
        try:
            if not math.isfinite(float(item.get("value"))):
                raise ValueError()
        except (ValueError,TypeError):
            errors.append(f"{tag}: valor no finito")
        ident=item.get("observation_id")
        if ident in ids:
            errors.append(f"{tag}: observation_id repetido")
        ids.add(ident)
        cell=tuple(str(item.get(k,"")) for k in ("indicator_id","geo_code","period","subgroup"))
        if cell in cells:
            errors.append(f"{tag}: misma celda estadística repetida")
        cells.add(cell)
        indicator=str(item.get("indicator_id",""))
        indicators[indicator]+=1
        regions.add(item.get("geo_code"))
        units[indicator].add(str(item.get("unit","")))
        if "STATUS_" in str(item.get("methodology_flag","")):
            flags[str(item["methodology_flag"])]+=1
        if not str(item.get("source_url","")).startswith("https://"):
            errors.append(f"{tag}: localizador HTTP no seguro")
    for indicator, set_units in units.items():
        if len(set_units)>1:
            errors.append(f"{indicator}: unidades distintas en la misma serie")
    if data.get("batch_id")=="T19":
        if len(rows)!=152 or len(regions)!=19 or len(indicators)!=8:
            errors.append(f"Cobertura T19 incompleta: {len(rows)} registros, {len(regions)} territorios, {len(indicators)} indicadores")
        for indicator,total in indicators.items():
            if total!=19:
                errors.append(f"{indicator}: {total} observaciones en vez de 19")
    before=data.get("checkpoint_before",{}).get("observations")
    after=data.get("checkpoint_after_if_appended",{}).get("observations")
    if isinstance(before,int) and isinstance(after,int) and after-before!=len(rows):
        errors.append("Incoherencia entre incremento declarado y tamaño de lote")
    return {
        "passed":not errors,
        "errors":errors,
        "warnings":[f"{count} observaciones con reserva: {flag}" for flag,count in sorted(flags.items())]+[
            "La integridad estructural NO verifica Eurostat, equivalencia metodológica, ni duplicados entre lotes."
        ],
        "metrics":{"rows":len(rows),"regions":len(regions),"indicators":len(indicators),"flagged":sum(flags.values()),"flags":dict(flags)}
    }

def main():
    parser=argparse.ArgumentParser()
    parser.add_argument("--input",default="database/seeds/T19_CCAA_BASELINE.json")
    parser.add_argument("--output",default="qa_t19_report.json")
    args=parser.parse_args()
    raw=Path(args.input).read_bytes()
    report=validate(json.loads(raw))
    report.update({"sha256":hashlib.sha256(raw).hexdigest(),"checked_at":datetime.now(timezone.utc).isoformat(),"publication_approved":False})
    Path(args.output).write_text(json.dumps(report,ensure_ascii=False,indent=2)+"\n",encoding="utf-8")
    print(f"T19: {report['metrics'].get('rows',0)} filas; {len(report['errors'])} errores; {report['metrics'].get('flagged',0)} reservas")
    for issue in report["errors"]:print("ERROR",issue)
    for issue in report["warnings"]:print("AVISO",issue)
    return 0 if report["passed"] else 1

if __name__=="__main__":
    raise SystemExit(main())
