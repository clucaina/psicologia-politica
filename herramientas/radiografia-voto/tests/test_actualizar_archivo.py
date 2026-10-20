"""Pruebas locales del importador histórico; ejecutar python3 -m unittest discover -s tests."""
import csv
import importlib.util
import io
from pathlib import Path
import unittest

MODULE = Path(__file__).resolve().parents[1] / "scripts" / "actualizar_archivo.py"
spec = importlib.util.spec_from_file_location("actualizar_archivo", MODULE)
ingest = importlib.util.module_from_spec(spec)
spec.loader.exec_module(ingest)

class ArchivoVotesTest(unittest.TestCase):
    def setUp(self):
        self.vote = {"id":"s1-v001","fecha":"2026-09-30","tipo":"PNL",
                     "titulo":"Prueba de una medida","temas":"vivienda",
                     "resultado":"Rechazada","si":"2","no":"1","abstencion":"1",
                     "no_vota":"0","url_votacion":"https://www.congreso.es/v1",
                     "url_expediente":""}
        self.group = {"votacion_id":"s1-v001","grupo":"SUMAR","si":"2","no":"1",
                      "abstencion":"1","no_vota":"0","voto_mayoritario":"Sí"}

    def test_counts_and_vote_majority(self):
        result=ingest.build([self.vote],[self.group],{})
        self.assertEqual(result["source_counts"],{"votaciones":1,"votos_grupo":1})
        self.assertEqual(result["records"][0]["grupos"]["SUMAR"]["mayoria"],"Sí")

    def test_absent_majority_is_not_abstention(self):
        group=dict(self.group,voto_mayoritario="")
        result=ingest.build([self.vote],[group],{})
        self.assertEqual(result["records"][0]["grupos"]["SUMAR"]["mayoria"],"No consta")

    def test_orphan_group_is_rejected(self):
        group=dict(self.group,votacion_id="unknown")
        with self.assertRaisesRegex(ValueError,"huérfano"):
            ingest.build([self.vote],[group],{})

    def test_duplicate_group_is_rejected(self):
        with self.assertRaisesRegex(ValueError,"duplicado"):
            ingest.build([self.vote],[self.group,self.group],{})

    def test_duplicate_vote_is_rejected(self):
        with self.assertRaisesRegex(ValueError,"duplicado"):
            ingest.build([self.vote,self.vote],[self.group],{})

    def test_csv_quoted_comma(self):
        rows=ingest.read_csv_bytes(
            b'id,fecha,tipo,titulo,temas,resultado,si,no,abstencion,no_vota,url_votacion,url_expediente\n'
            b's1-v001,2026-09-30,PNL,"Una, dos",vivienda,Rechazada,2,1,1,0,https://www.congreso.es/v1,\n',
            ingest.FIELDS_V)
        self.assertEqual(rows[0]["titulo"],"Una, dos")

    def test_malformed_count(self):
        vote=dict(self.vote,si="-1")
        with self.assertRaisesRegex(ValueError,"inválido"):
            ingest.build([vote],[self.group],{})

if __name__=="__main__":
    unittest.main()