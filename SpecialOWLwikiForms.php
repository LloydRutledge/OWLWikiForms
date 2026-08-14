<?php

define("WIKIURI", "http://localhost/OWF/");

// Prefixes used in all SPARQL queries
define("PREFIXES",
		'PREFIX :        <' . WIKIURI  . '>' . <<<EOT
		PREFIX rdf:     <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
		PREFIX rdfs:    <http://www.w3.org/2000/01/rdf-schema#>
		PREFIX owl:     <http://www.w3.org/2002/07/owl#>
		PREFIX fresnel: <http://www.w3.org/2004/09/fresnel#>
EOT
);

class SpecialOWLwikiForms extends SpecialPage {

	function __construct() {
		parent::__construct     ('OWLwikiForms') ;
		wfLoadExtensionMessages ('OWLwikiForms') ;
	}

	function execute( $par ) {
		global $wgOut, $wgRequest, $wgScript, $owfgSparqlQueryEndpoint, $inOntosStr;

		$action = $wgRequest->getText( 'action' );
		if ( $action == 'generate' ) {
			 
			$inOntosStr = $wgRequest->getText( 'InOntosStr' );
			$inOntoArr=explode(",",$inOntosStr);        // Split comma-seperated URIs
			
			endpointUpd ('CLEAR');                      // Empty endpoint
			foreach ($inOntoArr as $inOnto) {           // For each URI
				endpointUpd ('LOAD <' . $inOnto . '>'); // Load ontology to endpoint
			}
			
			///////////////////////////////////////////////////////////////////////////
			// Generate Fresnel triples
			///////////////////////////////////////////////////////////////////////////

			// for properties with asserted or inferred domains
			endpointUpd ( <<<EOT
					INSERT {
					  ?lensuri rdf:type       fresnel:Lens ;
					  fresnel:classLensDomain ?domain      ;
					  fresnel:showProperties  ?prop                 .
					}

					WHERE {
				      ?prop a           rdf:Property ;
					        rdfs:domain ?domain        .
				  	  FILTER ( ! bound(?subDomain) )
					  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/1999/02/22-rdf-syntax-ns#" ) )
					  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2000/01/rdf-schema#"       ) )
				  	  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2002/07/owl#"              ) )
				  	  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2004/09/fresnel#"          ) )
					  BIND(IRI(CONCAT("
EOT
					. WIKIURI . 'defaultLens", ' . <<<EOT

						REPLACE(str(?domain), "[^A-Za-z0-9]", "", "i" )
					  )) AS ?lensuri)
				      OPTIONAL {
					    ?prop      rdfs:domain     ?subDomain .
					    ?subDomain rdfs:subClassOf ?domain    .
					    FILTER ( ! sameterm ( ?domain , ?subDomain) )
		              }
		            }
EOT
			);

			// for properties with no domains
			endpointUpd ( <<<EOT
					INSERT {
					  ?lensuri rdf:type       fresnel:Lens ;
					  fresnel:classLensDomain owl:Thing    ;
					  fresnel:showProperties  ?prop                 .
					}

					WHERE {
				      ?prop a           rdf:Property   .
				  	  FILTER ( ! bound(?domain) )
					  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/1999/02/22-rdf-syntax-ns#" ) )
					  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2000/01/rdf-schema#"       ) )
				  	  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2002/07/owl#"              ) )
				  	  FILTER ( ! regex(str( ?prop   ), "http://www.w3.org/2004/09/fresnel#"          ) )
					  BIND(IRI(CONCAT("
EOT
					. WIKIURI . 'defaultLens", ' . <<<EOT

						REPLACE("http://www.w3.org/2002/07/owl#Thing", "[^A-Za-z0-9]", "", "i" )
					  )) AS ?lensuri)
				      OPTIONAL { ?prop rdfs:domain?domain }
		            }
EOT
			);

			///////////////////////////////////////////////////////////////////////////
			// Query Fresnel triples
			///////////////////////////////////////////////////////////////////////////
			
			// Query all classes for which Fresnel lenses were made
			$qryRtnArrDom = endpointQry ( <<<EOT
				SELECT DISTINCT ?domain
            	WHERE { ?lens fresnel:classLensDomain  ?domain }
            	ORDER BY ?domain
EOT
			);

            $domStr = ''; // Start $domStr as OWF special page table display

			// For each class with a Fresnel lens ...
			foreach(array_keys($qryRtnArrDom['results']['bindings']) as $key) {

				$domain = $qryRtnArrDom['results']['bindings'][$key]['domain']['value']; // Get the class name

				//  Query properties with the class as domain
				$qryRtnArr = endpointQry ( '
					SELECT DISTINCT ?prop
					WHERE {
						 ?lens fresnel:classLensDomain <' . $domain . '> ;
						       fresnel:showProperties  ?prop .
						 OPTIONAL {?lens fresnel:hideProperties  ?prop, ?hideDetect  }
                         FILTER ( !bound (?hideDetect) )
					}
					ORDER BY ?prop
				' );
				
				if      ( strpos  ($domain , '#' )) $domain = substr ( $domain , 1 + strpos  ( $domain , '#' ) );
				else if ( strrpos ($domain , '/' )) $domain = substr ( $domain , 1 + strrpos ( $domain , '/' ) );
				$props = writeBox ( $domain , $qryRtnArr );  // Create the box wiki pages
				$domStr = $domStr . "|-\n| ''" . $domain . "'' <small>([[:Category:" . $domain . "|category]],[[Form:" . $domain . "|form]],[[Template:Informbox " . $domain . "|Informbox]])</small> || " . $props . "\n";

			}

			$wgOut->addWikiText( '= OWL Wiki Forms='                                 );
			$wgOut->addWikiText( 'The interface was last regenerated from the ontology code at <code>' . $inOntosStr . '</code> at ' . date('g:i A l , F j Y.') );
			$wgOut->addWikiText( 'The following forms and templates were generated:' );
			$wgOut->addWikiText( "{|  style='border: 1px solid #aaaaaa; background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em; padding: 0.2em; text-align:left;'\n|-\n! Box !! Properties\n" . $domStr . '|}'                                                                   );
		}

		$html = '<form name="generate" action="" method="POST">' . "\n" .
				'<input type="hidden" name="action" value="generate" />' . "\n" .
				'URIs of ontologies to generate interface from: <input size="75" name="InOntosStr" value="" />' . "\n" .
				'<input type="submit" value="Generate"/></form>' . "\n";
		$wgOut->addHTML( $html );
	}

}

function endpointQry ( $query ) {

	global $owfgSparqlQueryEndpoint;

	$qryRtnJsonDom = file_get_contents( $owfgSparqlQueryEndpoint . '?query=' . urlencode( PREFIXES . $query ) . '&output=json' ) ;
	return json_decode ( $qryRtnJsonDom , true );
}

function endpointUpd ( $update ) {

	global $owfgSparqlUpdateEndpoint;

	file_get_contents($owfgSparqlUpdateEndpoint, false,
			stream_context_create(array('http' => array(
					'method' => 'POST',
					'header' => 'Content-type: application/x-www-form-urlencoded',
					'content' => http_build_query(array('update' => PREFIXES . $update))))));
}

function writeBox ( $boxName , $qryRtnArr ) {

	///////////////////////////////////////
	// Assign constant strings for page code
	///////////////////////////////////////

	// Start of Template page

	$TplStr =
'<includeonly>
{| style="width: 30em; font-size: 90%; border: 1px solid #aaaaaa; background-color: #f9f9f9; color: black; margin-bottom: 0.5em; margin-left: 1em; padding: 0.2em; float: right; clear: right; text-align:left;"
! style="text-align: center; background-color:#ccccff;" colspan="2" |<big>[[:Category:' . $boxName . '|' . $boxName . ']] data</big> [[Special:FormEdit/' . $boxName . '/{{FULLPAGENAME}}|form]]
';

	// End of Template page

	$TplEnd =
'|}

[[Category:' . $boxName . ']]
{{#ifexist: Template:InformboxTop ' . $boxName . '|{{InformboxTop ' . $boxName . '}}| }}
</includeonly>
';

	// Start of Form page

	$FrmStr =
'<noinclude>
This is the "' . $boxName . '" form.
To create a page with this form, enter the page name below;
if a page with that name already exists, you will be sent to a form to edit that page.


{{#forminput:form=' . $boxName . '}}

</noinclude><includeonly>
<div id="wikiPreview" style="display: none; padding-bottom: 25px; margin-bottom: 25px; border-bottom: 1px solid #AAAAAA;"></div>
{{{for template|Informbox ' . $boxName . '|label=' . $boxName . '}}}
{| class="formtable"
';

	// End of Form page

	$FrmEnd = <<<EOT
|}
{{{end template}}}

'''Free text:'''

{{{standard input|free text|rows=10}}}


{{{standard input|summary}}}

{{{standard input|minor edit}}} {{{standard input|watch}}}

{{{standard input|save}}} {{{standard input|preview}}} {{{standard input|changes}}} {{{standard input|cancel}}}
</includeonly>
EOT;

// Generate code for rows

	$props   ='';
	$FrmRows ='';
	$TplRows ='';

	foreach(array_keys($qryRtnArr['results']['bindings']) as $key) {

		$prop     = $qryRtnArr['results']['bindings'][$key]['prop']['value'];
		$propName = URIstripName ( $prop );
		$props    = $props . '[[Property:' . $propName . '|' . $propName . ']], ';
		
		// check if Fresnel override
		
		$qryFormat ='
SELECT ?type WHERE {
  ?format fresnel:propertyFormatDomain <' . $prop . '>;
          fresnel:value ?type
}		
';
		$formatRtnArr = endpointQry  ( $qryFormat );
		$formatURI    = $formatRtnArr['results']['bindings'][0]['type']['value']  ;

		if ( $formatURI == 'http://www.w3.org/2004/09/fresnel#image' ) 
		{
			$pageContent = assignType ( 'URL' );
		}
		else {
		
			
		// get range

		$qryRng ='
SELECT DISTINCT ?range
WHERE {
  <' . $prop . '> rdfs:range ?range
  FILTER ( ! regex( str(?range), "http://www.w3.org/2002/07/owl#Thing"           ) )
  FILTER ( ! regex( str(?range), "http://www.w3.org/2000/01/rdf-schema#Resource" ) )
}
LIMIT 1
';

		// assign Forms autocompletion to range if any

		$qryRngRtnArr = endpointQry  ( $qryRng );
		$rngURI       = $qryRngRtnArr['results']['bindings'][0]['range']['value'] ;
		$rngName      = URIstripName ( $rngURI ) ;

		// Create property page as either SMW datatype or default form
		$input    = '' ;
		$autocomp = '' ;
		if      ( $suffix = stripPrefix ( $rngURI, 'http://www.w3.org/2000/01/rdf-schema#' ) ) 
			switch ( $suffix ) {
				case 'Literal'  : $pageContent = assignType ( 'String'  ) ; break ;
				default         : $pageContent = assignForm ( $rngName  ) ; break ;
			}
		else if ( $suffix = stripPrefix ( $rngURI, 'http://www.w3.org/2001/XMLSchema#'     ) ) 
			switch ( $suffix ) {
				// Boolean via radiobuttons
				case 'anyURI'             :
					$pageContent = assignType ( 'URL'     ) ; break ;
				case 'boolean'            : 
					$pageContent = assignType ( 'Boolean' )                ;
					$input       = '|input type=radiobutton|values=Yes,No' ;
					break ;
				// Date
				case 'dateTime'           :
				case 'date'               :
					$pageContent = assignType ( 'Date'    ) ; break ;
				// String
				case 'string'             : 
				case 'XMLLiteral'         :
                case 'normalizedString'   :
				case 'Name'               :
				case 'token'              :
				case 'NMTOKEN'            :
				case 'language'           :
				case 'NCName'             : 
				case 'base64Binary'       :
				case 'byte'               :
				case 'hexBinary'          :
				case 'unsignedByte'       :
				case 'time'               :
				case 'gYearMonth'         :
				case 'gYear'              :
				case 'gMonthDay'          :
				case 'gDay'               :
				case 'gMonth'             :
					$pageContent = assignType ( 'String'  ) ; break ;
				// Number
				case 'decimal'            :
				case 'rational'           :
				case 'real'               :
				case 'int'                :
				case 'integer'            :
				case 'double'             :
				case 'float'              :
				case 'long'               :
				case 'short'              :
				case 'negativeInteger'    :
				case 'positiveInteger'    :
				case 'nonPositiveInteger' :
				case 'nonNegativeInteger' :
				case 'unsignedByte'       :
				case 'unsignedLong'       :
				case 'unsignedInt'        :
				case 'unsignedShort'      :
					 $pageContent = assignType ( 'Number'  ) ; break ;
				// Page, with default form and category-based autocompletion
				default                   : 
					 $pageContent = assignType ( 'Page'    ) ; break ;		
				 	 break ;
		}
		else { // Page, with default form and category-based autocompletion
			if ($rngName) $pageContent = assignForm ( $rngName )                 ;
			else          $pageContent = assignType ( 'Page'   )                 ;	
			$autocomp                  = '|autocomplete on category=' . $rngName ;
		}
		
	}
		
		$pageContent = $pageContent . " Equivalent URI is [[Equivalent URI::" . $prop . "]]" ;  // Makes owl:equivalent property in RDF export
		makePage ( 'Property' , $propName , $pageContent ) ;

		$FrmRows=$FrmRows .
'|-
! ' . $propName . ':
| {{{field|' . $propName . $autocomp . $input . '|list }}}
';

		$TplRows=$TplRows .
'|- {{#if: {{{' . $propName . '|}}}||style="display:none"}}
! [[Property:' . $propName . '|' . $propName . ']]
| {{#arraymap:{{{' . $propName . '|}}}|,|xxx|[[' . $propName . '::xxx]]}}
';

	}

	// Create pages
	makePage ( 'Category' ,                $boxName , '<span> </span>'         );
	makePage ( 'Template' , 'Informbox ' . $boxName , $TplStr.$TplRows.$TplEnd );
	makePage ( 'Form'     ,                $boxName , $FrmStr.$FrmRows.$FrmEnd );

	return $props;
}

function stripPrefix ( $fullStr, $prefix ) {
	if ( ! strncmp ( $fullStr, $prefix, strlen($prefix) ) ) return substr ( $fullStr , strlen($prefix) ) ;
	else                                                    return 0                                     ;
}

function assignType ( $type ) {
	return 'This property has type [[Has type::' . $type . ']]. ' ;
}

function assignForm ( $rngName ) {
	$return = assignType ( 'Page'    ) ;
	if ( $rngName ) $return = $return . 'This property uses the form [[Has default form::Form:' . $rngName . ']]. ' ;
	return $return;
}

function URIstripName ( $URI ) {
	if      ( strpos  ($URI , '#' )) $name = substr ( $URI , 1 + strpos  ( $URI , '#' ) ) ;
	else if ( strrpos ($URI , '/' )) $name = substr ( $URI , 1 + strrpos ( $URI , '/' ) ) ;
	else                            $name = $URI                                       ;
	return $name;
}

function makePage ( $prefix , $name , $content ) {
	$newarticle = new Article(Title::newFromText( $prefix . ':' . $name ) , 0);
	$newarticle->doEdit( $content , EDIT_UPDATE);
}