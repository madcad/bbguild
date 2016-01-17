<?php
/**
 * bbGuild ucp class file
 *
 * @package bbguild v2.0
 * @copyright 2016 bbDKP <https://github.com/bbDKP>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace bbdkp\bbguild\ucp;

use bbdkp\bbguild\model\admin\Admin;
use bbdkp\bbguild\model\player\Guilds;
use bbdkp\bbguild\model\player\Ranks;
use bbdkp\bbguild\model\player\Members;
use bbdkp\bbguild\model\games\rpg\Classes;
use bbdkp\bbguild\model\games\rpg\Faction;
use bbdkp\bbguild\model\games\rpg\Races;
use bbdkp\bbguild\model\games\rpg\Roles;

/**
 * Class bbguild_module
 * @package bbdkp\bbguild\acp
 */
class bbguild_module extends Admin
{
    /** @var string */
    public $u_action;

    /** @var int */
    protected $id;

    /** @var int */
    protected $mode;

    /** @var \phpbb\config\config */
    protected $config;
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var \phpbb\request\request */
    protected $request;
    /** @var \phpbb\symfony_request */
    protected $symfony_request;
    /** @var \phpbb\template\template */
    protected $template;
    /** @var \phpbb\user */
    protected $user;

    protected $module;
    protected $p_master;

    /**
     * Constructor
     */
    public function __construct($p_master)
    {
        global $db, $user, $auth, $template, $config, $phpbb_root_path, $phpEx, $pagination;
        global $phpbb_container, $request, $symfony_request, $module;
        $this->module   = $module;
        $this->p_master = $p_master;
        $this->phpEx = $phpEx;

        $this->config    = $config;
        $this->db        = $db;
        $this->user      = $user;
        $this->request   = $request;
        $this->symfony_request = $symfony_request;
        $this->template  = $template;
        $this->auth  = $auth;

        $this->pagination = $phpbb_container->get('pagination');

        parent::__construct();
    }

    /**
     * Entry point for module
     *
     * @param int $id     The id of the module.
     * @param int $mode   The mode of the module to enter.
     */
    public function main($id, $mode)
    {
        global $phpbb_root_path, $phpEx;

        $this->id = $id;
        $this->mode = $mode;

        // Attach the language files
        $this->user->add_lang(array('acp/groups', 'acp/common'));
        $guilds = new Guilds(0);

        // list all guild except noguild
        $guildlist = $guilds->guildlist(1);
        if(count($guildlist) == 0)
        {
            trigger_error('ERROR_NOGUILD', E_USER_WARNING );
        }
        $mode = ($mode == '' ? 'characters' :$mode);

        // GET processing logic
        $form_key = 'bbdkp/bbguild';
        add_form_key ( $form_key );

        switch ($this->mode)
        {
            case 'characters':
                /***
                 * ucp tab 1
                 * list of characters
                 */
                $this->link = '';
                $submit = $this->request->is_set_post('submit');
                $member = new Members();
                if ($submit)
                {
                    if (!check_form_key('bbdkp/bbguild'))
                    {
                        trigger_error('FORM_INVALID');
                    }
                    $member_id = (int) $this->request->variable('memberlist', 0);
                    $member->member_id = $member_id;
                    $member->Getmember();
                    $member->Claim_Member();
                    // Generate confirmation page. It will redirect back to the calling page
                    meta_refresh(2, $this->u_action);
                    $message = sprintf($this->user->lang['CHARACTERS_UPDATED'], $member->member_name) . '<br /><br />' . sprintf($this->user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
                    unset($member);
                    trigger_error($message);
                }

                $show_buttons = true;
                $s_guildmembers = ' ';
                //if user has no access to claiming chars, don't show the add button.
                if(!$this->auth->acl_get('u_charclaim'))
                {
                    $show_buttons = false;
                }

                if($member->has_reached_maxbbguildaccounts())
                {
                    $show_buttons = false;
                }

                //if there are no chars at all, do not show add button
                $sql = 'SELECT count(*) AS mcount FROM ' . MEMBER_LIST_TABLE .' WHERE member_rank_id < 90  ';
                $result = $this->db->sql_query($sql, 0);
                $mcount = (int) $this->db->sql_fetchfield('mcount');
                $show = true;
                if ($mcount == 0)
                {
                    $show = false;
                }

                if ($show)
                {

                    // list all characters bound to me
                    $this->listmychars();

                    // build popup for adding new chars to my phpbb account, get only those that are not assigned yet.
                    // note if someone picks a guildmember that does not belong to them then the guild admin can override this in acp

                    $member->listallmembers($guilds->guildid, true);

                    if(count($member->guildmemberlist ) > 0)
                    {
                        foreach ($member->guildmemberlist as $id => $m  )
                        {
                            $s_guildmembers .= '<option value="' . $m['member_id'] .'">'. $m['rank_name']  . ' ' . $m['member_name'] . '-' . $m['member_realm'] . '</option>';
                        }
                    }
                    else
                    {
                        $show_buttons = false;
                    }

                }
                $this->db->sql_freeresult ($result);
                unset($member);

                // These template variables are used on all the pages
                $this->template->assign_vars(array(
                        'S_DKPMEMBER_OPTIONS'	=> $s_guildmembers,
                        'S_SHOW'				=> $show,
                        'S_SHOW_BUTTONS'		=> $show_buttons,
                        'U_ACTION'  			=> $this->u_action,
                        'LA_ALERT_AJAX' 		=> $this->user->lang['ALERT_AJAX'] ,
                        'LA_ALERT_OLDBROWSER' 	=> $this->user->lang['ALERT_OLDBROWSER'] ,
                        'UA_MEMBERLIST'			=> append_sid("{$phpbb_root_path}styles/" . rawurlencode($this->user->theme['template_path']) . '/template/dkp/findmemberlist.'. $phpEx ),
                    )
                );


                // Dear phpbb, please display the templates for us.
                $this->tpl_name 	= 'ucp_bbguild';
                $this->page_title 	= $this->user->lang['UCP_DKP_CHARACTERS'];

                break;
            case 'characteradd':
                /**
                 *
                 * ucp tab 2
                 * character add/edit
                 *
                 */

                //get member_id if selected from pulldown
                $member_id =  $this->request->variable('hidden_member_id',  $this->request->variable(URI_NAMEID, 0));
                $submit	 = $this->request->is_set_post('add');
                $update	 = $this->request->is_set_post('update');
                $delete	 = $this->request->is_set_post('delete');
                if ( $submit || $update || $delete )
                {
                    if($delete)
                    {
                        // check if user can delete character
                        if(!$this->auth->acl_get('u_chardelete') )
                        {
                            trigger_error($this->user->lang['NOUCPDELCHARS']);
                        }

                        if (confirm_box(true))
                        {
                            $deletemember = new Members();
                            $deletemember->member_id = $this->request->variable('del_member_id', 0);
                            $deletemember->Getmember();
                            $deletemember->Deletemember();

                            $success_message = sprintf($this->user->lang['ADMIN_DELETE_MEMBERS_SUCCESS'], $deletemember->member_name);
                            trigger_error($success_message);
                        }
                        else
                        {
                            $deletemember = new Members();
                            $deletemember->member_id = $this->request->variable('member_id', 0);
                            $deletemember->Getmember();

                            $s_hidden_fields = build_hidden_fields(array(
                                    'delete'				=> true,
                                    'del_member_id'			=> $deletemember->member_id,
                                )
                            );

                            confirm_box(false, sprintf($this->user->lang['CONFIRM_DELETE_MEMBER'], $deletemember->member_name) , $s_hidden_fields);
                        }

                    }

                    if($submit)
                    {
                        // add character
                        if (!check_form_key('characteradd'))
                        {
                            trigger_error('FORM_INVALID');
                        }

                        $newmember = new Members();
                        if($newmember->has_reached_maxbbguildaccounts())
                        {
                            trigger_error(sprintf($this->user->lang['MAX_CHARS_EXCEEDED'],$this->config['bbguild_maxchars']) , E_USER_WARNING);
                        }

                        $newmember->game_id = $this->request->variable('game_id', '');
                        // get member name
                        $newmember->member_region = $this->request->variable('region_id', '');
                        $newmember->member_name = $this->request->variable('member_name', '', true);
                        $newmember->member_class_id = $this->request->variable('member_class_id', 1);
                        $newmember->member_race_id = $this->request->variable('member_race_id', 1);
                        $newmember->member_role = $this->request->variable('member_role', '');
                        $newmember->member_region = $this->request->variable('region_id', '');
                        $newmember->member_gender_id = $this->request->variable('gender', '0');
                        $newmember->member_title = $this->request->variable('member_title', '', true);
                        $newmember->member_realm = $this->request->variable('realm', '', true);
                        $newmember->member_guild_id = $this->request->variable('member_guild_id', 0);
                        $newmember->member_rank_id = $this->request->variable('member_rank_id', 99);
                        $newmember->member_level = $this->request->variable('member_level', 1);
                        $newmember->member_comment = $this->request->variable('member_comment', '', true);
                        $newmember->member_joindate = mktime(0, 0, 0, $this->request->variable('member_joindate_mo', 0), $this->request->variable('member_joindate_d', 0), $this->request->variable('member_joindate_y', 0));
                        $newmember->member_outdate = mktime ( 0, 0, 0, 12, 31, 2030 );
                        if ($this->request->variable('member_outdate_mo', 0) + $this->request->variable('member_outdate_d', 0) != 0)
                        {
                            $newmember->member_outdate = mktime(0, 0, 0, $this->request->variable('member_outdate_mo', 0), $this->request->variable('member_outdate_d', 0), $this->request->variable('member_outdate_y', 0));
                        }
                        $newmember->member_achiev = $this->request->variable('member_achiev', 0);
                        $newmember->member_armory_url = $this->request->variable('member_armorylink', '', true);
                        $newmember->phpbb_user_id = $this->user->data['user_id'];
                        $newmember->member_status = $this->request->variable('activated', 0) > 0 ? 1 : 0;
                        $newmember->Makemember();

                        if ($newmember->member_id > 0)
                        {
                            // record added.
                            $newmember->member_comment = sprintf($this->user->lang['ADMIN_ADD_MEMBER_SUCCESS'], ucwords($newmember->member_name), date("F j, Y, g:i a"));
                            $newmember->Armory_getmember();
                            $newmember->Updatemember($newmember);
                            meta_refresh(1, $this->u_action . '&amp;member_id=' . $newmember->member_id);
                            $success_message = sprintf($this->user->lang['ADMIN_ADD_MEMBER_SUCCESS'], ucwords($newmember->member_name), date("F j, Y, g:i a") );
                            trigger_error($success_message, E_USER_NOTICE);
                        }
                        else
                        {
                            meta_refresh(1, $this->u_action . '&amp;member_id=' . $newmember->member_id);
                            $failure_message = sprintf($this->user->lang['ADMIN_ADD_MEMBER_FAIL'], ucwords($newmember->member_name), $newmember->member_id);
                            trigger_error($failure_message, E_USER_WARNING);
                        }
                    }

                    if($update)
                    {
                        //update
                        if (!check_form_key('characteradd'))
                        {
                            trigger_error('FORM_INVALID');
                        }

                        // check if user can update character
                        if(!$this->auth->acl_get('u_charupdate') )
                        {
                            trigger_error($this->user->lang['NOUCPUPDCHARS']);
                        }
                        $updatemember = $this->UpdateMyCharacter($member_id);

                        meta_refresh(1, $this->u_action . '&amp;member_id=' . $updatemember->member_id);
                        //$success_message = sprintf($this->user->lang['ADMIN_UPDATE_MEMBER_SUCCESS'], ucwords($updatemember->member_name))  . '<br /><br />' . sprintf($this->user->lang['RETURN_UCP'], '<a href="' . $this->u_action . '">', '</a>');
                        //trigger_error($success_message, E_USER_NOTICE);

                    }
                }

                //template fill
                $this->fill_addmember($member_id, $guildlist);

                $this->template->assign_vars(array(
                    // javascript
                    'LA_ALERT_AJAX'		  => $this->user->lang['ALERT_AJAX'],
                    'LA_ALERT_OLDBROWSER' => $this->user->lang['ALERT_OLDBROWSER'],
                    'LA_MSG_NAME_EMPTY'	  => $this->user->lang['FV_REQUIRED_NAME'],
                    'UA_FINDGAMERANK'     => append_sid("{$phpbb_root_path}styles/" . rawurlencode($this->user->theme['template_path']) . '/template/dkp/findGameRank.'. $phpEx ),
                    'UA_FINDCLASSRACE'	  => append_sid("{$phpbb_root_path}styles/" . rawurlencode($this->user->theme['template_path']) . '/template/dkp/findclassrace.'. $phpEx ),
                ));
                $this->tpl_name 	= 'ucp_bbguild_charadd';
                break;
        }
    }


    /**
     * @param $member_id
     * @return Members
     */
    private function UpdateMyCharacter($member_id)
    {
        $updatemember = new Members();
        $updatemember->member_id = $member_id;
        $updatemember->Getmember();
        // get member name
        $updatemember->game_id          = $this->request->variable('game_id', '');
        $updatemember->member_race_id   = $this->request->variable('member_race_id', 0);
        $updatemember->member_class_id  = $this->request->variable('member_class_id', 0);
        $updatemember->member_role      = $this->request->variable('member_role', '');
        $updatemember->member_realm     = $this->request->variable('realm', '', true);
        $updatemember->member_region    = $this->request->variable('region_id', '');

        $updatemember->member_name      = $this->request->variable('member_name', '', true);
        $updatemember->member_gender_id = $this->request->variable('gender', '0');
        $updatemember->member_title     = $this->request->variable('member_title', '', true);
        $updatemember->member_guild_id  = $this->request->variable('member_guild_id', 0);
        $updatemember->member_rank_id   = $this->request->variable('member_rank_id', 99);
        $updatemember->member_level     = $this->request->variable('member_level', 0);

        $updatemember->member_achiev    = $this->request->variable('member_achiev', 0);
        $updatemember->member_comment   = $this->request->variable('member_comment', '', true);

        if ($updatemember->member_rank_id < 90)
        {
            $updatemember->Armory_getmember();
        }
        //override armory status
        $updatemember->member_status = $this->request->variable('activated', 0) > 0 ? 1 : 0;

        $oldmember = new Members();
        $oldmember->member_id = $updatemember->member_id;
        $oldmember->Getmember();
        $updatemember->Updatemember($oldmember);

        return $updatemember;
    }


    /**
     * shows add/edit character form
     *
     * @param int $member_id
     * @param array $guildlist
     */
    private function fill_addmember($member_id, $guildlist)
    {
        global $phpbb_root_path;
        $members = new Members();

        // Attach the language file
        $this->user->add_lang('mods/common');
        $this->user->add_lang(array('mods/admin'));
        $show=true;

        if($member_id == 0)
        {
            // check if user can add character
            if(!$this->auth->acl_get('u_charadd') )
            {
                trigger_error($this->user->lang['NOUCPADDCHARS']);
            }

            if(!$this->auth->acl_get('u_charclaim'))
            {
                trigger_error($this->user->lang['NOUCPADDCHARS']);
            }

            if($members->has_reached_maxbbguildaccounts())
            {
                $show=false;
                $this->template->assign_vars(array(
                    'MAX_CHARS_EXCEEDED' => sprintf($this->user->lang['MAX_CHARS_EXCEEDED'],$this->config['bbguild_maxchars']),
                ));

            }
            // set add mode
            $S_ADD = true;
        }
        else
        {
            $S_ADD = false;
            $members->member_id=$member_id;
            $members->Getmember();
        }


        foreach ($guildlist as $g)
        {
            //assign guild_id property
            if($members->member_guild_id == 0)
            {
                //if there is a default guild
                if($g['guilddefault'] == 1)
                {
                    $members->member_guild_id = $g['id'];
                }

                //if member count > 0
                if($members->member_guild_id == 0 && $g['membercount'] > 1)
                {
                    $members->member_guild_id = $g['id'];
                }

                //if guild id field > 0
                if($members->member_guild_id == 0 && $g['id'] > 0)
                {
                    $members->member_guild_id = $g['id'];
                }
            }

            //populate guild popup
            if($g['id'] > 0) // exclude guildless
            {
                $this->template->assign_block_vars('guild_row', array(
                    'VALUE' => $g['id'] ,
                    'SELECTED' => ($g['id'] == $members->member_guild_id ) ? ' selected="selected"' : '' ,
                    'OPTION' => (! empty($g['name'])) ? $g['name'] : '(None)'));
            }

            $guilds = new Guilds($members->member_guild_id);
            $gamename = $this->games[$guilds->game_id];

        }

        // Rank drop-down -> for initial load
        // reloading is done from ajax to prevent redraw
        $Ranks = new Ranks($members->member_guild_id);

        $result = $Ranks->listranks();

        while ($row = $this->db->sql_fetchrow($result))
        {
            $this->template->assign_block_vars('rank_row', array(
                'VALUE' => $row['rank_id'] ,
                'SELECTED' => ($members->member_rank_id == $row['rank_id']) ? ' selected="selected"' : '' ,
                'OPTION' => (! empty($row['rank_name'])) ? $row['rank_name'] : '(None)'));
        }


        //race dropdown
        $sql_array = array(
            'SELECT'	=>	'  r.race_id, l.name as race_name ',
            'FROM'		=> array(
                RACE_TABLE		=> 'r',
                BB_LANGUAGE		=> 'l',
            ),
            'WHERE'		=> " r.race_id = l.attribute_id
						AND r.game_id = '" . $guilds->game_id . "'
						AND l.attribute='race'
						AND l.game_id = r.game_id
						AND l.language= '" . $this->config['bbguild_lang'] ."'",
            'ORDER_BY'	=> 'l.name asc'
        );

        $sql = $this->db->sql_build_query('SELECT', $sql_array);

        $result = $this->db->sql_query($sql);

        if ($member_id > 0)
        {
            while ( $row = $this->db->sql_fetchrow($result) )
            {
                $this->template->assign_block_vars('race_row', array(
                        'VALUE' => $row['race_id'],
                        'SELECTED' => ( $members->member_race_id == $row['race_id'] ) ? ' selected="selected"' : '',
                        'OPTION'   => ( !empty($row['race_name']) ) ? $row['race_name'] : '(None)')
                );
            }

        }
        else
        {
            while ( $row = $this->db->sql_fetchrow($result) )
            {
                $this->template->assign_block_vars('race_row', array(
                        'VALUE' => $row['race_id'],
                        'SELECTED' =>  '',
                        'OPTION'   => ( !empty($row['race_name']) ) ? $row['race_name'] : '(None)')
                );
            }
        }

        // Class dropdown
        // reloading is done from ajax to prevent redraw
        $sql_array = array(
            'SELECT'	=>	' c.class_id, l.name as class_name, c.class_hide,
							  c.class_min_level, class_max_level, c.class_armor_type , c.imagename ',
            'FROM'		=> array(
                CLASS_TABLE		=> 'c',
                BB_LANGUAGE		=> 'l',
            ),
            'WHERE'		=> " l.game_id = c.game_id  AND c.game_id = '" . $guilds->game_id . "'
			AND l.attribute_id = c.class_id  AND l.language= '" . $this->config['bbguild_lang'] . "' AND l.attribute = 'class' ",
            'ORDER_BY'	=> 'l.name asc'
        );

        $sql = $this->db->sql_build_query('SELECT', $sql_array);

        $result = $this->db->sql_query($sql);
        while ( $row = $this->db->sql_fetchrow($result) )
        {
            if ( $row['class_min_level'] <= 1  )
            {
                $option = ( !empty($row['class_name']) ) ? $row['class_name'] . "
				 Level (". $row['class_min_level'] . " - ".$row['class_max_level'].")" : '(None)';
            }
            else
            {
                $option = ( !empty($row['class_name']) ) ? $row['class_name'] . "
				 Level ". $row['class_min_level'] . "+" : '(None)';
            }

            if ($member_id > 0)
            {
                $this->template->assign_block_vars('class_row', array(
                    'VALUE' => $row['class_id'],
                    'SELECTED' => ( $members->member_class_id == $row['class_id'] ) ? ' selected="selected"' : '',
                    'OPTION'   => $option ));

            }
            else
            {
                $this->template->assign_block_vars('class_row', array(
                    'VALUE' => $row['class_id'],
                    'SELECTED' => '',
                    'OPTION'   => $option ));
            }

        }
        $this->db->sql_freeresult($result);

        //Role dropdown
        $Roles = new Roles();
        $Roles->game_id = $guilds->game_id;
        $Roles->guild_id = $members->member_guild_id;
        $listroles = $Roles->listroles();
        foreach($listroles as $roleid => $Role )
        {
            $this->template->assign_block_vars('role_row', array(
                'VALUE' => $Role['role_id'] ,
                'SELECTED' => ($members->member_role == $Role['role_id']) ? ' selected="selected"' : '' ,
                'OPTION' => $Role['rolename'] ));
        }

        // build presets for joindate pulldowns
        $now = getdate();
        $s_memberjoin_day_options = '<option value="0"	>--</option>';
        for ($i = 1; $i < 32; $i++)
        {
            $day = isset($members->member_joindate_d) ? $members->member_joindate_d : $now['mday'] ;
            $selected = ($i == $day ) ? ' selected="selected"' : '';
            $s_memberjoin_day_options .= "<option value=\"$i\"$selected>$i</option>";
        }

        $s_memberjoin_month_options = '<option value="0">--</option>';
        for ($i = 1; $i < 13; $i++)
        {
            $month = isset($members->member_joindate_mo) ? $members->member_joindate_mo : $now['mon'] ;
            $selected = ($i == $month ) ? ' selected="selected"' : '';
            $s_memberjoin_month_options .= " <option value=\"$i\"$selected>$i</option>";
        }

        $s_memberjoin_year_options = '<option value="0">--</option>';
        for ($i = $now['year'] - 10; $i <= $now['year']; $i++)
        {
            $yr = isset($members->member_joindate_y) ? $members->member_joindate_y : $now['year'] ;
            $selected = ($i == $yr ) ? ' selected="selected"' : '';
            $s_memberjoin_year_options .= "<option value=\"$i\"$selected>$i</option>";
        }

        // build presets for outdate pulldowns
        $s_memberout_day_options = '<option value="0"' . ($members->member_id > 0 ? (($members->member_outdate != 0) ? '' : ' selected="selected"') : ' selected="selected"') . '>--</option>';
        for ($i = 1; $i < 32; $i++)
        {
            if ($members->member_id > 0 && $members->member_outdate != 0)
            {
                $day      = $members->member_outdate_d;
                $selected = ($i == $day) ? ' selected="selected"' : '';
            } else
            {
                $selected = '';
            }
            $s_memberout_day_options .= "<option value=\"$i\"$selected>$i</option>";
        }
        $s_memberout_month_options = '<option value="0"' . ($members->member_id > 0 ? (($members->member_outdate != 0) ? '' : ' selected="selected"') : ' selected="selected"') . '>--</option>';
        for ($i = 1; $i < 13; $i++)
        {
            if ($members->member_id > 0 && $members->member_outdate != 0)
            {
                $month    = $members->member_outdate_mo;
                $selected = ($i == $month) ? ' selected="selected"' : '';
            } else
            {
                $selected = '';
            }
            $s_memberout_month_options .= "<option value=\"$i\"$selected>$i</option>";
        }
        $s_memberout_year_options = '<option value="0"' . ($members->member_id > 0 ? (($members->member_outdate != 0) ? '' : ' selected="selected"') : ' selected="selected"') . '>--</option>';
        for ($i = $now['year'] - 10; $i <= $now['year'] + 10; $i++)
        {
            if ($members->member_id > 0 && $members->member_outdate != 0)
            {
                $yr       = $members->member_outdate_y;
                $selected = ($i == $yr) ? ' selected="selected"' : '';
            } else
            {
                $selected = '';
            }
            $s_memberout_year_options .= "<option value=\"$i\"$selected>$i</option>";
        }


        // check if user can add character
        $S_UPDATE = true;
        if(!$this->auth->acl_get('u_charupdate') )
        {
            $S_UPDATE = false;
        }

        $S_DELETE = true;
        if(!$this->auth->acl_get('u_chardelete') )
        {
            $S_DELETE = false;
        }


        foreach ($this->regions as $key => $regionname)
        {
            $this->template->assign_block_vars('region_row', array(
                'VALUE' => $key ,
                'SELECTED' => ($members->member_region == $key) ? ' selected="selected"' : '' ,
                'OPTION' => (! empty($regionname)) ? $regionname : '(None)'));
        }


        $form_key = 'characteradd';
        add_form_key($form_key);


        $this->template->assign_vars(array(
            'GAME_ID'               => $guilds->game_id,
            'GAME'                  => $gamename,
            'STATUS'				=> ($members->member_status == 1) ? ' checked="checked"' : '',
            'MEMBER_NAME'			=> $members->member_name,
            'MEMBER_TITLE'			=> $members->member_title,
            'MEMBER_ID'				=> $members->member_id,
            'MEMBER_LEVEL'			=> $members->member_level,
            'MALE_CHECKED'			=> ($members->member_gender_id  == '0') ? ' checked="checked"' : '' ,
            'FEMALE_CHECKED'		=> ($members->member_gender_id  == '1') ? ' checked="checked"' : '' ,
            'MEMBER_COMMENT'		=> $members->member_comment,
            'REALM'                 => $members->member_realm,
            'S_CAN_HAVE_ARMORY'		=>  $members->game_id == 'wow' || $members->game_id == 'aion'  ? true : false,
            'MEMBER_URL'			=>  $members->member_armory_url,
            'MEMBER_PORTRAIT'		=>  $members->member_portrait_url,
            'S_MEMBER_PORTRAIT_EXISTS'  => strlen( $members->member_portrait_url ) > 1 ? true : false,
            'S_CAN_GENERATE_ARMORY'		=> $members->game_id == 'wow' ? true : false,
            'COLORCODE' 			=> $members->colorcode == '' ? '#254689' : $members->colorcode,

            'CLASS_IMAGE' 			=> $members->class_image,
            'S_CLASS_IMAGE_EXISTS' 	=> strlen($members->class_image) > 1 ? true : false,

            'RACE_IMAGE' 			=> $members->race_image,
            'S_RACE_IMAGE_EXISTS' 	=> strlen( $members->race_image) > 1 ? true : false ,

            'S_JOINDATE_DAY_OPTIONS'	=> $s_memberjoin_day_options,
            'S_JOINDATE_MONTH_OPTIONS'	=> $s_memberjoin_month_options,
            'S_JOINDATE_YEAR_OPTIONS'	=> $s_memberjoin_year_options,

            'S_OUTDATE_DAY_OPTIONS'    => $s_memberout_day_options,
            'S_OUTDATE_MONTH_OPTIONS'  => $s_memberout_month_options,
            'S_OUTDATE_YEAR_OPTIONS'   => $s_memberout_year_options,

            'S_SHOW' => $show,
            'S_ADD' => $S_ADD,
            'S_CANDELETE' => $S_DELETE,
            'S_CANUPDATE' => $S_UPDATE,
        ));

    }


    /**
     * lists all my characters
     *
     */
    private function listmychars()
    {

        global $phpbb_root_path, $phpEx;
        $members = new Members();

        $mycharacters = $members->getmemberlist(0, 0, false, false, '', '', 0, 0, 0, 0, 200, true, '', 1);

        $lines = 0;
        foreach ($mycharacters[0] as $char)
        {
            $this->template->assign_block_vars('members_row', array(
                'U_EDIT'		=> append_sid("{$phpbb_root_path}ucp.$phpEx", "i=dkp&amp;mode=characteradd&amp;". URI_NAMEID . '=' . $char['member_id']),
                'GAME'			=> $char['game_id'],
                'COLORCODE'		=> $char['colorcode'],
                'CLASS'			=> $char['class_name'],
                'NAME'			=> $char['member_name'],
                'RACE'			=> $char['race_name'],
                'GUILD'			=> $char['guildname'],
                'REALM'			=> $char['realm'],
                'REGION'		=> $char['region'],
                'RANK'			=> $char['member_rank'],
                'LEVEL'			=> $char['member_level'],
                'ARMORY'		=> $char['member_armory_url'],
                'PHPBBUID'		=> $char['username'],
                'PORTRAIT'		=> $char['member_portrait_url'],
                'ACHIEVPTS'		=> $char['member_achiev'],
                'CLASS_IMAGE' 	=> $char['class_image'],
                'RACE_IMAGE' 	=> $char['race_image'],
            ));


            $sql_array2 = array(
                'SELECT'    => ' d.dkpsys_id, d.dkpsys_name,
				SUM(m.member_earned + m.member_adjustment) AS ep,
			    SUM(m.member_spent - m.member_item_decay + ( ' . max(0, $this->config['bbguild_basegp']) . ')) AS gp,
     			SUM(m.member_earned + m.member_adjustment - m.member_spent + m.member_item_decay - ( ' . max(0, $this->config['bbguild_basegp']) . ') ) AS member_current,
				CASE WHEN SUM(m.member_spent - m.member_item_decay) <= 0
					THEN SUM(m.member_earned + m.member_adjustment)
					ELSE ROUND( SUM(m.member_earned + m.member_adjustment) /  SUM(' . max(0, $this->config['bbguild_basegp']) .' + m.member_spent - m.member_item_decay) ,2)
				END AS pr',
                'FROM'      => array(
                    MEMBER_DKP_TABLE 	=> 'm',
                    DKPSYS_TABLE 		=> 'd',
                    MEMBER_LIST_TABLE 	=> 'l',
                ),
                'WHERE'     => "l.member_id = m.member_id and l.member_status = 1 and m.member_dkpid = d.dkpsys_id and d.dkpsys_status='Y' and m.member_id = " . $char['member_id'],
                'GROUP_BY'  => " d.dkpsys_id, d.dkpsys_name " ,
                'ORDER_BY'	=> " d.dkpsys_name ",
            );

            $sql2 = $this->db->sql_build_query('SELECT', $sql_array2);
            $result = $this->db->sql_query($sql2);
            while ($row2 = $this->db->sql_fetchrow($result))
            {
                $this->template->assign_block_vars('members_row.row', array(
                        'DKPSYS'        => $row2['dkpsys_name'],
                        'U_VIEW_MEMBER' => append_sid("{$phpbb_root_path}dkp.$phpEx",
                            "page=member&amp;". URI_NAMEID . '=' . $char['member_id'] . '&amp;' . URI_DKPSYS . '= ' . $row2['dkpsys_id'] ),
                        'EARNED'       => $row2['ep'],
                        'SPENT'        => $row2['gp'],
                        'PR'           => $row2['pr'],
                        'CURRENT'      => $row2['member_current'],
                    )
                );
            }
            $this->db->sql_freeresult ($result);

        }
        $this->template->assign_vars(array(
            'S_SHOWEPGP' 	=> ($this->config['bbguild_epgp'] == '1') ? true : false,
        ));

    }

}