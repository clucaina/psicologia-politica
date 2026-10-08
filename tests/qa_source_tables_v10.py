"""Independent numeric cross-check against two ministry tables; not editorial sign-off.
T30: indexed text of official 2025 PDF (visual PDF not accessible in this run).
T31: official 2025 table, PDF page visually inspected 2026-10-08.
No value is imputed and neither dataset is published by this script.
"""
from pathlib import Path
import json
R=Path(__file__).resolve().parent.parent
load=lambda p:json.loads((R/p).read_text(encoding='utf-8'))
# Ordered 2022, 2023, 2024, 2025. Independent transcription of published ministry table.
edu={
'ES':(13.9,13.7,13.0,12.8),'ES61':(15.5,16.9,15.5,14.5),'ES24':(12.5,10.8,11.9,12.3),
'ES12':(11.9,10.5,10.5,10.6),'ES53':(17.3,18.0,20.1,15.2),'ES70':(12.3,14.7,13.1,15.9),
'ES13':(9.0,7.3,5.5,8.9),'ES41':(10.7,10.3,10.8,10.2),'ES42':(16.2,16.6,14.6,15.7),
'ES51':(17.5,14.8,13.7,13.5),'ES52':(16.0,15.0,12.9,14.4),'ES43':(10.6,9.9,13.1,15.0),
'ES11':(9.9,9.1,10.8,10.4),'ES30':(11.2,11.4,10.5,9.6),'ES62':(18.4,19.2,18.2,20.6),
'ES22':(5.2,6.5,9.9,7.8),'ES21':(6.1,6.7,5.0,3.6),'ES23':(11.8,9.7,17.0,15.5),
'ES63':(19.5,21.2,14.6,11.65),'ES64':(15.0,20.4,26.0,14.2)}
# Ordered employment 2015/2020/2025, unemployment 2015/2020/2025.
labor={
'ES':(64.6,68.7,75.3,20.3,14.1,9.4),'ES61':(55.3,60.2,68.9,29.6,20.6,14.1),
'ES24':(70.3,73.8,78.5,14.8,10.6,6.8),'ES12':(60.5,65.2,74.5,18.2,13.4,7.7),
'ES53':(71.4,69.0,77.5,15.5,14.5,8.1),'ES70':(58.0,60.0,70.4,27.5,20.9,12.4),
'ES13':(65.3,69.7,76.5,16.7,10.9,6.6),'ES41':(66.7,71.2,77.3,16.7,10.9,7.6),
'ES42':(60.9,66.3,73.0,23.9,16.3,11.6),'ES51':(70.0,73.9,78.6,16.9,10.9,7.4),
'ES52':(63.7,67.8,73.9,20.9,14.9,10.1),'ES43':(56.2,61.4,70.1,27.0,20.2,13.2),
'ES11':(64.0,69.7,75.5,18.2,11.2,7.6),'ES30':(72.5,75.2,81.0,15.3,11.3,7.1),
'ES62':(60.9,67.0,72.0,22.3,14.5,10.9),'ES22':(71.8,73.9,79.2,12.5,9.1,6.7),
'ES21':(69.7,74.2,78.2,13.6,8.4,6.6),'ES23':(71.5,74.8,80.0,13.9,9.9,6.7)}
actual=load('database/T30_ABANDONO_EDUCATIVO_2022_2025.json')['observations']
expected={(geo,str(year)):value for geo,vals in edu.items() for year,value in zip((2022,2023,2024,2025),vals)}
assert len(expected)==len(actual)==80
for o in actual:
 key=(o['geo_code'],o['period']);assert key in expected and o['value']==expected[key],('T30',key,o['value'],expected.get(key))
actual=load('database/T31_EMPLEO_PARO_25_64_2015_2025.json')['observations']
expected={}
for geo,vals in labor.items():
 for indicator,offset in [('EPA_TASA_EMPLEO_25_64_PCT',0),('EPA_TASA_PARO_25_64_PCT',3)]:
  for year,value in zip((2015,2020,2025),vals[offset:offset+3]):expected[(indicator,geo,str(year))]=value
assert len(expected)==len(actual)==108
for o in actual:
 key=(o['indicator_id'],o['geo_code'],o['period']);assert key in expected and o['value']==expected[key],('T31',key,o['value'],expected.get(key))
print('V10 PRIMARY-TABLE CROSSCHECK PASS: 80 T30 values matched indexed PDF table, 108 T31 values matched visually inspected PDF table. Editorial verification remains required.')
