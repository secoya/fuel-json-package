<?php

Autoloader::add_core_namespace('JSON');

Autoloader::add_classes(array(
	'JSON\\JSON' => __DIR__.'/classes/json.php',
	'JSON\\JSONException' => __DIR__.'/classes/json.php'
));