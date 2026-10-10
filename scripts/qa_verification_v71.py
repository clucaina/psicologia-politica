#!/usr/bin/env python3
"""Local read-only QA orchestrator. Never reaches databases or production."""
from pathlib import Path
import argparse, json, subprocess, time, shutil
ROOT=Path(__file__).resolve().parents[1]
TESTS=[
 ('php','tests/qa_v71_markdown.php'),('php','tests/qa_article_markdown_v56.php'),
 ('php','tests/qa_v71_editorial.php'),('php','tests/qa_v68_text_guard.php'),
 ('php','tests/qa_v66_statistical_contract.php'),('php','tests/qa_v67_temporal_contract.php'),
 ('php','tests/qa_v67_editorial.php'),('php','tests/qa_v67_text_citizenship.php'),
 ('php','tests/qa_v66_ecp_reference_date.php'),('php','tests/qa_v65_text_semantics.php'),
 ('php','tests/qa_v64_ecv_revision.php'),('php','tests/qa_v66_quarterly_stock.php'),
 ('node','tests/qa_v68_series_contract.mjs'),('node','tests/qa_v67_map_contract.mjs'),
 ('node','tests/qa_v67_autosave_race.mjs'),
 ('python3','tests/qa_v71_npl_readiness.py'),('python3','tests/qa_v71_release.py'),
 ('python3','tests/qa_v68_candidate_gate.py'),
]
def run_tests(include_browser:bool=False):
    selected=[*TESTS]
    if include_browser:selected.append(('python3','tests/qa_v71_svg_trust_browser.py'))
    result=[]
    for program,name in selected:
        if not (ROOT/name).is_file():
            result.append({'file':name,'result':'MISSING'});continue
        started=time.monotonic()
        try:
            p=subprocess.run([program,name],cwd=ROOT,timeout=35,text=True,capture_output=True)
            result.append({'file':name,'result':'PASS' if p.returncode==0 else 'FAIL',
                    'elapsed_seconds':round(time.monotonic()-started,3),
                    'diagnostic':(p.stdout+p.stderr)[-700:]})
        except subprocess.TimeoutExpired:
            result.append({'file':name,'result':'TIMEOUT'})
    return result

def lint_files():
    result={};php=list(ROOT.rglob('*.php'));js=list(ROOT.rglob('*.js'))+list(ROOT.rglob('*.mjs'));json_files=list(ROOT.rglob('*.json'))
    for prog,items in (('php',php),('node',js)):
        if not shutil.which(prog):result[prog]={'status':'UNAVAILABLE','count':0,'failed':[]};continue
        fails=[]
        for file in items:
            proc=subprocess.run([prog,'-l' if prog=='php' else '--check',str(file)],capture_output=True,text=True,timeout=8)
            if proc.returncode:fails.append({'file':str(file.relative_to(ROOT)),'error':(proc.stderr+proc.stdout)[-300:]})
        result[prog]={'status':'PASS' if not fails else 'FAIL','count':len(items),'failed':fails[:30]}
    failures=[]
    for file in json_files:
        try:json.loads(file.read_text(encoding='utf-8'))
        except Exception as exc:failures.append({'file':str(file.relative_to(ROOT)),'error':str(exc)})
    result['json']={'status':'PASS' if not failures else 'FAIL','count':len(json_files),'failed':failures[:30]}
    return result

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--browser',action='store_true');parser.add_argument('--output',type=Path);args=parser.parse_args()
    cases=run_tests(args.browser)
    checks=lint_files()
    passed=sum(x['result']=='PASS' for x in cases)
    report={'release':'V71','scope':'LOCAL_PREPRODUCTION_ONLY','tests':cases,
        'selected_test_pass_count':passed,'selected_test_total':len(cases),'lint':checks,
        'production_validation':'NOT_EXECUTED','mariadb_integration':'NOT_EXECUTED',
        'browser_with_real_api':'NOT_EXECUTED','publication_gate':'NO_GO'}
    if args.output:args.output.write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
    print('V71 QA:',passed,'/',len(cases),'selected tests; syntax', {k:v['count'] for k,v in checks.items()})
    if any(x['result']!='PASS' for x in cases) or any(x['status']!='PASS' for x in checks.values()):
        print('FAILED:',[(x['file'],x['result']) for x in cases if x['result']!='PASS']);return 2
    return 0
if __name__=='__main__':raise SystemExit(main())
