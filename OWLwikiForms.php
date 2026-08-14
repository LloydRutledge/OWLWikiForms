<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/OWLwikiForms/OWLwikiForms.php" );
EOT;
        exit( 1 );
}
 
$wgExtensionCredits['specialpage'][] = array(
        'name' => 'OWLwikiForms',
        'author' => 'Lloyd Rutledge',
        'url' => 'http://icommas.ou.nl/lru/OWLwikiForms/',
        'description' => 'RDF(S)/OWL-based forms for RDF export and wiki interface generation',
        'descriptionmsg' => 'OWL Wiki Forms',
        'version' => '0.1.2',
);
 
$dir = dirname(__FILE__) . '/';
 
$wgAutoloadClasses['SpecialOWLwikiForms'] = $dir . 'SpecialOWLwikiForms.php'; # Location of the SpecialOWLwikiForms class (Tell MediaWiki to load this file)
$wgExtensionMessagesFiles['OWLwikiForms'] = $dir . 'OWLwikiForms.i18n.php'; # Location of a messages file (Tell MediaWiki to load this file)
$wgSpecialPages['OWLwikiForms'] = 'SpecialOWLwikiForms'; # Tell MediaWiki about the new special page and its class name
