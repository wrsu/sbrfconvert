<?php
/**
 *	Sberbank report conversion interface
 *
 *	This program is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 2 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	@author	Anton AlterVision Reznichenko <altervision13@gmail.com>
 *	@version	0.1
 */

// Data to convert
if ( isset( $_POST['data'] ) && !empty( $_POST['data'] ) ) {	// Input will be taken from post data
	$in = stripslashes( $_POST['data'] );
} elseif( isset( $_FILES['report'] ) && !empty( $_FILES['report']['name'] ) ) {	// Input will be taken from the file
	$in = file_get_contents( $_FILES['report']['tmp_name'] );
} else die( 'oops!' ); // Somethig went wrong

// Conversion parameters
$type = isset( $_POST['type'] ) ? $_POST['type'] : ( isset( $_GET['type'] ) ? $_GET['type'] : 'json' );
$utf8 = isset( $_POST['utf8'] ) ? $_POST['utf8'] : ( isset( $_GET['utf8'] ) ? $_GET['utf8'] : false );
$utf8 = $utf8 ? true : false; // Feel the zen ...

// Output the result
require_once 'sberbank.php';
switch ( $type ) {
  case 'csv':
	header( 'Content-type: text/csv; charset='.( $utf8 ? 'UTF-8' : 'Windows-1251' ) );
	header( 'Content-disposition: attachment; filename=sberbank.csv' );
	echo sbrfconvert( $in, 'csv', $utf8 );
  	break;

  case 'tsv':
	header( 'Content-type: text/tab-separated-values; charset='.( $utf8 ? 'UTF-8' : 'Windows-1251' ) );
	header( 'Content-disposition: attachment; filename=sberbank.tsv' );
	echo sbrfconvert( $in, 'tsv', $utf8 );
  	break;

  case 'json':
  default:
	header( 'Content-type: application/json' );
	echo sbrfconvert( $in, 'json', $utf8 );

}
