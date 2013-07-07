<?php
/**
 * 
* @author Ippehe, Sajaki
* @version 1.3.0
* @copyright (c) 2009 bbdkp https://github.com/bbDKP
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
* @package acp
*/


/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}


/**
* @package acp
*/
class acp_dkp_raid_info
{
	function module()
	{
		return array(
			'filename'	=> 'acp_dkp_raid',
			'title'		=> 'ACP_DKP_RAIDS',
			'version'	=> '1.3.0',
			'modes'		=> array(
				'addraid'		=> array('title' => 'ACP_DKP_RAID_ADD', 'auth' => 'acl_a_dkp', 'cat' => array('ACP_DKP_RAIDS') , 'display' => false),
				'editraid'		=> array('title' => 'ACP_DKP_RAID_EDIT', 'auth' => 'acl_a_dkp', 'cat' => array('ACP_DKP_RAIDS') , 'display' => false),
				'listraids'		=> array('title' => 'ACP_DKP_RAID_LIST', 'auth' => 'acl_a_dkp', 'cat' => array('ACP_DKP_RAIDS')),
				),
		);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>
