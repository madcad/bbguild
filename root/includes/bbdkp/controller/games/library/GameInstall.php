<?php
/**
 * abstract class aGameInstall
 *
 * @link http://www.bbdkp.com
 * @author Sajaki@gmail.com
 * @copyright 2013 bbdkp
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version 1.3.0
 */
namespace bbdkp\controller\games;
use bbdkp\controller\games;
/**
 * @ignore
 */
if (! defined('IN_PHPBB'))
{
	exit();
}
/**
 * Game interface
 * this abstract class is the framework for all game installers
 *
 */
abstract class GameInstall
{

	private $game_id;
	private $gamename;

	/**
	 * Install a game
     * can be implemented, this is the default install
	 */
    public final function Install($game_id, $gamename)
	{
		global $db;
		$this->game_id = $game_id;
		$this->gamename = $gamename;
		$db->sql_transaction ( 'begin' );
		$this->Installfactions();
		$this->InstallClasses();
		$this->InstallRaces();
		$this->InstallEventGroup();

		//insert a new entry in the game table
		$data = array (
				'game_id' => $this->game_id,
				'game_name' => $this->gamename,
				'imagename' => '',
				'armory_enabled' => ($this->game_id == 'wow' ? 1 : 0),
				'status' => 1
		);

		$sql = 'INSERT INTO ' . GAMES_TABLE . ' ' . $db->sql_build_array ( 'INSERT', $data );
		$db->sql_query ( $sql );

		$db->sql_transaction ( 'commit' );

	}


    Public final function Uninstall($game_id, $gamename)
    {
        global $cache, $db;
        $this->game_id = $game_id;
        $this->gamename = $gamename;

        $db->sql_transaction ( 'begin' );

        $factions = new \bbdkp\controller\games\Faction();
        $factions->game_id = $this->game_id;
        $factions->Delete_all_factions();

        $races = new \bbdkp\controller\games\Races();
        $races->game_id = $this->game_id;
        $races->Delete_all_races();

        $classes = new \bbdkp\controller\games\Classes();
        $classes->game_id = $this->game_id;
        $classes->Delete_all_classes();

        $sql = 'DELETE FROM ' . GAMES_TABLE . " WHERE game_id = '" .   $this->game_id . "'";
        $db->sql_query ($sql);

        $db->sql_transaction ( 'commit' );

        $cache->destroy ( 'sql', GAMES_TABLE );



    }



	/**
	 * Installs factions
	 * must be implemented
	 */
    abstract protected function Installfactions();

	/**
	 * Installs game classes
	 * must be implemented
	*/
    abstract protected function InstallClasses();

	/**
	 * Installs races
	 * must be implemented
	*/
    abstract protected function InstallRaces();

	/**
	 * Install sample Event Groups
	 * an Event answers the 'what' question
     * must be implemented
	 */
    abstract protected function InstallEventGroup();


}

?>