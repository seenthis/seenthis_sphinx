<?php

## plugin sphinxql
defined('_SPHINXQL_HOST') || define('_SPHINXQL_HOST', '127.0.0.1');
defined('_SPHINXQL_MODE') || define('_SPHINXQL_MODE', 'sphinxql');
defined('_SPHINXQL_INDEX') || define('_SPHINXQL_INDEX', 'testrt');
defined('SPHINX_SERVER_PORT') || define('SPHINX_SERVER_PORT', 9306);

/**
 * Démarrer une connexion sur le serveur sphinxql
 */
function sphinxql_connect() {
	static $connection;

	if (!isset($connection)) $connection = mysqli_connect(_SPHINXQL_HOST, null, null, null, SPHINX_SERVER_PORT);

	return $connection;
}

/**
 * Lancer une requête sur le serveur sphinxql
 * 
 * @param string $query
 *      Chaîne contenant la requête à lancer
 */
function sphinxql_query($query) {
	// tester ou etablir la connexion SphinxQL
	if (!$connection = sphinxql_connect()) return false;

	// envoyer la query
	$res = mysqli_query($connection, $query);

	if (!$res) {
		echo $query;
		var_dump( mysqli_error($connection) );
		exit;
	}

	// etablir le succès (renvoie true/false ou une ressource MySQL si SELECT)
	return $res;
}

function sphinxql_indexer_document($doc = array()) {
	if (_SPHINXQL_MODE == 'sphinxql')
		sphinxql_query('REPLACE INTO '._SPHINXQL_INDEX.' … ');

	if (_SPHINXQL_MODE == 'xmlpipe')
		echo sphinxql_xml($doc);

	return true; // succès
}

function sphinxql_documents( $ids = array() ) {
	return $documents;
}

function sphinxql_indexer_documents( $ids = array() ) {
	$documents = sphinxql_documents($ids);

	foreach($documents as $doc) {
		sphinxql_indexer_document($doc);
	}
}

function sphinxql_indexer_tout() {
	// lister les docs
	
	// appeler par batch
}

function sphinxql_allfetsel($select = null, $from=null, $where=null) {
	$docs = $meta = array();
	
	if (is_null($from)){
        $from = _SPHINXQL_INDEX;
	}
	
	// Lancer la requête demandée
	$a = sphinxql_query("SELECT $select FROM $from where $where");
	
	// Récupérer les documents trouvés
	while($t = mysqli_fetch_array($a, MYSQLI_ASSOC)) {
		$docs[] = $t;
	}
	
	// Récupérer les méta-informations des résultats
	$a = sphinxql_query("SHOW META");
	while($t = mysqli_fetch_array($a, MYSQLI_ASSOC)) {
		$meta[] = $t;
	}
	
	return array('docs' => $docs, 'meta'=>$meta);
}

function sphinxql_escape_query($t) {
	if (is_array($t))
		return array_map('sphinxql_escape_query', $t);
	return "'".mysqli_real_escape_string(sphinxql_connect(),$t)."'";
}

function sphinxql_shorten($text) {
	return mb_substr(preg_replace("/\s+/", " ", trim(strip_tags($text))),0,60);	
}

function sphinxql_show_results($res) {
	array_unshift($res['docs'], array_keys($res['docs'][0]));
	foreach ($res['docs'] as $doc) {
		echo '| '.join(' | ', array_map('sphinxql_shorten',$doc))." |\n";
	}

	echo "---------\n";	
	foreach ($res['meta'] as $meta) {
		echo join(':', $meta)."\n";
	}
}
