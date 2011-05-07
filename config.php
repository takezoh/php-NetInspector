<?php
$default_settings = array(
	// 'cache_dir' => './cache',
	'updated_interval' => 2592000,
	'default_action' => 'allow',
);

$iana_server = array(
	'server_url' => 'http://ftp.apnic.net',
	'delegated_urls' => array(
		'apnic'   => '/stats/apnic/delegated-apnic-latest',
		'ripencc' => '/stats/ripe-ncc/delegated-ripencc-latest',
		'lacnic'  => '/stats/lacnic/delegated-lacnic-latest',
		'arin'    => '/stats/arin/delegated-arin-latest',
		'afrinic' => '/stats/afrinic/delegated-afrinic-latest',
		'iana'    => '/stats/iana/delegated-iana-latest',
	),
);

$cached_registries = array(
	'apnic',
	'ripencc',
	'lacnic',
	'arin',
	'afrinic'
);

$header_text = array();
?>
