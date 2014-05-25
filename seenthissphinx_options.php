<?php

if ($_GET['page'] == 'sphinx' AND isset($_REQUEST['recherche'])) {
  $_GET['var_recherche'] = preg_replace(',\W+,u',' ',$_REQUEST['recherche']);
}

defined('_SPHINXQL_INDEX') || define('_SPHINXQL_INDEX', 'seenthisrt');

function seenthissphinx_indexer_me($t) {
	if (is_array($t))
		$id_me = intval( ($t['id_parent'] > 0)
			? $t['id_parent'] : $t['id_me']
		);
	else
		$id_me = $t;
	job_queue_add('indexer_sphinx', 'Indexer sphinx '.$t, array($t), 'indexer_sphinx', true);
}


function sphinx_retraiter_env($env, $quoi) {
	static $e;
	
	if (!isset($e)) {
		$e = unserialize($env);

		$e['recherche_initiale'] = $e['recherche'];

		if (preg_match(',\bhttps?://\S+,iu', $e['recherche'], $r)) {
			if (!isset($e['url'])) $e['url'] = rtrim($r[0], '/');
			$e['recherche'] = str_replace($r[0], ' ', $e['recherche']);
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