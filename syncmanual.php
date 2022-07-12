<?php

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");

exec('php ' . dol_buildpath('/ecommerceng/scripts/syncronize_to_dolibarr.php', 0) . ' ' . GETPOST('login', 'alphanohtml') . ' ' . GETPOST('site_id', 'int') . ' ' . GETPOST('type', 'alphanohtml') . ' ' . GETPOST('id_from', 'int') . ' ' . GETPOST('id_to', 'int') . ' >> ' . DOL_DATA_ROOT . '/manualsync.log & echo $!;');

$db->close();
