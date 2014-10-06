<?php

if (isset($_SERVER['HTTP_HOST']))
  die('ce script ne fonctionne qu\'en ligne de commande');

define('_CLI_', true);

date_default_timezone_set('Europe/Paris');

@header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);

chdir(dirname(__FILE__));

chdir('../..');
require_once 'ecrire/inc_version.php';

spip_timer('indexer');

include_spip('indexer_sphinx');

$command = $argv[1];

if (in_array('debug', $argv)) define ('_CLI_DEBUG', true);

switch(true) {
	case $command == 'tout':
		seenthis_indexer_tout();
		break;
	case $id_me = intval($command):
		seenthis_indexer_un($id_me);
		break;
	case $command == 'recents':
	default:
		seenthis_indexer_recent(30*24*3600);
		break;
}

echo spip_timer('indexer'),"\n";
