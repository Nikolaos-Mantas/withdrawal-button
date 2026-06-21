<?php
/**
 * Compile .po translation files to .mo (GNU gettext format).
 *
 * Usage: php bin/compile-translations.php
 */

$root = dirname( __DIR__ );
$autoload = $root . '/vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
 fwrite( STDERR, "Run composer install first.\n" );
 exit( 1 );
}

require $autoload;

use Gettext\Generator\MoGenerator;
use Gettext\Loader\PoLoader;

$languages_dir = $root . '/languages';
$po_files      = glob( $languages_dir . '/*.po' );

if ( ! $po_files ) {
 fwrite( STDERR, "No .po files found in languages/.\n" );
 exit( 0 );
}

$loader   = new PoLoader();
$generator = new MoGenerator();
$compiled = 0;

foreach ( $po_files as $po_file ) {
	$mo_file = preg_replace( '/\.po$/', '.mo', $po_file );
	$gettext = $loader->loadFile( $po_file );
	$generator->generateFile( $gettext, $mo_file );
	$compiled++;
	echo 'Compiled: ' . basename( $mo_file ) . "\n";
}

echo "Done. {$compiled} file(s) compiled.\n";
