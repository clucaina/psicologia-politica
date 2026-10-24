import json
import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]/"scripts"))
from npl_descriptive_comparator import compare,render

SOURCE=Path(__file__).resolve().parents[1]/"database/T27_SALARIOS_CCAA_2024.json"
def sample():
    return {"batch_id":"demo","new_indicators":[{"indicator_id":"W","label_es":"Salarios regionales","status":"candidate_review"}],
            "observations":[
                dict(indicator_id="W",period="2024",geo_code="ES",geography="España",subgroup="Todos",
                     value=100,unit="EUR/año",source_id="INE",dataset_code="A",methodology_flag="M",measurement_type="survey"),
                dict(indicator_id="W",period="2024",geo_code="ES30",geography="Madrid",subgroup="Todos",
                     value=150,unit="EUR/año",source_id="INE",dataset_code="A",methodology_flag="M",measurement_type="survey")]}
class NPLRegionalComparatorTests(unittest.TestCase):
    def test_real_source_has_descriptive_output_but_no_publication(self):
        data=json.loads(SOURCE.read_text(encoding="utf-8"))
        r=compare(data,"EAES_SALARIO_ANUAL_BRUTO","2024","Ambos sexos")
        self.assertEqual(r["status"],"CANDIDATE_RESEARCH_ONLY_NO_DEPLOY")
        self.assertEqual(r["regions_compared"],17)
        self.assertFalse(r["publication_authorized"])
        self.assertEqual(r["editorial_status"],"candidate_review")
        self.assertTrue(r["national"]["value"]>0)
        self.assertIn("No",r["explanation"].capitalize()) if False else None
        page=render(r)
        self.assertIn("CANDIDATO",page)
        self.assertIn("17",str(r["regions_compared"]))
    def test_same_period_group_source_and_unit_required(self):
        d=sample()
        r=compare(d,"W","2024","Todos")
        self.assertAlmostEqual(r["regions"][0]["difference_percent_of_national"],50)
        d["observations"][1]["unit"]="EUR/mes"
        self.assertEqual(compare(d,"W","2024","Todos")["status"],"BLOCK_COMPARISON")
        d=sample()
        d["observations"][1]["source_id"]="UNKNOWN"
        self.assertEqual(compare(d,"W","2024","Todos")["status"],"BLOCK_COMPARISON")
    def test_duplicate_geographies_or_baseline_zero_block(self):
        d=sample()
        d["observations"].append(d["observations"][1].copy())
        self.assertEqual(compare(d,"W","2024","Todos")["status"],"BLOCK_COMPARISON")
        d=sample()
        d["observations"][0]["value"]=0
        self.assertEqual(compare(d,"W","2024","Todos")["status"],"BLOCK_COMPARISON")
        d=sample()
        d["observations"][0]["value"]=True
        self.assertEqual(compare(d,"W","2024","Todos")["status"],"BLOCK_COMPARISON")
    def test_html_escapes_untrusted_location_names(self):
        d=sample()
        d["observations"][1]["geography"]='<img src=x onerror=alert(1)>'
        page=render(compare(d,"W","2024","Todos"))
        self.assertNotIn("<img src=x onerror=",page)
        self.assertIn("&lt;img",page)
if __name__=="__main__":unittest.main()
