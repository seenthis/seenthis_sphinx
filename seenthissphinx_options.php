<?php

if (in_array($_GET['page'], array('sphinx','recherche'))
AND isset($_REQUEST['recherche'])) {
  $_GET['var_recherche'] = trim(preg_replace(',\W+,u',' ',$_REQUEST['recherche']));
}

// nom de l'index pour l'enregistrement des donnees
defined('_SPHINXQL_INDEX') || define('_SPHINXQL_INDEX', 'seenthisrt');

function seenthissphinx_indexer_me($t) {
	if (is_array($t))
		$id_me = intval( ($t['id_parent'] > 0)
			? $t['id_parent'] : $t['id_me']
		);
	else
		$id_me = $t;

	spip_log('job_queue_add indexer'.$id_me, 'sphinx');
	job_queue_add('indexer_sphinx', 'Indexer sphinx '.$id_me, array($id_me), 'indexer_sphinx', true);
}


function sphinx_retraiter_env($env, $quoi) {
	static $e;
	
	if (!isset($e)) {
		$e = unserialize($env);

		$e['recherche_initiale'] = $e['recherche'];

		if (preg_match(',\bhttps?://\S+,iu', $e['recherche'], $r)) {
			$e['recherche'] = str_replace($r[0], seenthissphinx_normaliser_url($r[0]), $e['recherche']);
		}

		if (preg_match(',\#\S+,iu', $e['recherche'], $r)) {
			if (!isset($e['tag'])) {
				$e['tag'] = $r[0];
				$e['recherche'] = str_replace($r[0], ' ', $e['recherche']);
			}
		}

		if (preg_match(',\@(\w+),iu', $e['recherche'], $r)) {
			if (!isset($e['login'])) $e['login'] = $r[1];
			$e['recherche'] = str_replace($r[0], ' ', $e['recherche']);
		}

		$e['recherche'] = trim($e['recherche']);

	}

	return $e[$quoi];
}

// transforme une URL en httprezonet pour pouvoir l'indexer sans tuer
// les mots "rezo" ou "net" ; ˆ utiliser aussi sur la query utilisateur
// s'applique ˆ $u ou ˆ $r=[ match, É ] d'un preg_replace_callback
function seenthissphinx_normaliser_url($u) {
	if (is_array($u)) $u = array_shift($u);

	$u = preg_replace(',^(http|ftp)s?://(www\.)?(.*),i', '\\1-\\3', $u);
	$u = preg_replace(',\W+,u', '', $u);

	return "$u*";
}

