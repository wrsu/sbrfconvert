<?php
/**
 *	Sberbank report conversion API
 *
 *	Just simple API to convert generic Sberbank report to machine-readable
 *	array of operations data.
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

/**
 *	Sberbank-to-Array
 *
 *	This function converts generic Sberbank card report into
 *	simple easy to use data array
 *
 *	@param	$sbrf	Text version of Sberbank Report
 *	@param	$utf8	Convert comments in unicode
 *	@return	array	List of operations
 */
function sbrf2arr( $sbrf, $utf8 = false ) {

	// Mounth IDs inlcuding nulyabr
	$month = array_flip(array( 'ÍÓË', 'ßÍÂ', 'ÔÅÂ', 'ÌÀÐ', 'ÀÏÐ', 'ÌÀÉ', 'ÈÞÍ', 'ÈÞË', 'ÀÂÃ', 'ÑÅÍ', 'ÎÊÒ', 'ÍÎß', 'ÄÅÊ' ));

	$data = explode( "\n", $sbrf ); // Input lines array
	$res = array(); // Result data array
	$row = false; // Current processed row
	foreach ( $data as $d ) {
		// The day operation was made
		$day1 = (int) substr( $d, 20, 2 );
		$mon1 = (int) $month[substr( $d, 22, 3 )];

		// The day operation was processed
		$day2 = (int) substr( $d, 26, 2 );
		$mon2 = (int) $month[substr( $d, 28, 3 )];
		$yea2 = 2000 + (int) substr( $d, 31, 2 );

		// Operation comment and text before and after it
		$text = substr( $d, 41, 22 );
		$bftx = substr( $d, 20, 21 );
		$aftx = substr( $d, 64, 34 );

		// Operation currency, summs and direction
		$curr = substr( $d, 64, 3 );
		$sum1 = (float) trim(substr( $d, 68, 15 ));
		$sum2 = (float) trim(substr( $d, 84, 11 ));
		$plus = ( substr( $d, 95, 2 ) == 'CR' ) ? true : false;

		// Operation ID
		$opid = (int) substr( $d, 34, 6 );

		// Check current line type
		if ( $bftx == '                     ' && $aftx == '                                  ' && $text != '                     ' ) {
			// Long operation comments have spaces before and after them
			// If we are working with operation now, add this comment
			if ( $row !== false ) $row['text'] .= $text;

		} elseif ( $day1 && $mon1 && $day2 && $mon2 && $yea2 && $text && $curr ) {

			// Add already finished operation			if ( $row !== false ) {				$row['text'] = trim(preg_replace( '#\s+#i', ' ', $row['text'] ));
				if ( $utf8 ) $row['text'] = iconv( 'windows-1251', 'UTF-8', $row['text'] );
				$res[] = $row;
			}

			// Simple hack - date is OK only for lines with real operations
			if (!checkdate( $mon2, $day2, $yea2 )) continue;
			$dat2 = sprintf( "%02d.%02d.%04d", $day2, $mon2, $yea2 );

			// New year operations processing
			$yea1 = ( $mon1 > $mon2 ) ? $yea2 - 1 : $yea2;
			$dat1 = sprintf( "%02d.%02d.%04d", $day1, $mon1, $yea1 );

			// Create new operation
			$row = array(
				'date'	=> $dat1,	// Date of operation
				'done'	=> $dat2,	// Date of processing
				'opid'	=> $opid,	// Operation ID
				'summ'	=> $sum1,	// Operation summ
				'curr'	=> $curr,	// Operation currency
				'total'	=> $plus ? $sum2 : -$sum2,	// Total amount
				'text'	=> $text,	// Comment
			);

		} elseif ( $row !== false ) {
			// On the bad lines - simply save the operation			$row['text'] = trim(preg_replace( '#\s+#i', ' ', $row['text'] ));
			if ( $utf8 ) $row['text'] = iconv( 'windows-1251', 'UTF-8', $row['text'] );
			$res[] = $row;
			$row = false;

		}

	}

	// Never forget to check the last one, just in case ...
	if ( $row !== false ) {
		$row['text'] = trim(preg_replace( '#\s+#i', ' ', $row['text'] ));
		if ( $utf8 ) $row['text'] = iconv( 'windows-1251', 'UTF-8', $row['text'] );
		$res[] = $row;
	}

	// All done!
	return $res;

}

/**
 *	Convert sberbank report to specific format
 *
 *	@param	$sbrf	Raw report data
 *  @param	$type	One of formats: json (default), csv, tsv
 *	@param	$utf8	Output result in UTF-8 instead of WIN-1251
 *	@return	string	Report in desired format
 */
function sbrfconvert( $sbrf, $type = 'json', $utf8 = false ) {
	// Prepare data to work
	$data = sbrf2arr( $sbrf, $utf8 );
	if (!count( $data )) return false;

	// Check the format
	if ( $type == 'csv' || $type == 'tsv' ) {
		// Table elements
		if  ( $type == 'csv' ) {			$start = '"';			// First element
			$glue1 = "\";\r\n\"";	// Glue between lines
			$glue2 = '";"';			// Glue between elements
			$finish = '";';			// Last element
		} else {			$start = '';
			$glue1 = "\r\n";
			$glue2 = "\t";
			$finish = '';
		}

		// Create table and it's headline from keys of the first element
		$table = array();
		$heads = array_keys( $data[0] );
		$table[] = implode( $glue2, $heads );

		// Fill the body with elements
		foreach ( $data as &$d ) {

		    // Russian Excel does not support . in floats
		    $d['summ'] = strtr( (string) $d['summ'], '.', ',' );
		    $d['total'] = strtr( (string) $d['total'], '.', ',' );
			// Add the row
			$table[] = implode( $glue2, $d );

		} unset ( $d );

		// Make the final result
		return $start . implode( $glue1, $table ) . $finish;

	} else {
		// Using JSON format with simple unicode if possible
		if ( $utf8 && defined('JSON_UNESCAPED_UNICODE') ) {			return json_encode( $data, JSON_UNESCAPED_UNICODE );
		} else return json_encode( $data );

	}

}