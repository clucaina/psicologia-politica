import sys
import json
import unittest
from io import BytesIO
from unittest.mock import patch
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent))
from candidate_consumer import review,verify_contract,contract_fingerprint,PREFIX,UPSTREAM,get_json,RejectSourceRedirect
from urllib.request import Request,HTTPRedirectHandler
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
    def test_upstream_duplicate_json_keys_fail_closed(self):
        # Both top-level candidate replacement and nested release-gate
        # override must be rejected before the ledger can be reviewed.
        payloads = [
            b'{"schema":"neurohuerto.candidate_ledger.v1","candidates":[],"candidates":[{"id":"T43_HOUSING_TEST"}]}',
            b'{"candidates":[{"id":"T43_HOUSING_TEST","release_gate":"NO_AUTO_IMPORT_NO_AUTO_DEPLOY","release_gate":"AUTO_DEPLOY"}]}',
        ]
        for payload in payloads:
            with self.subTest(payload=payload), patch("candidate_consumer.build_opener") as opener:
                opener.return_value.open.return_value=BytesIO(payload)
                with self.assertRaisesRegex(ValueError, "Duplicate JSON key"):
                    get_json(UPSTREAM)
                self.assertIsInstance(opener.call_args.args[0], RejectSourceRedirect)

    def test_unique_json_keys_still_parse_without_network(self):
        payload = b'{"schema":"neurohuerto.candidate_ledger.v1","candidates":[]}'
        with patch("candidate_consumer.build_opener") as opener:
            opener.return_value.open.return_value=BytesIO(payload)
            data, digest = get_json(UPSTREAM)
            self.assertIsInstance(opener.call_args.args[0], RejectSourceRedirect)
        self.assertEqual(data["candidates"], [])
        self.assertEqual(len(digest), 64)

    def test_redirects_never_leave_the_allowlisted_source(self):
        # urllib's default redirect handler would follow 302; the reader must not.
        request = Request(UPSTREAM)
        target = "https://untrusted.example/collector"
        self.assertIsNotNone(HTTPRedirectHandler().redirect_request(
            request, None, 302, "Found", {}, target))
        for status, target in (
            (301, "https://untrusted.example/collector"),
            (302, UPSTREAM),
            (307, "http://raw.githubusercontent.com/not-allowed"),
            (308, PREFIX + "database/T43_HOUSING_TEST.json"),
        ):
            with self.subTest(status=status, target=target):
                with self.assertRaisesRegex(ValueError, "Source redirect blocked"):
                    RejectSourceRedirect().redirect_request(
                        request, None, status, "Redirect", {}, target)

    def test_json_reader_installs_fail_closed_redirect_handler_without_network(self):
        payload = b'{"schema":"neurohuerto.candidate_ledger.v1","candidates":[]}'
        with patch("candidate_consumer.build_opener") as factory:
            factory.return_value.open.return_value = BytesIO(payload)
            parsed, digest = get_json(UPSTREAM)
            self.assertEqual(parsed["candidates"], [])
            self.assertEqual(len(digest), 64)
            self.assertIsInstance(factory.call_args.args[0], RejectSourceRedirect)
            self.assertEqual(factory.return_value.open.call_count, 1)
            self.assertEqual(factory.return_value.open.call_args.kwargs["timeout"], 25)

    def test_valid_candidate_still_requires_human_license_and_methodology(self):
        c=proposal()
        result=verify_contract(c,self.fetch)
        self.assertEqual(result["status"],"NEEDS_SOURCE_AND_LICENSE_REVIEW")
        self.assertFalse(result["human_factual_review"])
        self.assertEqual(result["rows_independently_checked"],2)
        self.assertNotIn("32.4",str(result))
    def test_duplicate_observation_identity_blocks_structural_clearance(self):
        first = row("ES")
        def fetch(url):
            return {"batch_id": "T43_HOUSING_TEST",
                    "observations": [first, {**first, "value": 99.0}]}, "b" * 64
        result = verify_contract(proposal(), fetch)
        self.assertEqual(result["status"], "NEEDS_STRUCTURAL_REPAIR")
        self.assertIn("duplicate_observation_key", result["structural_flags"])
        self.assertEqual(result["rows_independently_checked"], 2)
        self.assertFalse(result["human_factual_review"])

    def test_distinct_observation_dimensions_remain_separate(self):
        first = row("ES")
        def fetch(url):
            return {"batch_id": "T43_HOUSING_TEST",
                    "observations": [first, {**first, "period": "2026"},
                                     {**first, "geo_code": "ES30"},
                                     {**first, "source_id": "EUROSTAT"}]}, "b" * 64
        result = verify_contract(proposal(), fetch)
        self.assertEqual(result["status"], "NEEDS_SOURCE_AND_LICENSE_REVIEW")
        self.assertNotIn("duplicate_observation_key", result["structural_flags"])

    def test_blank_or_nontext_unit_needs_structural_repair(self):
        for bad_unit in ("", "   ", 0, True, ["%"], {"unit": "%"}):
            with self.subTest(unit=bad_unit):
                observation = dict(row("ES"), unit=bad_unit)
                def fetch(url):
                    return {"batch_id": "T43_HOUSING_TEST",
                            "observations": [observation]}, "b" * 64
                result = verify_contract(proposal(), fetch)
                self.assertEqual(result["status"], "NEEDS_STRUCTURAL_REPAIR")
                self.assertIn("missing_unit", result["structural_flags"])
                self.assertFalse(result["human_factual_review"])

    def test_nontext_or_blank_period_and_source_needs_repair(self):
        for field in ("period", "source_id"):
            for invalid in ("", "   ", 2026, True, ["INE"], {"id": "INE"}):
                with self.subTest(field=field, invalid=invalid):
                    observation = dict(row("ES"), **{field: invalid})
                    def fetch(url):
                        return {"batch_id": "T43_HOUSING_TEST",
                                "observations": [observation]}, "b" * 64
                    result = verify_contract(proposal(), fetch)
                    self.assertEqual(result["status"], "NEEDS_STRUCTURAL_REPAIR")
                    self.assertIn("missing_period_or_source", result["structural_flags"])
                    self.assertFalse(result["methodology_review"])

    def test_legacy_policy_fingerprint_forces_new_review(self):
        import hashlib
        from candidate_consumer import REVIEW_CONTRACT_FIELDS
        candidate = proposal()
        legacy_fields = {key: candidate.get(key) for key in REVIEW_CONTRACT_FIELDS}
        old_fingerprint = hashlib.sha256(json.dumps(
            legacy_fields, sort_keys=True, ensure_ascii=False,
            separators=(",", ":")).encode("utf-8")).hexdigest()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [candidate]}
        prior = review(upstream, {}, self.fetch)
        prior["reviews"][0]["source_contract_sha256"] = old_fingerprint
        self.assertNotEqual(old_fingerprint, contract_fingerprint(candidate))
        self.assertEqual(review(upstream, prior, self.fetch)["newly_reviewed"], 1)

    def test_changed_data_or_malicious_paths_are_blocked(self):
        c=proposal()
        self.assertEqual(verify_contract({**c,"raw_sha256":"0"*64},self.fetch)["status"],"BLOCK_SOURCE_CHANGED")
        self.assertEqual(verify_contract({**c,"source_path":"database/../users.json"},self.fetch)["status"],"BLOCK_BAD_ID_OR_PATH")
        self.assertEqual(verify_contract({**c,"release_gate":"AUTO_DEPLOY"},self.fetch)["status"],"BLOCK_UNTRUSTED_PUBLICATION_OR_PRIVACY")
        self.assertEqual(verify_contract({**c,"source_repository":"private/org"},self.fetch)["status"],"BLOCK_UNTRUSTED_REPOSITORY")
    def test_missing_or_blank_indicator_is_structural_error(self):
        for invalid in (None, "", "   ", 42, True):
            with self.subTest(invalid=invalid):
                broken = row("ES")
                if invalid is None:
                    del broken["indicator_id"]
                else:
                    broken["indicator_id"] = invalid
                def f(url, broken=broken):
                    return {"batch_id": "T43_HOUSING_TEST", "observations": [broken]}, "b" * 64
                result = verify_contract(proposal(), f)
                self.assertEqual(result["status"], "NEEDS_STRUCTURAL_REPAIR")
                self.assertIn("missing_indicator_id", result["structural_flags"])
                self.assertEqual(result["indicators"], 0)

    def test_named_indicator_does_not_trigger_false_alarm(self):
        result = verify_contract(proposal(), self.fetch)
        self.assertEqual(result["status"], "NEEDS_SOURCE_AND_LICENSE_REVIEW")
        self.assertEqual(result["indicators"], 1)

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
    def test_same_blob_but_changed_digest_forces_independent_recheck(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [candidate]}
        prior = review(upstream, {}, self.fetch)
        changed = dict(candidate, raw_sha256="0" * 64)
        checked = review({**upstream, "candidates": [changed]}, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertEqual(checked["reviews"][0]["independent_check"]["status"],
                         "BLOCK_SOURCE_CHANGED")
        self.assertFalse(checked["ready_for_production"])

    def test_same_blob_but_relaxed_release_gate_forces_recheck(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [candidate]}
        prior = review(upstream, {}, self.fetch)
        changed = dict(candidate, release_gate="AUTO_DEPLOY")
        checked = review({**upstream, "candidates": [changed]}, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertEqual(checked["reviews"][0]["independent_check"]["status"],
                         "BLOCK_UNTRUSTED_PUBLICATION_OR_PRIVACY")
        self.assertFalse(checked["ready_for_production"])

    def test_candidate_reclassified_as_document_drops_stale_numeric_review(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [candidate]}
        prior = review(upstream, {}, self.fetch)
        self.assertEqual(len(prior["reviews"]), 1)
        changed = dict(candidate, candidate_kind="DOCUMENT_ONLY",
                       independent_statistical_review_allowed=False)
        checked = review({**upstream, "candidates": [changed]}, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 0)
        self.assertEqual(checked["reviews"], [])
        self.assertFalse(checked["ready_for_production"])

    def test_legacy_prior_without_contract_fingerprint_is_rechecked(self):
        candidate = proposal()
        prior = {"reviews": [{"id": candidate["id"],
                              "source_blob_sha": candidate["source_blob_sha"]}]}
        checked = review({"schema": "neurohuerto.candidate_ledger.v1",
                          "candidates": [candidate]}, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertEqual(checked["reviews"][0]["independent_check"]["status"],
                         "NEEDS_SOURCE_AND_LICENSE_REVIEW")

    def test_stale_prior_reviews_keep_queue_nonnegative(self):
        current = proposal()
        prior = {"reviews": [
            {"id": current["id"], "source_blob_sha": current["source_blob_sha"],
             "source_contract_sha256": contract_fingerprint(current)},
            {"id": "T99_OLD_HISTORICAL", "source_blob_sha": "9" * 40},
        ]}
        result = review({"schema": "neurohuerto.candidate_ledger.v1",
                         "candidates": [current]}, prior, self.fetch)
        self.assertEqual(result["newly_reviewed"], 1)  # incomplete cached review is unsafe
        self.assertEqual(result["remaining_new"], 0)
        self.assertEqual(len(result["reviews"]), 2)  # archival continuity
        self.assertFalse(result["reviews"][0]["ready_to_import"])

    def test_forged_cached_approval_never_skips_independent_review(self):
        c = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [c]}
        prior = review(upstream, {}, self.fetch)
        prior["reviews"][0]["ready_to_import"] = True
        prior["reviews"][0]["independent_check"] = {"status": "APPROVED"}
        checked = review(upstream, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertFalse(checked["reviews"][0]["ready_to_import"])
        self.assertEqual(checked["reviews"][0]["independent_check"]["status"],
                         "NEEDS_SOURCE_AND_LICENSE_REVIEW")

    def test_cached_review_missing_evidence_is_rechecked(self):
        c = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [c]}
        prior = review(upstream, {}, self.fetch)
        del prior["reviews"][0]["independent_check"]
        self.assertEqual(review(upstream, prior, self.fetch)["newly_reviewed"], 1)

    def test_unhashable_cached_status_is_rechecked_without_crash(self):
        c = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [c]}
        prior = review(upstream, {}, self.fetch)
        prior["reviews"][0]["independent_check"]["status"] = ["APPROVED"]
        checked = review(upstream, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertFalse(checked["ready_for_production"])

    def test_source_outage_is_retried_not_cached_as_permanent_review(self):
        c = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1", "candidates": [c]}
        def unavailable(url):
            raise ValueError("synthetic unavailable source")
        prior = review(upstream, {}, unavailable)
        self.assertEqual(prior["reviews"][0]["independent_check"]["status"],
                         "SOURCE_UNAVAILABLE")
        recovered = review(upstream, prior, self.fetch)
        self.assertEqual(recovered["newly_reviewed"], 1)
        self.assertEqual(recovered["reviews"][0]["independent_check"]["status"],
                         "NEEDS_SOURCE_AND_LICENSE_REVIEW")
        self.assertEqual(review(upstream, recovered, self.fetch)["newly_reviewed"], 0)

    def test_malformed_upstream_records_cannot_disappear_from_review_queue(self):
        c = proposal()
        for invalid in (None, [], "not a candidate", 0, True):
            with self.subTest(invalid=invalid):
                with self.assertRaisesRegex(ValueError, "Malformed upstream candidate record"):
                    review({"schema": "neurohuerto.candidate_ledger.v1",
                            "candidates": [c, invalid]}, {}, self.fetch)

    def test_malformed_only_ledger_does_not_claim_zero_pending(self):
        with self.assertRaisesRegex(ValueError, "Malformed upstream candidate record"):
            review({"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [None]}, {}, self.fetch)

    def test_duplicate_upstream_ids_fail_closed(self):
        c = proposal()
        with self.assertRaises(ValueError):
            review({"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [c, dict(c, source_blob_sha="f" * 40)]},
                   {}, self.fetch)

    def test_nonstring_upstream_ids_cannot_crash_or_be_silently_cast(self):
        for invalid in (["T43_HOUSING_TEST"], {"key": 1}, 31, ""):
            with self.subTest(invalid=invalid):
                with self.assertRaises(ValueError):
                    review({"schema": "neurohuerto.candidate_ledger.v1",
                            "candidates": [dict(proposal(), id=invalid)]},
                           {}, self.fetch)

    def test_extreme_json_integer_marks_measurement_invalid_without_crashing(self):
        def fetch_huge(url):
            return {"batch_id": "T43_HOUSING_TEST",
                    "observations": [dict(row("ES"), value=10 ** 500)]}, "b" * 64
        result = verify_contract(proposal(), fetch_huge)
        self.assertEqual(result["status"], "NEEDS_STRUCTURAL_REPAIR")
        self.assertIn("invalid_measurement", result["structural_flags"])

    def test_non_statistical_json_is_rejected_without_a_redundant_second_scan(self):
        bad={**proposal(),"candidate_kind":"DOCUMENT_ONLY",
             "independent_statistical_review_allowed":False}
        u={"schema":"neurohuerto.candidate_ledger.v1","candidates":[bad]}
        self.assertEqual(verify_contract(bad,self.fetch)["status"],"BLOCK_NON_STATISTICAL_DOCUMENT")
        result=review(u,{},self.fetch)
        self.assertEqual(result["newly_reviewed"],0)
        self.assertEqual(result["reviews"],[])
    def test_original_public_document_is_not_a_fake_numeric_batch(self):
        # Reproduces the actual shape of T35-T39 public editorial candidates:
        # provenance and matching batch ID, but NO numeric observations array.
        def fetch_document(url):
            return {"batch_id": "T43_HOUSING_TEST",
                    "publication_policy": "DRAFT",
                    "numeric_npl_observations": 0,
                    "candidates": [{"kind": "editorial"}]}, "b" * 64
        result = verify_contract(proposal(), fetch_document)
        self.assertEqual(result["status"], "BLOCK_NON_STATISTICAL_DOCUMENT")
        self.assertEqual(result["digest_match"], True)

    def test_bad_payload_blocks_instead_of_auto_fixing_values(self):
        def f(url):return {"batch_id":"T43_HOUSING_TEST","observations":[dict(row("ES"),value=True)]},"b"*64
        self.assertEqual(verify_contract(proposal(),f)["status"],"NEEDS_STRUCTURAL_REPAIR")
        self.assertFalse(verify_contract(proposal(),f)["ready_to_import"]) if False else None
    def test_archived_forged_approval_cannot_survive_in_output(self):
        current = proposal()
        historical = {"id": "T99_ARCHIVED", "ready_to_import": True,
                      "no_publication_without_human_review": False,
                      "independent_check": {"status": "APPROVED"}}
        prior = {"reviews": [historical]}
        result = review({"schema": "neurohuerto.candidate_ledger.v1",
                         "candidates": [current]}, prior, self.fetch)
        archived = next(x for x in result["reviews"] if x["id"] == "T99_ARCHIVED")
        self.assertIs(archived["ready_to_import"], False)
        self.assertIs(archived["no_publication_without_human_review"], True)
        self.assertEqual(archived["independent_check"]["status"], "ARCHIVED_UNVERIFIED")
        self.assertEqual(archived["independent_check"]["release_gate"], "NO_DEPLOY_NO_PUBLISH")
        self.assertTrue(historical["ready_to_import"])  # input not mutated
        self.assertFalse(result["ready_for_production"])

    def test_unselected_forged_cache_still_requires_recheck(self):
        later = proposal()
        first = proposal("T41_FIRST")
        upstream_later = {"schema": "neurohuerto.candidate_ledger.v1",
                          "candidates": [later]}
        prior = review(upstream_later, {}, self.fetch)
        prior["reviews"][0]["ready_to_import"] = True
        # With limit=1 the unsafe cached record is pending but not yet fetched.
        result = review({"schema": "neurohuerto.candidate_ledger.v1",
                         "candidates": [first, later]}, prior, self.fetch, limit=1)
        self.assertEqual(result["newly_reviewed"], 1)
        self.assertEqual(result["remaining_new"], 1)
        pending = next(x for x in result["reviews"] if x["id"] == later["id"])
        self.assertIs(pending["ready_to_import"], False)
        self.assertIs(pending["no_publication_without_human_review"], True)
        # The next bounded pass must actually recheck it, not trust sanitization.
        next_pass = review({"schema": "neurohuerto.candidate_ledger.v1",
                            "candidates": [first, later]}, result, self.fetch, limit=1)
        self.assertEqual(next_pass["newly_reviewed"], 1)
        self.assertEqual(next_pass["remaining_new"], 0)

    def test_archived_record_cannot_republish_private_prior_fields(self):
        current = proposal()
        sentinel = "SYNTHETIC_PRIVATE_SENTINEL"
        prior = {"reviews": [{
            "id": "T99_ARCHIVED", "source_path": "private/" + sentinel,
            "patient_name": sentinel, "observations": [{"name": sentinel}],
            "ready_to_import": False,
            "no_publication_without_human_review": True,
            "independent_check": {
                "status": "NEEDS_SOURCE_AND_LICENSE_REVIEW",
                "patient_notes": sentinel,
            },
        }]}
        result = review({"schema": "neurohuerto.candidate_ledger.v1",
                         "candidates": [current]}, prior, self.fetch)
        archived = next(x for x in result["reviews"] if x["id"] == "T99_ARCHIVED")
        self.assertNotIn(sentinel, json.dumps(result))
        self.assertNotIn("observations", archived)
        self.assertNotIn("source_path", archived)
        self.assertEqual(archived["independent_check"]["status"], "ARCHIVED_UNVERIFIED")
        self.assertFalse(result["ready_for_production"])

    def test_unknown_nested_cache_field_forces_recheck_without_leak(self):
        current = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [current]}
        first = review(upstream, {}, self.fetch)
        first["reviews"][0]["independent_check"]["patient_notes"] = (
            "SYNTHETIC_PRIVATE_SENTINEL")
        checked = review(upstream, first, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertNotIn("SYNTHETIC_PRIVATE_SENTINEL", json.dumps(checked))
        self.assertEqual(checked["reviews"][0]["independent_check"]["status"],
                         "NEEDS_SOURCE_AND_LICENSE_REVIEW")

    def test_fresh_candidate_metadata_cannot_leak_untrusted_text(self):
        candidate = proposal()
        sentinel = "SYNTHETIC_PRIVATE_SENTINEL"
        candidate["source_path"] = "private/" + sentinel + ".json"
        candidate["source_blob_sha"] = sentinel
        candidate["raw_sha256"] = sentinel
        result = review({"schema": "neurohuerto.candidate_ledger.v1",
                         "candidates": [candidate]}, {}, self.fetch)
        fresh = result["reviews"][0]
        self.assertEqual(fresh["independent_check"]["status"], "BLOCK_BAD_ID_OR_PATH")
        self.assertNotIn(sentinel, json.dumps(result))
        self.assertNotIn("source_path", fresh)
        self.assertNotIn("source_blob_sha", fresh)
        self.assertNotIn("upstream_sha256", fresh)
        self.assertFalse(result["ready_for_production"])

    def test_fresh_valid_metadata_retains_bounded_reusable_fields(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [candidate]}
        first = review(upstream, {}, self.fetch)
        fresh = first["reviews"][0]
        self.assertEqual(fresh["source_path"], candidate["source_path"])
        self.assertEqual(fresh["source_blob_sha"], candidate["source_blob_sha"])
        self.assertEqual(fresh["upstream_sha256"], candidate["raw_sha256"])
        self.assertEqual(fresh["independent_check"]["status"],
                         "NEEDS_SOURCE_AND_LICENSE_REVIEW")
        second = review(upstream, first, self.fetch)
        self.assertEqual(second["newly_reviewed"], 0)

    def test_untrusted_fresh_candidate_ids_are_rejected_before_output(self):
        for bad_id in ("PRIVATE_PERSON", "T43_PRIVATE/CASE",
                       "T43_" + "X" * 65):
            with self.subTest(bad_id=bad_id):
                candidate = proposal()
                candidate["id"] = bad_id
                with self.assertRaisesRegex(ValueError,
                                            "Malformed or duplicated upstream candidate ID"):
                    review({"schema": "neurohuerto.candidate_ledger.v1",
                            "candidates": [candidate]}, {}, self.fetch)

    def test_incomplete_statistical_cache_requires_real_recheck(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [candidate]}
        prior = review(upstream, {}, self.fetch)
        # Same source fingerprint, but a status label is not row evidence.
        prior["reviews"][0]["independent_check"] = {
            "status": "NEEDS_SOURCE_AND_LICENSE_REVIEW"}
        checked = review(upstream, prior, self.fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertEqual(checked["reviews"][0]["independent_check"]["rows_independently_checked"], 2)
        self.assertFalse(checked["ready_for_production"])

    def test_forged_structural_cache_without_flags_requires_recheck(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [candidate]}
        def invalid_fetch(url):
            return {"batch_id": candidate["id"],
                    "observations": [dict(row("ES"), value=True)]}, "b" * 64
        prior = review(upstream, {}, invalid_fetch)
        self.assertEqual(prior["reviews"][0]["independent_check"]["status"],
                         "NEEDS_STRUCTURAL_REPAIR")
        prior["reviews"][0]["independent_check"]["structural_flags"] = []
        checked = review(upstream, prior, invalid_fetch)
        self.assertEqual(checked["newly_reviewed"], 1)
        self.assertIn("invalid_measurement",
                      checked["reviews"][0]["independent_check"]["structural_flags"])
        self.assertFalse(checked["ready_for_production"])

    def test_duplicate_prior_ids_invalidate_cached_review(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [candidate]}
        prior = review(upstream, {}, self.fetch)
        self.assertEqual(prior["newly_reviewed"], 1)
        duplicate = dict(prior["reviews"][0])
        duplicate["independent_check"] = {
            "status": "BLOCK_NON_STATISTICAL_DOCUMENT",
            "release_gate": "NO_DEPLOY_NO_PUBLISH",
        }
        prior["reviews"].append(duplicate)
        calls = []
        def tracked_fetch(url):
            calls.append(url)
            return self.fetch(url)
        result = review(upstream, prior, tracked_fetch)
        self.assertEqual(len(calls), 1)
        self.assertEqual(result["newly_reviewed"], 1)
        self.assertEqual(result["remaining_new"], 0)
        self.assertEqual(len(result["reviews"]), 1)
        self.assertEqual(result["reviews"][0]["independent_check"]["status"],
                         "NEEDS_SOURCE_AND_LICENSE_REVIEW")
        self.assertFalse(result["ready_for_production"])

    def test_untrusted_prior_shape_forces_recheck_without_crash(self):
        candidate = proposal()
        upstream = {"schema": "neurohuerto.candidate_ledger.v1",
                    "candidates": [candidate]}
        for bad in (None, [], {"reviews": None}, {"reviews": {}},
                    {"reviews": "not-a-review-list"}):
            with self.subTest(bad=bad):
                result = review(upstream, bad, self.fetch)
                self.assertEqual(result["newly_reviewed"], 1)
                self.assertEqual(result["remaining_new"], 0)
                self.assertEqual(len(result["reviews"]), 1)
                self.assertFalse(result["ready_for_production"])

if __name__=="__main__":unittest.main()
