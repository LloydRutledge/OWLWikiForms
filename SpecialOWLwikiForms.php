<?php
class SpecialOWLwikiForms extends SpecialPage {
  function __construct() {
    parent::__construct( 'OWLwikiForms' );
    wfLoadExtensionMessages('OWLwikiForms');
  }
 
  function execute( $par ) {
    global $wgRequest, $wgOut;
 
    $this->setHeaders();
    $param = $wgRequest->getText('param');
    $wgOut->disable();
    header( "Content-type: application/xml; charset=utf-8" );
    $filename = urlencode( 'OWFformsAndBoxes' . wfTimestampNow() . '.xml' );
    header( "Content-disposition: attachment;filename={$filename}" );
 
    print '<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.3/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.3/ http://www.mediawiki.org/xml/export-0.3.xsd" version="0.3" xml:lang="en">';
    $target_title = Title::newFromText( 'Category:Course' );
    $OWF_article  = new Article( $target_title );
    $params['format']    = 'template'     ;
    $params['template']  = 'OntoXMLClass' ;
    $params['mainlabel'] = '-'            ;
    $params['link']      = 'none'         ;
    $extraprintouts[]    = new SMWPrintRequest( SMWPrintRequest::PRINT_PROP, '',SMWPropertyValue::makeUserProperty( 'Pagename' ) );
    SMWResultPrinter::$maxRecursionDepth = 10;
    print SMWQueryProcessor::getResultFromQueryString(
        '[[rdf:type::Category:owl:Class]]',
        $params, $extraprintouts, SMW_OUTPUT_HTML);
    print "</mediawiki>";
  }
}
