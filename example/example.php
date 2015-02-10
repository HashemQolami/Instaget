<?php
/**
 * Instaget example
 * 
 * @author Hashem Qolami <hashem@qolami.com>
 * @copyright 2015 Hashem Qolami. Released under MIT license.
 */

require '../vendor/autoload.php';

use Hashem\Instaget\Instaget as Instaget;
use League\Flysystem\Filesystem;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\Local as Adapter;

# Our private Filesystem
$fs = new Filesystem(new Adapter(__DIR__.'/'), [
    'visibility' => AdapterInterface::VISIBILITY_PRIVATE
]);

$inst = new Instaget($fs, [
	'clientID'  => 'YOUR_CLIENT_ID',
	'username'  => 'USERNAME',
	'count'     => 6,       // user feed limit
	'imageType' => 'large', // size of the instagram shots
	'expTime'   => 3600     // expiration time limit in seconds
]);

# Get/Store the shots!
$shots = $inst->run();

foreach ($shots as $shot) {
	echo "<div style=\"float: left; margin: 8px;\"> <a href=\"$shot[link]\" target=\"_blank\"><img src=\"$shot[image]\" /></a></div>\n";
}