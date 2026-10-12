#!/usr/bin/env python3
"""Actualiza el archivo de votaciones de Psicología Política.
Fuentes CC BY 4.0: Congreso Abierto, con datos del Congreso de los Diputados.
No importa encuestas, perfiles de visitantes ni inferencias ideológicas.
"""
from __future__ import annotations
import argparse
import csv
import hashlib
import io
import json
import os
from pathlib import Path
import tempfile
from urllib.request import Request, urlopen

BASE = "https://congresoabierto.org/datos/csv/"
URL_VOTOS = BASE + "votaciones.csv"
URL_GRUPOS = BASE + "votos-grupo.csv"
ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "archivo-xv-votos.json"
FIELDS_V = ("id", "fecha", "tipo", "titulo", "temas", "resultado", "si", "no",
            "abstencion", "no_vota", "url_votacion", "url_expediente")
FIELDS_G = ("votacion_id", "grupo", "si", "no", "abstencion", "no_vota", "voto_mayoritario")
MAYORIAS = {"Sí", "No", "Abstención", "Empate", "No vota"}
def read_csv_bytes(data: bytes, required: tuple[str, ...]) -> list[dict]:
    decoded = data.decode("utf-8-sig")
    parser = csv.DictReader(io.StringIO(decoded, newline=""))
    if not parser.fieldnames or not set(required).issubset(parser.fieldnames):
        raise ValueError(f"Cabeceras no coinciden: {parser.fieldnames}")
    rows = list(parser)
    if any(None in r or not r[required[0]] for r in rows):
        raise ValueError("Fila CSV truncada o sin identificador")
    return rows
def number(text: str) -> int:
    if text is None or not text.isdecimal():
        raise ValueError("Recuento inválido en CSV oficial reutilizado: " + repr(text))
    return int(text)
def build(votes: list[dict], group_votes: list[dict], source_hashes: dict) -> dict:
    index = {}
    for row in votes:
        vid = row["id"]
        if vid in index:
            raise ValueError(f"Identificador duplicado: {vid}")
        if len(row["fecha"]) != 10 or row["fecha"][4] != "-":
            raise ValueError("Fecha malformada: " + vid)
        index[vid] = dict(
            id=vid, fecha=row["fecha"], tipo=row["tipo"], titulo=row["titulo"],
            temas=row["temas"], resultado=row["resultado"],
            si=number(row["si"]), no=number(row["no"]),
            abstencion=number(row["abstencion"]), no_vota=number(row["no_vota"]),
            url_votacion=row["url_votacion"], url_expediente=row["url_expediente"],
            grupos={}
        )
    linked = 0
    for row in group_votes:
        vid, grupo = row["votacion_id"], row["grupo"]
        if vid not in index:
            raise ValueError(f"Voto por grupo huérfano: {vid}")
        if not grupo or grupo in index[vid]["grupos"]:
            raise ValueError(f"Grupo duplicado o sin nombre: {vid}/{grupo}")
        if row["voto_mayoritario"] not in MAYORIAS:
            raise ValueError("Voto mayoritario desconocido: " + row["voto_mayoritario"])
        index[vid]["grupos"][grupo] = dict(
            si=number(row["si"]), no=number(row["no"]),
            abstencion=number(row["abstencion"]), no_vota=number(row["no_vota"]),
            mayoria=row["voto_mayoritario"]
        )
        linked += 1
    return {
        "schema": "pp-congreso-xv-grupos/1",
        "snapshot": max(row["fecha"] for row in votes),
        "description": "Archivo histórico de voto mayoritario por grupo parlamentario; no representa programas electorales ni posiciones de los partidos integrantes.",
        "license": "CC BY 4.0",
        "attribution": "Congreso Abierto (congresoabierto.org), a partir de datos oficiales del Congreso de los Diputados.",
        "source_urls": [URL_VOTOS, URL_GRUPOS],
        "source_sha256": source_hashes,
        "source_counts": {"votaciones": len(votes), "votos_grupo": linked},
        "records": sorted(index.values(), key=lambda v: (v["fecha"], v["id"]), reverse=True),
    }
def download(url: str) -> bytes:
    req = Request(url, headers={"User-Agent": "NeuroLab-PsicologiaPolitica/1.0 open-data-audit"})
    with urlopen(req, timeout=45) as resp:
        if resp.status != 200:
            raise RuntimeError("Descarga no disponible: " + url)
        data = resp.read(15_000_001)
        if len(data) > 15_000_000:
            raise ValueError("Archivo remoto demasiado grande; revisar esquema")
        return data
def main():
    p = argparse.ArgumentParser()
    p.add_argument("--dry-run", action="store_true", help="Verifica, no modifica el archivo")
    p.add_argument("--votes-csv", type=Path, help="CSV local de votaciones, para pruebas reproducibles")
    p.add_argument("--groups-csv", type=Path, help="CSV local de voto por grupo")
    p.add_argument("--output", type=Path, default=OUT)
    p.add_argument("--min-votes", type=int, default=2000)
    p.add_argument("--allow-reduction", action="store_true")
    args = p.parse_args()
    a = args.votes_csv.read_bytes() if args.votes_csv else download(URL_VOTOS)
    b = args.groups_csv.read_bytes() if args.groups_csv else download(URL_GRUPOS)
    hashes={"votaciones_csv":hashlib.sha256(a).hexdigest(),"votos_grupo_csv":hashlib.sha256(b).hexdigest()}
    data=build(read_csv_bytes(a,FIELDS_V), read_csv_bytes(b,FIELDS_G), hashes)
    if len(data["records"]) < args.min_votes:
        raise ValueError("Regresión de cobertura: menos de " + str(args.min_votes))
    if args.output.exists() and not args.allow_reduction:
        previous=json.loads(args.output.read_text(encoding="utf-8"))
        if len(data["records"]) < len(previous["records"]):
            raise ValueError("Descenso de cobertura sin aprobación")
    print(json.dumps({"counts":data["source_counts"],"fecha_ultima":data["snapshot"],
        "sha256":hashes,"dry_run":args.dry_run},ensure_ascii=False))
    if args.dry_run:
        return
    args.output.parent.mkdir(parents=True,exist_ok=True)
    with tempfile.NamedTemporaryFile(mode="w",encoding="utf-8",dir=args.output.parent,delete=False) as temp:
        json.dump(data,temp,ensure_ascii=False,separators=(",",":"))
        temp_path=temp.name
    os.replace(temp_path,args.output)
if __name__=="__main__":
    main()
