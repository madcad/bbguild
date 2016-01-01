<?php
/**
 * bbdkp ucp language file 
 *
 * @package phpBB Extension - bbdkp
 * @copyright 2009 bbdkp <https://github.com/bbDKP>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 * @author sajaki <sajaki@gmail.com>
 * @link http://www.bbdkp.com
 * @version 2.0
 * */

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// Create the lang array if it does not already exist
if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// Merge the following language entries into the lang array
$lang = array_merge($lang, array(
    'UCP_DKP_CHARACTERS'		=> 'Characters',
	'UCP_DKP'			=> 'bbDKP',  
	'UCP_DKP_CHARACTER_LIST'	=> 'My Characters',
	'UCP_DKP_CHARACTER_ADD'		=> 'Add Character'

));