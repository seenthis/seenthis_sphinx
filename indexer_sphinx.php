<?php

function indexer_sphinx($id_me) {
  spip_log('indexer '.var_export($id_me,true), 'sphinx');
  seenthis_indexer_un($id_me);
}

include_spip('sphinxql');

function seenthis_indexer_un($id) {
	seenthis_indexer_conditionnel("me.id_me=".intval($id));
}

/* indexer les messages recemment modifies ; ca ne prend pas en compte les
 * nouveaux partages etc, mais ca permet de rattraper */
function seenthis_indexer_recent($delais = 86400) {
	include_spip('base/abstract_sql');
	$r = sql_allfetsel('DISTINCT(IF(id_parent>0,id_parent,id_me)) as id', 'spip_me', array('date_modif > DATE_SUB(NOW(),INTERVAL '.$delais.' SECOND)'));
	if (count($r)) {
		$in = sql_in('me.id_me', array_map('array_shift', $r));
		seenthis_indexer_conditionnel($in);
	}
}


function seenthis_indexer_tout( ) {
	$req = spip_query('SELECT MIN(id_me) AS mini,MAX(id_me) AS maxi FROM spip_me');
	$t = sql_fetch($req);

	$step = 1000;

	for ($i = intval($t['mini']); $i<= intval($t['maxi']); $i+=$step) {
		spip_timer('indexer_cond');
		seenthis_indexer_conditionnel("(me.id_me>=".$i." AND me.id_me<".($i+$step).")");
		if (defined('_CLI_') AND _CLI_) echo spip_timer('indexer_cond'),"\n";
	}

	sphinxql_query('OPTIMIZE INDEX '._SPHINXQL_INDEX);
}

function seenthis_indexer_conditionnel($where = '1=0') {
	mb_internal_encoding("UTF-8"); # pour mb_strtolower ci-dessous
	spip_query('SET SESSION group_concat_max_len = 1000000');

	$query = '
	SELECT
		me.id_me,
		me.id_auteur,
		me.date,
		a.login,
		a.nom,
		GROUP_CONCAT(DISTINCT(rep.id_auteur)) AS rauteurs,
		GROUP_CONCAT(DISTINCT(ra.login)) AS rlogins,
		GROUP_CONCAT(DISTINCT(ra.nom) SEPARATOR " | ") AS rnoms,
		met.texte,
		GROUP_CONCAT(DISTINCT(rept.id_me)) AS reps,
		GROUP_CONCAT(DISTINCT(IF(rep.statut="publi",rept.texte,"")) SEPARATOR "\n\n----\n\n") AS rept,
		GROUP_CONCAT(DISTINCT(tags.tag) SEPARATOR " | ") AS tags,
		GROUP_CONCAT(DISTINCT(rtags.tag) SEPARATOR " | ") AS rtags,
		GROUP_CONCAT(DISTINCT(sh.id_auteur)) AS share,
		me.statut
	FROM
		spip_me AS me
		JOIN spip_me_texte AS met ON me.id_me = met.id_me
		JOIN spip_auteurs AS a ON me.id_auteur = a.id_auteur
		LEFT JOIN spip_me AS rep ON rep.id_parent = me.id_me
		LEFT JOIN spip_me_texte AS rept ON rept.id_me = rep.id_me
		LEFT JOIN spip_auteurs AS ra ON ra.id_auteur = rep.id_auteur
		LEFT JOIN spip_me_tags AS tags ON tags.id_me = me.id_me
		LEFT JOIN spip_me_tags AS rtags ON rtags.id_me = rep.id_me
		LEFT JOIN spip_me_share AS sh ON sh.id_me = me.id_me
	WHERE
		me.id_parent = 0
		AND '.$where.'
		AND (
		        1=1
			OR me.date_modif > "2014-05-15"
			OR rep.date_modif > "2014-05-15"
		)
	GROUP BY me.id_me
	';

	#echo $query;

	$req = spip_query($query);
	if (!$req) {
		echo $query,"\n";
		echo spip_mysql_error();
		return false;
	}

	while($t = sql_fetch($req)) {
		$b = array(
		'id' => $t['id_me'],
		'title' => ($t['statut'] == 'publi')
			? seenthis_titre_me($t['texte'])
			: '',
		'uri' => 'http://' . _HOST . '/messages/'.$t['id_me'],
		'summary' => '@'.$t['login'].': '.$t['texte'],
		'date' => strtotime($t['date']),
		'content' => ($t['statut'] == 'publi')
			? $t['texte'] . "\n\n----\n\n".$t['rept']
			: '',
		'properties' => array(
			'objet' => 'me',
			'id_objet' => $t['id_me'],
			'date' => $t['date'],
			'auteurs' => array_values(array_unique(array_map('intval',
				array_merge(array($t['id_auteur']), explode(',',$t['rauteurs']))
			))),
			'authors' => array_values(array_unique(array_filter(array_map('strval',
				array_merge(array($t['login']), explode(',',$t['rlogins']))
			)))),
			'id_auteur' => intval($t['id_auteur']),
			'login' => strval($t['login']),
			'share' => array_values(array_unique(array_filter(array_map('intval',
				array_merge(array($t['id_auteur']),explode(',',$t['share'])))))),
			'published' => intval($t['statut'] == 'publi'),
			),
		'signature' => '',
		);

		// gestion des tags
		$censure = array('#for', '#via_google_reader');
		$tags = array('tags' => array(), 'oc' => array(), 'url' => array());
		foreach( array_values(array_unique(array_filter(
			array_map('mb_strtolower',array_map('strval',
				array_merge(explode(' | ',$t['tags']), explode(' | ',$t['rtags']))))))) as $m ) {
			if (in_array($m, $censure)) {}
			else if ($m[0] == '#') $tags['tags'][] = $m;
			else if (preg_match(',^https?://,i', $m)) $tags['url'][] = $m;
			else if (preg_match(',^(.+):(.+),', $m)) $tags['oc'][] = $m;
		}

		#if (defined('_CLI_') AND _CLI_) var_dump($tags);
		foreach($tags as $k => &$v) {
			if (count($v))
				$b['properties'][$k] = $v;
		}

		// normaliser les liens, on ne veut pas les indexer dans le fulltext
		if (function_exists('seenthissphinx_normaliser_url')) {
			foreach(array('title','content','summary') as $k)
				$b[$k] = preg_replace_callback("/"._REG_URL."/uiS", 'seenthissphinx_normaliser_url', $b[$k]);
		}

		$b['properties'] = json_encode($b['properties']);


		$c = array_map('sphinxql_escape_query', $b);

		$query = "REPLACE INTO "._SPHINXQL_INDEX
			   ." (id,title,uri, summary, date, content,properties,signature) VALUES ($c[id], $c[title], $c[uri], $c[summary], $c[date], $c[content], $c[properties], $c[signature])";
	
                spip_log($query,'sphinx');
	
		$a = sphinxql_query($query);
	
		if (defined('_CLI_') AND _CLI_) {
			echo $a ? "+" : "-",$b['title'],' ', $b['uri'],"\n";
			if (defined('_CLI_DEBUG') AND _CLI_DEBUG) echo $query,"\n";
		}

	}

}
