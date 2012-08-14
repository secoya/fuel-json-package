<?php

Autoloader::add_core_namespace('Secoya\\JSON');

Autoloader::add_classes(array(
	'Secoya\\JSON\\JSON' => __DIR__.'/classes/json.php',
	'Secoya\\JSON\\JSONException' => __DIR__.'/classes/json.php'
));

Autoloader::alias_to_namespace('Secoya\\JSON\\JSON');