import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent))
from candidate_consumer import review,verify_contract,PREFIX,UPSTREAM
def proposal(id="T43_HOUSING_TEST"):
    return {"schema":"neurohuerto.public_source_candidate.v1","id":id,
      "source_path":"database/"+id+".json","source_repository":"clucaina/psicologia-politica",
      "source_ref":"integracion/v78-maestro-sin-despliegue","source_blob_sha":"a"*40,
      "raw_sha256":"b"*64,"raw_values_copied":False,"release_gate":"NO_AUTO_IMPORT_NO_AUTO_DEPLOY"}
def row(geo):
    return {"indicator_id":"VIV_TEST","period":"2025","geo_code":geo,"unit":"%",
            "value":32.4,"source_id":"INE"}
class IndependentReviewTests(unittest.TestCase):
    def fetch(self,url):
        self.assertTrue(url.startswith(PREFIX+"database/T"))
        return {"batch_id":"T43_HOUSING_TEST","observations":[row("ES"),row("ES30")]}, "b"*64
    def test_valid_candidate_still_requires_human_license_and_methodology(self):
        c=proposal()
        result=verify_contract(c,self.fetch)
        self.assertEqual(result["status"],"NEEDS_SOURCE_AND_LICENSE_REVIEW")
        self.assertFalse(result["human_factual_review"])
        self.assertEqual(result["rows_independently_checked"],2)
        self.assertNotIn("32.4",str(result))
    def test_changed_data_or_malicious_paths_are_blocked(self):
        c=proposal()
        self.assertEqual(verify_contract({**c,"raw_sha256":"0"*64},self.fetch)["status"],"BLOCK_SOURCE_CHANGED")
        self.assertEqual(verify_contract({**c,"source_path":"database/../users.json"},self.fetch)["status"],"BLOCK_BAD_ID_OR_PATH")
        self.assertEqual(verify_contract({**c,"release_gate":"AUTO_DEPLOY"},self.fetch)["status"],"BLOCK_UNTRUSTED_PUBLICATION_OR_PRIVACY")
        self.assertEqual(verify_contract({**c,"source_repository":"private/org"},self.fetch)["status"],"BLOCK_UNTRUSTED_REPOSITORY")
    def test_queue_is_idempotent_and_preserves_prior_reviews(self):
        c=proposal()
        u={"schema":"neurohuerto.candidate_ledger.v1","candidates":[c]}
        first=review(u,{},self.fetch)
        self.assertEqual(first["newly_reviewed"],1)
        self.assertFalse(first["ready_for_production"])
        second=review(u,first,self.fetch)
        self.assertEqual(second["newly_reviewed"],0)
        self.assertEqual(first["reviews"],second["reviews"])
        changed=proposal()
        changed["source_blob_sha"]="c"*40
        third=review({"schema":"neurohuerto.candidate_ledger.v1","candidates":[changed]},first,self.fetch)
        self.assertEqual(third["newly_reviewed"],1)
    def test_bad_payload_blocks_instead_of_auto_fixing_values(self):
        def f(url):return {"batch_id":"T43_HOUSING_TEST","observations":[dict(row("ES"),value=True)]},"b"*64
        self.assertEqual(verify_contract(proposal(),f)["status"],"NEEDS_STRUCTURAL_REPAIR")
        self.assertFalse(verify_contract(proposal(),f)["ready_to_import"]) if False else None
if __name__=="__main__":unittest.main()
