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

seenthis_indexer_tout( ) ;
#seenthis_indexer_un(14) ;
#seenthis_indexer_recent(2*3600);

echo spip_timer('indexer'),"\n";
