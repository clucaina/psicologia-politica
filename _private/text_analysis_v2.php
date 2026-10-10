<?php
declare(strict_types=1);
/** Heuristic, non-factual, non-diagnostic text analysis. No content is persisted. */
function pp_text_propositions(string $text): array {
    // Protect honorifics before names: 'Dr. García' is not two propositions.
    // The private-use placeholder is restored before any text is returned.
    $dot="\u{E000}";
    $safe=preg_replace_callback('/\b(?:Sr|Sra|Dr|Dra|Dña|Prof|D)\.(?=\s+\p{Lu})/u',
        static fn($m)=>str_replace('.',"\u{E000}",$m[0]),trim($text));
    $segments=preg_split('/(?:\n{2,}|(?<=[.!?])\s+(?=[\p{Lu}¿¡«“]))/u',$safe??trim($text));
    return array_values(array_filter(array_map(static fn($s)=>str_replace($dot,'.',trim($s)),$segments),fn($s)=>$s!==''));
}
/** Multiple simultaneous speech acts are possible; avoid forcing an exclusive label. */
function pp_text_types(string $segment): array {
    if(preg_match('/^[«“"‘].*[»”"’]$/us',$segment))return ['cita'];
    $patterns=[
        'prediccion'=>'/\b(mañana|ocurrirá|será|habrá|podría\s+[\p{L}]+|se prevé|predecimos|en el futuro|se espera que)\b/iu',
        'afirmacion_causal'=>'/\b(porque|provoca|causa|causado por|debido a|hace que|produce|consecuencia de)\b/iu',
        'inferencia'=>'/\b(por tanto|por eso|se deduce|en consecuencia|de ello se infiere|significa que)\b/iu',
        'opinion'=>'/\b(creo|opino|considero|me parece|debería|pienso que|a mi juicio)\b/iu',
        'condicional'=>'/\b(si|en caso de que|suponiendo que)\b/iu',
        'cita'=>'/[«“"‘][^»”"’]{1,800}[»”"’]/u',
        'hecho_verificable'=>'/\b\d{1,4}(?:[.,]\d+)?\s*(?:%|euros?|años?|personas|casos|delitos|denuncias|condenas|hechos|hogares|habitantes)(?=\s|[.,;!?]|$)/iu'
    ];
    $types=[];foreach($patterns as $label=>$pattern)if(preg_match($pattern,$segment))$types[]=$label;
    return $types?:['indeterminado'];
}
function pp_text_type(string $segment): string {return pp_text_types($segment)[0];}
/** Avoid attributing a quoted claim or reported speech to the person analysing it. */
function pp_text_signal_context(string $segment,int $offset,int $length,string $type): string {
    if($type==='cita'&&preg_match('/^[«“"‘].*[»”"’]$/us',trim($segment)))return 'quoted_speech';
    preg_match_all('/«[^»]{1,800}»|“[^”]{1,800}”|‘[^’]{1,800}’|"[^"]{1,800}"/u',$segment,$quoted,PREG_OFFSET_CAPTURE);
    foreach($quoted[0] as [$text,$start])
        if($offset>=$start&&$offset+$length<=$start+strlen($text))return 'quoted_speech';
    $prefix=substr($segment,0,$offset); // PCRE offsets are UTF-8 byte positions.
    $clause=(string)preg_replace('/^.*[.;:!?\n]/us','',$prefix);
    // A contrastive connector can start a new speaker's assertion within one sentence.
    // 'El ministro dijo que todos... pero los datos no lo prueban' has two scopes.
    $scope=(string)preg_replace('/^.*\b(?:pero|sin embargo|en cambio|no obstante)\b/ius','',$clause);
    if(preg_match('/\b(no|sin|ni|nunca|tampoco)\b(?:\s+[\p{L}]+){0,5}\s*$/iu',$scope))return 'negation_nearby';
    // A deliberately hypothetical quotation is not the analyst's own claim.
    // Restrict this to explicit constructions; generic "si" can be part of an
    // asserted conditional and should not suppress a real statement.
    if(preg_match('/\b(?:si alguien dijera|si alguien afirmara|si se dijera|si se afirmara|supongamos que|imaginemos que|hipot[eé]ticamente)\b/iu',$scope))
        return 'hypothetical_context';
    if(preg_match('/\b(?:seg[uú]n|dijo|afirm[oó]|declar[oó]|se[ñn]al[oó]|cit[oó]|asegur[oó]|sostuvo|escribi[oó]|public[oó])\b(?:[^.;:!?]{0,140})$/iu',$scope))
        return 'reported_speech';
    return 'review_context';
}
/** Exact arithmetic on explicitly written percentage pairs. This is NOT fact checking:
 * the source numbers may be false, misquoted or measured on different bases.
 * Never infer a missing baseline or claim causal effects from a computed difference. */
function pp_text_quantitative_checks(string $segment): array {
    $checks=[];
    if(preg_match_all('/\bpas[oó]\s+de\s+(\d{1,3}(?:[.,]\d{1,3})?)\s*%\s+a\s+(\d{1,3}(?:[.,]\d{1,3})?)\s*%/iu',$segment,$matches,PREG_SET_ORDER)){
        foreach(array_slice($matches,0,4) as $m){
            $before=(float)str_replace(',','.',$m[1]);$after=(float)str_replace(',','.',$m[2]);
            $checks[]=['kind'=>'explicit_percentage_pair','before_pct'=>$before,'after_pct'=>$after,
                'difference_percentage_points'=>round($after-$before,4),
                'relative_change_percent'=>$before==0.0?null:round(($after-$before)/$before*100,4),
                'baseline_zero'=>$before==0.0,'truth_status'=>'not_assessed',
                'note'=>'Aritmética del texto, no verificación de la fuente. Los dos porcentajes deben tener población, denominador y definición comparables.'];
            // Only compare an explicitly stated change in *percentage points*.
            // A bare "subió un 5 %" is a relative-change claim and must not be
            // silently equated with five percentage points.
            if(count($matches)===1 && preg_match('/\b(?:subi[oó]|aument[oó]|creci[oó]|baj[oó]|descendi[oó]|disminuy[oó])\s+(?:en\s+|un\s+|una\s+)?(\d{1,3}(?:[.,]\d{1,3})?)\s+puntos?\s+porcentuales?\b/iu',$segment,$claimed)){
                $claimedPoints=(float)str_replace(',','.',$claimed[1]);
                $isDecrease=(bool)preg_match('/\b(?:baj[oó]|descendi[oó]|disminuy[oó])\s+(?:en\s+|un\s+|una\s+)?'.preg_quote($claimed[1],'/').'/iu',$segment);
                $signed=$isDecrease?-$claimedPoints:$claimedPoints;
                $checks[]=['kind'=>'explicit_percentage_point_consistency','claimed_change_pp'=>$signed,
                    'computed_change_pp'=>round($after-$before,4),
                    'arithmetically_consistent'=>abs($signed-($after-$before))<0.0001,
                    'truth_status'=>'not_assessed','intent_status'=>'not_assessed',
                    'note'=>'Solo coherencia aritmética entre cifras declaradas. No verifica la fuente, población, periodo ni intención del emisor.'];
            }
        }
    }
    return $checks;
}
/** A percentage-point change and a relative-percent change are different.
 * Only inspect a single, syntactically adjacent pair+claim. Do not infer that
 * a distant "aumentó 25%" refers to the same population or measurement.
 * An initial 0% has no finite relative percentage change. */
function pp_text_relative_change_checks(string $segment): array {
    $pair='/\bpas[oó]\s+de\s+(\d{1,3}(?:[.,]\d{1,3})?)\s*%\s+a\s+(\d{1,3}(?:[.,]\d{1,3})?)\s*%/iu';
    if(preg_match_all($pair,$segment,$all)!==1)return [];
    $joined='/\bpas[oó]\s+de\s+(\d{1,3}(?:[.,]\d{1,3})?)\s*%\s+a\s+(\d{1,3}(?:[.,]\d{1,3})?)\s*%\s*(?:y|,)\s*(aument[oó]|subi[oó]|creci[oó]|baj[oó]|descendi[oó]|disminuy[oó])\s+(?:(?:en|un|una)\s+){0,2}(\d{1,3}(?:[.,]\d{1,3})?)\s*%(?!\s*(?:puntos?|pp)\b)/iu';
    if(!preg_match($joined,$segment,$m))return [];
    $before=(float)str_replace(',','.',$m[1]);$after=(float)str_replace(',','.',$m[2]);
    $claimed=(float)str_replace(',','.',$m[4]);
    if(preg_match('/^(?:baj|descend|disminuy)/iu',$m[3]))$claimed=-$claimed;
    $computed=$before===0.0?null:($after-$before)/$before*100;
    return [['kind'=>'explicit_relative_percentage_consistency','before_pct'=>$before,'after_pct'=>$after,
        'claimed_change_relative_percent'=>$claimed,'computed_change_relative_percent'=>$computed===null?null:round($computed,4),
        'arithmetically_consistent'=>$computed===null?null:abs($claimed-$computed)<=0.02,
        'baseline_zero'=>$before===0.0,'truth_status'=>'not_assessed','intent_status'=>'not_assessed',
        'note'=>'Comprobación aritmética de una afirmación relativa explícita y adyacente; no verifica cifras, fuente, población, periodo ni intención.']];
}
/** A verification plan is a research checklist, never a truth/intent score.
 * It separates a quoted assertion from the analyst's own proposition.
 */
function pp_text_verification_tasks(string $segment,array $types): array {
    $tasks=[];
    if(in_array('hecho_verificable',$types,true)){
        $tasks[]=['code'=>'primary_source','question'=>'¿Qué fuente primaria respalda la cifra o afirmación?'];
        $tasks[]=['code'=>'period_population','question'=>'¿Cuál es el periodo, territorio, población y denominador exactos?'];
    }
    if(preg_match('/\d+(?:[,.]\d+)?\s*%/u',$segment)){
        $tasks[]=['code'=>'percentage_basis','question'=>'¿El porcentaje se refiere a personas, casos, respuestas o cambios relativos? ¿Se indica la base?'];
        if(preg_match('/\b(?:subi[oó]|baj[oó]|aument[oó]|disminuy[oó]|pas[oó]\s+de|variaci[oó]n|diferencia)\b/iu',$segment))
            $tasks[]=['code'=>'percent_vs_pp','question'=>'¿El cambio está expresado en puntos porcentuales o en porcentaje relativo?'];
    }
    if(preg_match('/\b(?:semestre|enero\s+a\s+junio|acumulad[oa]s?|primeros\s+seis\s+meses|trimestre|año\s+completo)\b/iu',$segment)){
        $tasks[]=['code'=>'cumulative_vs_quarter','question'=>'¿La cifra es acumulada desde enero, del trimestre aislado o de un año completo? ¿El periodo comparado tiene la misma duración?'];
    }
    if(preg_match('/\b(?:provisional(?:es)?|pendientes?\s+de\s+consolidaci[oó]n|estimaci[oó]n(?:es)?|revisi[oó]n\s+estad[ií]stica)\b/iu',$segment)){
        $tasks[]=['code'=>'statistical_revision','question'=>'¿El dato es provisional o estimado? ¿Qué versión y fecha de actualización se usan y se han comprobado revisiones posteriores?'];
    }
    // Domain-specific guardrails: a count of convictions is not a police crime count,
    // and a self-reported survey response is not a clinical diagnosis.
    if(preg_match('/\b(?:delitos?|criminalidad|denuncias?|condenas?|detenciones?|hechos\s+conocidos|hechos\s+esclarecidos)\b/iu',$segment)){
        $tasks[]=['code'=>'justice_measurement','question'=>'¿Se trata de hechos policiales, denuncias, personas detenidas, procedimientos o condenas? ¿Se identifican unidad de recuento, jurisdicción y periodo?'];
    }
    if(preg_match('/\b(?:encuestas?|sondeos?|autoinformad[oa]s?|autodeclarad[oa]s?|diagn[oó]sticos?|prevalencias?|depresi[oó]n|ansiedad)\b/iu',$segment)){
        $tasks[]=['code'=>'survey_diagnosis','question'=>'¿La medida procede de autoinforme, cribado, registro sanitario o diagnóstico clínico? ¿Qué instrumento, muestra y definición se utilizaron?'];
    }
    if(preg_match('/\b(?:por\s+(?:cada\s+)?(?:100\s*000|100\.000|mil|habitantes)|tasas?|ratios?|porcentajes?|de\s+cada)\b|\d+(?:[,.]\d+)?\s*%/iu',$segment)){
        $tasks[]=['code'=>'rate_denominator','question'=>'¿Cuál es el denominador, la unidad temporal, el territorio y la población expuesta? ¿Se comparan tasas equivalentes?'];
    }
    // Verification prompts are NOT allegations of statistical fallacies. They
    // flag information a reader would need before interpreting the assertion.
    if(preg_match('/\b(?:muestra|muestreo|encuestad[oa]s?|sondeo|encuesta|panel|participantes)\b/iu',$segment)){
        $tasks[]=['code'=>'sampling_frame','question'=>'¿Cómo se seleccionó la muestra, cuál fue su tamaño, tasa de respuesta, ponderación y población a la que representa?'];
    }
    if(preg_match('/\b(?:comunidades aut[oó]nomas|ccaa|provincias|regiones|territorios)\b/iu',$segment)){
        $tasks[]=['code'=>'territorial_aggregation','question'=>'¿Se trata de datos agregados por territorio? ¿Se está atribuyendo indebidamente a individuos una propiedad del conjunto?'];
    }
    if(preg_match('/\b(?:aument[oó]|disminuy[oó]|redujo|creci[oó]|subi[oó]|baj[oó]|se duplic[oó])\b/iu',$segment)){
        $tasks[]=['code'=>'baseline_period','question'=>'¿Cuál es el valor inicial, el periodo de comparación y la diferencia absoluta frente a la relativa?'];
    }
    // Public controversies often conflate nationality, birthplace and migration
    // status. A population breakdown cannot be reconstructed from a headline.
    if(preg_match('/\b(?:inmigrantes?|extranjer[oa]s?|nacionalidad|nacid[oa]s?\s+en\s+españa|origen\s+extranjero|naturalizad[oa]s?)\b/iu',$segment)){
        $tasks[]=['code'=>'nationality_vs_birthplace','question'=>'¿La fuente clasifica nacionalidad actual, país de nacimiento, país de origen o situación migratoria? ¿Se incluye a quienes adquirieron nacionalidad española? ¿Cuál es el denominador?'];
    }
    // ECP and migration debates often substitute the number of foreign
    // residents (a stock) for arrivals in a period (a flow). Neither style
    // nor the word "inmigrante" verifies a person's citizenship or origin.
    if(preg_match('/\b(?:poblaci[oó]n\s+extranjera|residentes\s+extranjer[oa]s?|inmigraci[oó]n|inmigrantes?|saldo\s+migratorio|entradas\s+de\s+poblaci[oó]n)\b/iu',$segment))
        $tasks[]=['code'=>'population_stock_vs_migration_flow','question'=>'¿La cifra es población residente a una fecha (stock), llegadas o salidas durante un periodo (flujos), o saldo migratorio? ¿Se distingue nacionalidad de país de nacimiento?'];
    if(preg_match('/\b(?:a\s+(?:1|uno)\s+de\s+(?:enero|abril|julio|octubre)|fecha\s+de\s+referencia|poblaci[oó]n\s+residente\s+a\s+fecha)\b/iu',$segment))
        $tasks[]=['code'=>'reference_date_vs_period_average','question'=>'¿El dato corresponde a una fecha concreta de referencia o a la media/suma del trimestre? No convertir un stock puntual en flujo o promedio trimestral.'];
    if(preg_match('/\b(?:desahucios?|lanzamientos?\s+judiciales?|ejecuciones?\s+hipotecarias?|alquileres?\s+impagad[oa]s?)\b/iu',$segment)){
        $tasks[]=['code'=>'eviction_unit','question'=>'¿Se cuentan lanzamientos practicados, procedimientos, hogares, personas o inmuebles? ¿Se distingue arrendamiento de ejecución hipotecaria y existen datos sobre nacionalidad o nacimiento?'];
    }
    if(preg_match('/\b(?:ha\s+demostrado|demuestra\s+que|prueba\s+que|cient[ií]ficamente\s+probado|seg[uú]n\s+un\s+estudio)\b/iu',$segment)){
        $tasks[]=['code'=>'evidence_design','question'=>'¿Se identifica estudio primario, diseño, muestra, desenlace, incertidumbre y posibilidad de replicación? ¿La afirmación excede lo realmente medido?'];
    }
    if(preg_match('/\b(?:deepfakes?|v[ií]deos?\s+generad[oa]s?\s+por\s+ia|imagen\s+sint[eé]tica|fotograf[ií]a\s+manipulada)\b/iu',$segment)){
        $tasks[]=['code'=>'media_provenance','question'=>'¿Se ha comprobado procedencia, versión original, contexto temporal y evidencia técnica de manipulación? La apariencia no prueba autenticidad ni falsedad.'];
    }
    if(preg_match('/\b(?:estad[ií]sticamente\s+significativ[oa]s?|significativ[oa]s?)\b/iu',$segment))
        $tasks[]=['code'=>'statistical_significance','question'=>'¿«Significativo» significa importancia práctica o resultado inferencial? ¿Se informan intervalo de confianza, tamaño de efecto, diseño y comparaciones múltiples?'];
    if(preg_match('/\b(?:media|mediana|promedio|renta\s+media|salario\s+medio)\b/iu',$segment))
        $tasks[]=['code'=>'distribution_summary','question'=>'¿La cifra es media, mediana o percentil? ¿Cómo es la distribución y se ajusta por inflación, tamaño del hogar o composición cuando corresponde?'];
    // Grouped percentages can reverse direction after stratification (Simpson).
    // This is a verification question, not a diagnosis of deceptive statistics.
    if(preg_match('/\b(?:paradoja\s+de\s+simpson|al\s+desagregar|por\s+grupos\s+de\s+edad|estratificad[oa]s?|ajustar\s+por\s+composici[oó]n)\b/iu',$segment))
        $tasks[]=['code'=>'simpson_stratification','question'=>'¿El resultado agregado cambia al separar subgrupos? Comprueba composición, ponderaciones, tamaño de cada estrato y definición común antes de atribuir una causa.'];
    if(preg_match('/\b(?:solo\s+(?:se\s+)?(?:contaron|incluyeron|analizaron)|excluyeron\s+(?:los|las)|supervivientes|sesgo\s+de\s+supervivencia|muestra\s+de\s+conveniencia)\b/iu',$segment))
        $tasks[]=['code'=>'selection_denominator','question'=>'¿Quién quedó fuera del recuento o de la muestra? Identifica población elegible, exclusiones, pérdidas y denominador antes de extrapolar.'];
    // A correlation is neither a causal effect nor an individual-level finding.
    // Add questions, never a machine accusation of ecological fallacy.
    if(preg_match('/\b(?:correlaci[oó]n|correlacionad[oa]s?|asociad[oa]s?|asociaci[oó]n|coeficiente\s+(?:de\s+)?(?:pearson|spearman)|relaci[oó]n\s+estad[ií]stica)\b/iu',$segment)){
        $tasks[]=['code'=>'association_not_causation','question'=>'¿La asociación es observacional? ¿Qué variables de confusión, selección, causalidad inversa y cambios temporales se han considerado?'];
        $tasks[]=['code'=>'ecological_inference','question'=>'¿Las unidades analizadas son territorios, instituciones o personas? Una correlación agregada no demuestra la misma relación entre individuos.'];
    }
    if(preg_match('/\b(?:tasa\s+(?:bruta|ajustada|estandarizada)|ajustad[oa]\s+por\s+edad|estandarizaci[oó]n\s+por\s+edad|por\s+100[. ]?000)\b/iu',$segment))
        $tasks[]=['code'=>'rate_standardization','question'=>'¿La tasa es bruta o ajustada por edad/sexo? ¿El denominador y la población de referencia son equivalentes entre territorios y años?'];
    if(preg_match('/\b(?:riesgo\s+relativo|odds\s+ratio|raz[oó]n\s+de\s+riesgos?|duplic[oó]\s+el\s+riesgo)\b/iu',$segment))
        $tasks[]=['code'=>'absolute_risk','question'=>'¿Se informa el riesgo absoluto inicial, tamaño del efecto, intervalo de confianza y número de personas afectadas, además de la razón relativa?'];
    if(preg_match('/\b(?:percepci[oó]n\s+(?:de\s+)?(?:inseguridad|criminalidad|delito)|miedo\s+al\s+delito|inseguridad\s+percibida)\b/iu',$segment))
        $tasks[]=['code'=>'perceived_vs_recorded_security','question'=>'¿Se mide percepción de inseguridad o hechos registrados? Son constructos distintos y pueden variar en direcciones diferentes.'];
    // Evidence quality is a separate dimension from rhetoric. These are
    // reviewer questions only, not automatic labels of fraud or falsehood.
    if(preg_match('/\b(?:preprint|prepublicaci[oó]n|revisi[oó]n\s+por\s+pares|art[ií]culo\s+revisado|working\s+paper)\b/iu',$segment))
        $tasks[]=['code'=>'peer_review_status','question'=>'¿Es un preprint, documento de trabajo o artículo revisado por pares? ¿Hay versión posterior, corrección o retractación?'];
    // Perceived source bias can change receptivity, but it does not establish whether
    // the underlying proposition is true or false. Keep credibility and evidence separate.
    if(preg_match('/\b(?:sesgad[oa]s?|parcial(?:es)?|ideologizad[oa]s?|propaganda|favorece\s+(?:a|al)|contra\s+(?:mi|nuestro)\s+partido)\b/iu',$segment)&&
       preg_match('/\b(?:fuentes?|medios?|peri[oó]dicos?|televisi[oó]n|chatbots?|asistentes?\s+de\s+ia|modelos?\s+de\s+lenguaje|inteligencia\s+artificial)\b/iu',$segment)){
        $tasks[]=['code'=>'source_credibility_vs_claim','question'=>'¿La conclusión evalúa la evidencia de la afirmación o sustituye esa evaluación por una percepción de sesgo de la fuente? Comprueba por separado fuente, datos, método y posibles conflictos de interés.'];
    }
    if(preg_match('/\b(?:margen\s+de\s+error|intervalo\s+de\s+confianza|ic\s*95\s*%|p\s*[<=>]\s*0[,.]0[0-9]|significaci[oó]n\s+estad[ií]stica)\b/iu',$segment))
        $tasks[]=['code'=>'statistical_uncertainty','question'=>'¿El intervalo y margen de error corresponden al diseño muestral y ponderación? ¿Se comunica la incertidumbre y el número de comparaciones?'];
    if(preg_match('/\b(?:sin\s+evidencia|no\s+hay\s+pruebas|no\s+se\s+detect[oó]\s+efecto|no\s+fue\s+significativo)\b/iu',$segment))
        $tasks[]=['code'=>'absence_of_evidence','question'=>'¿Se distingue ausencia de evidencia de evidencia de ausencia? ¿Cuál es la potencia estadística y qué tamaños de efecto descarta el estudio?'];
    if(preg_match('/\b(?:ensayo\s+aleatorizado|experimento\s+aleatorizado|randomizad[oa]|grupo\s+control|experimento\s+de\s+campo)\b/iu',$segment))
        $tasks[]=['code'=>'experimental_validity','question'=>'¿Hubo asignación aleatoria real, pérdidas de seguimiento, contaminación entre grupos y desenlaces preregistrados? ¿A qué población se generaliza?'];
    // Personalised moral framing is not, by itself, deception or manipulation.
    // Require BOTH an AI/algorithmic context and a framing/values claim before
    // asking for evidence of the actual persuasive intervention.
    if(preg_match('/\b(?:inteligencia\s+artificial|chatbots?|asistentes?\s+de\s+ia|algoritmos?|modelos?\s+de\s+lenguaje)\b/iu',$segment)&&
       preg_match('/\b(?:valores?\s+morales?|identidad\s+pol[ií]tica|encuadre\s+moral|personalizaci[oó]n\s+persuasiva|adapt[ao]\s+(?:sus?\s+)?argumentos?\s+a\s+los?\s+valores?)\b/iu',$segment)){
        $tasks[]=['code'=>'moral_framing_evidence','question'=>'¿Se manipuló experimentalmente el encuadre de valores o solo se observó una asociación? ¿Qué recomendación, datos y población se mantuvieron constantes? No se infiere intención manipuladora.'];
    }
    if(preg_match('/\b(?:disposici[oó]n\s+(?:de\s+|a\s+)?pagar|intenci[oó]n\s+de\s+voto|intenci[oó]n\s+de\s+compra|declar[oó]\s+que\s+(?:pagar[ií]a|votar[ií]a|comprar[ií]a)|apoyo\s+declarado)\b/iu',$segment)){
        $tasks[]=['code'=>'stated_vs_observed_behavior','question'=>'¿Se midió una intención o disposición declarada, o una conducta efectivamente observada? ¿Hay seguimiento y resultados externos? No equiparar ambos desenlaces.'];
    }
    // V57: narrative corrections are research questions, not automated proof
    // that a correction failed or that a speaker deliberately misled anyone.
    if(preg_match('/\b(?:desmentid[oa]s?|rectificaci[oó]n(?:es)?|correcci[oó]n(?:es)?|informaci[oó]n\s+falsa|bulo(?:s)?)\b/iu',$segment)&&
       preg_match('/\b(?:narrativ[ao]s?|explicaci[oó]n(?:es)?|caus[ao]s?|secuencia|acontecimientos?|noticias?|historia|modelo\s+mental)\b/iu',$segment)){
        $tasks[]=['code'=>'narrative_correction_evidence','question'=>'¿La rectificación solo niega una proposición o ofrece una explicación alternativa documentada y coherente con la secuencia de hechos? Es una hipótesis de investigación, no una prueba automática de eficacia.'];
    }
    if(preg_match('/\b(?:mediaci[oó]n|mediador(?:a|es)?|mecanismo\s+causal|variable\s+intermedia)\b/iu',$segment)&&
       preg_match('/\b(?:experimento|intervenci[oó]n|tratamiento|efecto|causa)\b/iu',$segment)){
        $tasks[]=['code'=>'mediation_first_stage','question'=>'¿La intervención cambió realmente la variable mediadora? Sin un primer efecto identificable no se puede atribuir un resultado posterior a ese mecanismo. Revisar potencia, incertidumbre y supuestos.'];
    }
    // V60: A cross-sectional association does not identify a causal effect,
    // even when a mediation model or bootstrap confidence interval is used.
    // This is a verification question, not a verdict about the claim's truth.
    if(preg_match('/\b(?:encuesta\s+transversal|estudio\s+transversal|datos\s+transversales|estudio\s+observacional|correlaci[oó]n|modelo\s+de\s+mediaci[oó]n)\b/iu',$segment)&&
       preg_match('/\b(?:demuestra\s+que|prueba\s+que|causa|provoca|determina|efecto\s+causal|cadena\s+causal|hace\s+que)\b/iu',$segment)){
        $tasks[]=['code'=>'observational_causal_claim','question'=>'¿El diseño identifica causalidad o solo asociaciones? Un modelo de mediación transversal, incluso con bootstrap, no sustituye asignación, temporalidad ni control de confusión.'];
    }
    // Statistical significance is not effect size, and lack of significance
    // does not establish that an effect is exactly zero.
    if(preg_match('/\b(?:no\s+fue\s+significativ[oa]|no\s+se\s+detect[oó]\s+efecto|p\s*[>≥]\s*0[,.]05)\b/iu',$segment)&&
       preg_match('/\b(?:no\s+existe\s+efecto|efecto\s+cero|no\s+afecta|demuestra\s+que\s+no)\b/iu',$segment)){
        $tasks[]=['code'=>'null_result_overinterpretation','question'=>'¿Se estimó un intervalo suficientemente estrecho para descartar efectos relevantes, o solo no se rechazó la hipótesis nula?'];
    }
    // V62: do not infer population attitudes from visible posts or network
    // clusters. Privacy settings and selective participation can change who
    // speaks and to whom, without a measured change in private attitudes.
    if(preg_match('/\b(?:polarizaci[oó]n|polarizad[oa]s?|moderad[oa]s?|radicalizaci[oó]n)\b/iu',$segment)&&
       preg_match('/\b(?:redes\s+sociales|publicaciones|comentarios|mensajes|perfiles|usuarios|algoritmo|audiencias?|privacidad|interacciones|plataformas)\b/iu',$segment)){
        $tasks[]=['code'=>'expressed_vs_latent_polarization','question'=>'¿Se mide la postura expresada públicamente, la interacción entre cuentas o la actitud privada de personas representativas? La selección de audiencia y la autocensura pueden alterar lo visible sin demostrar cambios poblacionales.'];
    }
    if(preg_match('/\b(?:grafo|red\s+de\s+interacciones|comunidades\s+de\s+usuarios|clusters?|c[aá]maras\s+de\s+eco|nodos\s+y\s+enlaces)\b/iu',$segment)&&
       preg_match('/\b(?:polarizaci[oó]n|radicalizaci[oó]n|opiniones|ideolog[ií]a|desinformaci[oó]n)\b/iu',$segment)){
        $tasks[]=['code'=>'network_structure_vs_psychology','question'=>'¿La estructura de conexiones se ha validado contra actitudes, contenidos y muestreo? La separación de comunidades digitales no equivale automáticamente a polarización psicológica ni a prevalencia nacional.'];
    }
    // V65: comparability safeguards are questions, never automatic verdicts.
    // A birthplace category is not equivalent to citizenship or migration status.
    if(preg_match('/\b(?:pa[ií]s\s+de\s+nacimiento|nacid[oa]s?\s+(?:en|fuera)|origen\s+extranjero)\b/iu',$segment))
        $tasks[]=['code'=>'birthplace_not_nationality','question'=>'¿El dato clasifica por país de nacimiento, nacionalidad actual, ascendencia o situación migratoria? No son poblaciones intercambiables.'];
    if(preg_match('/\b(?:busc[oó]\s+vivienda|b[uú]squeda\s+de\s+vivienda|sin\s+cambiar\s+de\s+residencia|no\s+(?:pudo|consigui[oó])\s+(?:mudarse|alquilar))\b/iu',$segment))
        $tasks[]=['code'=>'housing_search_not_eviction','question'=>'¿Se mide búsqueda activa sin mudanza, demanda insatisfecha, denegación contractual, emancipación o desahucio? No equivalen al mismo resultado.'];
    if(preg_match('/\b(?:encuesta\s+de\s+condiciones\s+de\s+vida|\bECV\s*2025\b)\b/iu',$segment)&&
       preg_match('/\b(?:renta|ingresos?|salarios?)\b/iu',$segment))
        $tasks[]=['code'=>'ecv_income_reference_year','question'=>'¿La cifra procede de la ECV 2025 pero refiere ingresos del año natural 2024? Distingue año de encuesta y periodo económico medido.'];
    if(preg_match('/\b(?:lenguaje\s+(?:basado\s+en\s+)?(?:evidencia|intuici[oó]n)|estilo\s+ret[oó]rico|puntuaci[oó]n\s+EMI|evidence\s+minus\s+intuition)\b/iu',$segment))
        $tasks[]=['code'=>'rhetoric_metric_not_truth','question'=>'¿La métrica identifica proximidad a un estilo lingüístico o verifica proposiciones factuales? Una puntuación retórica no demuestra mentira, honestidad, intención ni verdad.'];
    // V67: a survey of digital civic activities is not an election result.
    // Do not flag ordinary political participation without an explicit online
    // activity context; the output is a verification question, not a verdict.
    if(preg_match('/\b(?:participaci[oó]n\s+(?:pol[ií]tica\s+y\s+social|c[ií]vica|pol[ií]tica)|consultas?\s+p[uú]blicas?)\b/iu',$segment)&&
       preg_match('/\b(?:internet|en\s+l[ií]nea|online|digital|redes\s+sociales|TICH\s*2025)\b/iu',$segment)){
        $tasks[]=['code'=>'online_civic_activity_not_electoral_turnout',
            'question'=>'¿La encuesta mide actividades cívicas por Internet, voto electoral, militancia o participación presencial? ¿Cuál es el universo de edad y la ventana de recuerdo? No equiparar estas medidas.'];
    }
    if(preg_match('/\b(?:uso|usuarios?|adopci[oó]n|utilizaci[oó]n)\s+(?:de\s+)?(?:herramientas?\s+de\s+)?(?:ia\s+generativa|inteligencia\s+artificial\s+generativa)\b/iu',$segment)&&
       preg_match('/\b(?:persuasi[oó]n|voto|manipulaci[oó]n|desinformaci[oó]n|eficacia\s+pol[ií]tica)\b/iu',$segment)){
        $tasks[]=['code'=>'ai_adoption_not_persuasion_effect',
            'question'=>'¿La cifra mide uso declarado de IA o un efecto experimental sobre persuasión, voto o desinformación? La prevalencia de uso no prueba un efecto político.'];
    }
    if(preg_match('/\b(?:habilidades|competencias)\s+digitales\b/iu',$segment)&&
       preg_match('/\b(?:encuesta|INE|Eurostat|b[aá]sicas?|avanzadas?|66[,.]5)\b/iu',$segment)){
        $tasks[]=['code'=>'digital_skills_composite_not_clinical_assessment',
            'question'=>'¿Se trata del indicador compuesto de actividades TIC de Eurostat o de una prueba directa de competencias? No interpretar el porcentaje como diagnóstico individual.'];
    }
    if(preg_match('/\b(?:tres|3)\s+[uú]ltimos?\s+meses|\b[uú]ltimos?\s+(?:tres|3)\s+meses/iu',$segment)&&
       preg_match('/\b(?:doce|12)\s+[uú]ltimos?\s+meses|\b[uú]ltimos?\s+(?:doce|12)\s+meses/iu',$segment)){
        $tasks[]=['code'=>'mixed_survey_lookback_windows',
            'question'=>'¿Las variables usan periodos retrospectivos de tres y doce meses distintos? No presentar porcentajes como si midieran la misma ventana temporal.'];
    }
    // V68: an identical between-party warmth gap can describe two very
    // different groups (indifference or hostility toward both). Do not equate
    // a low difference score with support for democracy or political apathy.
    if(preg_match('/\b(?:polarizaci[oó]n\s+afectiva|no\s+polarizad[oa]s?|despolarizaci[oó]n)\b/iu',$segment)&&
       preg_match('/\b(?:term[oó]metro|simpat[ií]a|diferencia|distancia|indiferencia|rechazo|ambos\s+partidos|dos\s+partidos)\b/iu',$segment)){
        $tasks[]=['code'=>'affective_gap_not_absolute_warmth',
            'question'=>'¿La medida es la diferencia de simpatía entre partidos o también su nivel absoluto? Una diferencia pequeña puede coexistir con indiferencia o rechazo hacia ambos. Revisar instrumento y población antes de interpretar participación o confianza.'];
    }
    // V68: an advance CPI release is not a confirmed definitive monthly
    // series. A slower positive inflation rate is not a falling price level.
    if(preg_match('/\b(?:IPC|IPCA|inflaci[oó]n|precios\s+de\s+consumo)\b/iu',$segment)&&
       preg_match('/\b(?:adelantad[oa]|avance|provisional|estimad[oa]|definitiv[oa])\b/iu',$segment)){
        $tasks[]=['code'=>'ipc_advance_not_final',
            'question'=>'¿La tasa corresponde al avance o al dato definitivo del IPC/IPCA? Comprobar fecha, revisión, base y periodo antes de combinarla con otras series.'];
    }
    if(preg_match('/\b(?:IPC|IPCA|inflaci[oó]n)\b/iu',$segment)&&
       preg_match('/\b(?:baj[oó]\s+la\s+tasa|menor\s+inflaci[oó]n|inflaci[oó]n\s+baj[oó]|precios\s+bajaron|bajaron\s+los\s+precios|desinflaci[oó]n)\b/iu',$segment)){
        $tasks[]=['code'=>'disinflation_not_deflation',
            'question'=>'¿Se afirma que disminuyó la tasa de inflación o que cayó el nivel de precios? Son magnitudes diferentes; comprobar signo de la variación y mes de referencia.'];
    }
    if(in_array('afirmacion_causal',$types,true)){
        $tasks[]=['code'=>'causal_identification','question'=>'¿Existe diseño causal, grupo de comparación, temporalidad y control de explicaciones alternativas?'];
    }
    if(in_array('prediccion',$types,true)){
        $tasks[]=['code'=>'forecast_validation','question'=>'¿Se indica horizonte, incertidumbre, supuestos y evaluación fuera de muestra?'];
    }
    if(in_array('cita',$types,true)){
        $tasks[]=['code'=>'quote_context','question'=>'¿La cita es fiel, tiene fuente, fecha y contexto? ¿A quién corresponde?'];
    }
    return $tasks;
}
function pp_text_analyze(string $text): array {
    $rules=pp_parse(file_get_contents(__DIR__.'/text-rules.json'))['rules']??[];$segments=pp_text_propositions($text);
    $results=[];$signalCount=0;$signalsToReview=0;$signalsContextOnly=0;
    foreach(array_slice($segments,0,80) as $index=>$segment){
        $types=pp_text_types($segment);$type=$types[0];$signals=[];
        foreach($rules as $rule){
            if(count($signals)>=8)break;$regex='~'.str_replace('~','\\~',$rule['pattern']).'~iu';
            $match=[];$ok=@preg_match($regex,$segment,$match,PREG_OFFSET_CAPTURE);
            if($ok!==1)continue;
            $offset=$match[0][1];$context=pp_text_signal_context($segment,$offset,strlen($match[0][0]),$type);
            $contextOnly=in_array($context,['quoted_speech','reported_speech','negation_nearby','hypothetical_context'],true);
            if($contextOnly)$signalsContextOnly++;else $signalsToReview++;
            $signals[]=['code'=>$rule['code'],'label'=>$rule['label'],'fragment'=>$match[0][0],
                'explanation'=>$rule['explanation'],'alternative'=>$rule['alternative'],'limit'=>$rule['limit'],
                'confidence'=>'heuristic_low','context_flag'=>$context,
                'classification'=>$contextOnly?'context_only_not_attributed':'linguistic_signal_to_review',
                'requires_human_review'=>true];
        }
        $signalCount+=count($signals);
        $results[]=['index'=>$index,'text'=>$segment,'type'=>$type,'type_tags'=>$types,'truth_status'=>'not_assessed','intent_status'=>'not_assessed','signals'=>$signals,'verification_tasks'=>pp_text_verification_tasks($segment,$types),'quantitative_checks'=>pp_text_quantitative_checks($segment),'relative_change_checks'=>pp_text_relative_change_checks($segment),'evidence_needed'=>in_array('hecho_verificable',$types,true)||in_array('afirmacion_causal',$types,true)];
    }
    return ['version'=>'2.0-preliminary','proposition_count'=>count($segments),'analyzed_count'=>count($results),'truncated'=>count($segments)>80,'signals_total'=>$signalCount,
        'signals_to_review'=>$signalsToReview,'signals_context_only'=>$signalsContextOnly,'propositions'=>$results,
        'truth_status'=>'not_assessed','intent_status'=>'not_assessed','stored'=>false,
        'limitations'=>['La clasificación es heurística y puede equivocarse.','Una señal lingüística no demuestra falacia, mentira o manipulación intencional.','Las citas, negaciones y atribuciones se separan como señales de contexto; no son acusaciones contra el autor.','La verificación factual requiere fuentes externas y revisión humana.']];
}
function pp_text_v2_api(string $path): never {
    if($path==='/api/v1/text/taxonomy'&&$_SERVER['REQUEST_METHOD']==='GET'){
        header('Cache-Control: public,max-age=86400');pp_reply(pp_parse(file_get_contents(__DIR__.'/../database/ANALIZADOR_TAXONOMY_V2.json')));
    }
    if($path==='/api/v1/text/analyze'&&$_SERVER['REQUEST_METHOD']==='POST'){
        pp_rate('text-analysis','',25,300);$p=pp_body();$text=$p['text']??null;
        if(!is_string($text)||mb_strlen($text)<12||mb_strlen($text)>10000)throw new PPError(400,'Introduce entre 12 y 10.000 caracteres.');
        pp_reply(pp_text_analyze($text));
    }
    throw new PPError(405,'Método no permitido.');
}
