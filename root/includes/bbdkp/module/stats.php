<?php
/**
 * statistics page
 * 
 * @package bbDKP
 * @copyright 2009 bbdkp <http://code.google.com/p/bbdkp/>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version $Id$
 * 
 */
/**
 * @ignore
 */
if (!defined('IN_PHPBB'))
{
   exit;
}


/* begin dkpsys pulldown */
// pulldown
$query_by_pool = false;
$defaultpool = 99; 

$dkpvalues[0] = $user->lang['ALL']; 
$dkpvalues[1] = '--------'; 
// find only pools with dkp records
$sql_array = array(
	'SELECT'    => 'a.dkpsys_id, a.dkpsys_name, a.dkpsys_default', 
	'FROM'		=> array( 
				DKPSYS_TABLE => 'a', 
				MEMBER_DKP_TABLE => 'd',
				), 
	'WHERE'  => ' a.dkpsys_id = d.member_dkpid', 
	'GROUP_BY'  => 'a.dkpsys_id'
); 
$sql = $db->sql_build_query('SELECT', $sql_array);
$result = $db->sql_query ( $sql );
$index = 3;
while ( $row = $db->sql_fetchrow ( $result ) )
{
	$dkpvalues[$index]['id'] = $row ['dkpsys_id']; 
	$dkpvalues[$index]['text'] = $row ['dkpsys_name']; 
	if (strtoupper ( $row ['dkpsys_default'] ) == 'Y')
	{
		$defaultpool = $row ['dkpsys_id'];
	}
	$index +=1;
}
$db->sql_freeresult ( $result );

$dkp_id = 0; 
if(isset( $_POST ['pool']) or isset( $_POST ['getdksysid']) or isset ( $_GET [URI_DKPSYS] ) )
{
	if (isset( $_POST ['pool']) )
	{
		$pulldownval = request_var('pool',  $user->lang['ALL']);
		if(is_numeric($pulldownval))
		{
			$query_by_pool = true;
			$dkp_id = intval($pulldownval); 	
		}
	}
	elseif (isset ( $_GET [URI_DKPSYS] ))
	{
		$query_by_pool = true;
		$dkp_id = request_var(URI_DKPSYS, 0); 
	}
}
else 
{
	$query_by_pool = true;
	$dkp_id = $defaultpool; 
}

foreach ( $dkpvalues as $key => $value )
{
	if(!is_array($value))
	{
		$template->assign_block_vars ( 'pool_row', array (
			'VALUE' => $value, 
			'SELECTED' => ($value == $dkp_id && $value != '--------') ? ' selected="selected"' : '',
			'DISABLED' => ($value == '--------' ) ? ' disabled="disabled"' : '',  
			'OPTION' => $value, 
		));
	}
	else 
	{
		$template->assign_block_vars ( 'pool_row', array (
			'VALUE' => $value['id'], 
			'SELECTED' => ($dkp_id == $value['id']) ? ' selected="selected"' : '', 
			'OPTION' => $value['text'], 
		));
		
	}
}

$query_by_pool = ($dkp_id != 0) ? true : false;
/**** end dkpsys pulldown  ****/


/**** column sorting *****/
$sort_order = array(
     0 => array('member_raidcount desc', 'member_raidcount asc'),
     1 => array('member_name asc', 'member_name desc'),
     2 => array('member_firstraid asc', 'member_firstraid desc'),
     3 => array('member_lastraid asc', 'member_lastraid desc'),
     4 => array('ep desc', 'ep'),
     5 => array('ep_per_day desc', 'ep_per_day'),
     6 => array('ep_per_raid desc', 'ep_per_raid'),
     7 => array('gp desc', 'gp'),
     8 => array('gp_per_day desc', 'gp_per_day'),
     9 => array('gp_per_raid desc', 'gp_per_raid'),
    10 => array('pr desc', 'pr'),
    11 => array('member_current desc', 'member_current')
);

$current_order = switch_order($sort_order);
$sort_index = explode('.', $current_order['uri']['current']);
$previous_source = preg_replace('/( (asc|desc))?/i', '', $sort_order[$sort_index[0]][$sort_index[1]]);
$previous_data = '';

// get raidcount
$sql = 'SELECT count(*) as raidcount FROM ' . RAIDS_TABLE . ' r, ' . EVENTS_TABLE . ' e where r.event_id = e.event_id ';
if ($query_by_pool)
{
    $sql .= ' AND event_dkpid = '. $dkp_id; 
}
$result = $db->sql_query($sql);
$total_raids = (int) $db->sql_fetchfield('raidcount',0,$result);   
$db->sql_freeresult ( $result );

$show_all = ( (isset($_GET['show'])) && (request_var('show', '') == "all") ) ? true : false;

$sql_array = array(
    'SELECT'    => 	'l.member_name,	l.member_class_id,
		c1.name as classr_name, c.colorcode, 
		m.member_id, 
		m.member_dkpid, 
        m.member_firstraid, 
        m.member_lastraid, 
        m.member_raidcount,
		
        m.member_earned - m.member_raid_decay + m.member_adjustment AS ep,
        (m.member_earned - m.member_raid_decay + m.member_adjustment) / m.member_raidcount AS ep_per_raid,
        (m.member_earned - m.member_raid_decay + m.member_adjustment) / ((('.time().' - m.member_firstraid)+86400) / 86400)  AS ep_per_day,

        m.member_spent - m.member_item_decay + ( ' . max(0, $config['bbdkp_basegp']) . ') AS gp, 
        ( m.member_spent - m.member_item_decay + ( ' . max(0, $config['bbdkp_basegp']) . ') )  /m.member_raidcount AS gp_per_raid, 
        ( m.member_spent - m.member_item_decay + ( ' . max(0, $config['bbdkp_basegp']) . ') )   / ((('.time().' - m.member_firstraid)+86400) / 86400) AS gp_per_day,
        
        (m.member_earned - m.member_raid_decay + m.member_adjustment - m.member_spent + m.member_item_decay - ( ' . max(0, $config['bbdkp_basegp']) . ') ) AS member_current,

        case when m.member_spent - m.member_item_decay <= 0 
		then m.member_earned - m.member_raid_decay + m.member_adjustment  
		else round( (m.member_earned - m.member_raid_decay + m.member_adjustment) / (' . max(0, $config['bbdkp_basegp']) .' + m.member_spent - m.member_item_decay) ,2) end as pr , 
        
        (('.time().' - member_firstraid) / 86400) AS zero_check 
        
         ',

    'FROM'      => array(
        CLASS_TABLE 		=> 'c',
        MEMBER_DKP_TABLE 	=> 'm',
        MEMBER_LIST_TABLE 	=> 'l',
        BB_LANGUAGE			=> 'c1'
    	),
 
    'WHERE'     =>  "l.member_id=m.member_id 
        AND l.member_class_id = c.class_id and l.game_id = c.game_id 
    	AND c1.game_id=c.game_id and c1.attribute_id = c.class_id AND c1.language= '" . $config['bbdkp_lang'] . "' AND c1.attribute = 'class'" ,
    	
    	
    'ORDER_BY' => $current_order['sql'],
);

if ($query_by_pool)
{
	$sql_array['WHERE'] .= ' AND m.member_dkpid = ' . $dkp_id . ' ';
}

if ( ($config['bbdkp_hide_inactive'] == 1) && (!$show_all) )
{
    $sql_array['WHERE'] .= " AND m.member_status='1'";
}

$sql = $db->sql_build_query('SELECT', $sql_array);

if ( !($members_result = $db->sql_query($sql)) )
{
    trigger_error ($user->lang['MNOTFOUND']);
}

$member_count = 0;

$memberraidcount_g = array();
$memberattendancepct_g = array();
$membername_g = array();

while ( $row = $db->sql_fetchrow($members_result) )
{
	$member_count++;
	
	$colorcode = $row['colorcode'];
	
	// all time raid count from join date!
	$memberraidcount= raidcount (true, $dkp_id, 0, $row ['member_id'], 1, true);
	$memberraidcount_g[]= $memberraidcount;
	
    // Default the values of these in case they have no earned or spent or adjustment   
    $row['earned_per_day'] = ( ( (!empty($row['earned_per_day']) ) && ( $row['zero_check'] > 0.01) )) ? $row['earned_per_day'] : '0.00';
    $row['earned_per_raid'] = (!empty($row['earned_per_raid'])) ? $row['earned_per_raid'] : '0.00';
    
    $row['spent_per_day'] = ( ( (!empty($row['spent_per_day']) ) && ($row['zero_check'] > 0.01) )) ? $row['spent_per_day'] : '0.00';
    $row['spent_per_raid'] = (!empty($row['spent_per_raid'])) ? $row['spent_per_raid'] : '0';
    
    $row['er'] = (!empty($row['er'])) ? $row['er'] : '0.00';
	  
    // Find out how many days it's been since their first raid
    //$days_since_start = 0;
    //$days_since_start = round((time() - $row['member_firstraid']) / 86400);

    // Find the alltime percentage of raids they've been on
    $attended_percent = raidcount ( true,  $dkp_id, 0, $row ['member_id'], 2, true);
	$memberattendancepct_g[] = $attended_percent;
	
	$membername_g[]= $row['member_name'];

    $template->assign_block_vars('stats_row', array(
    	'COLORCODE'				=> $colorcode,
    	'ID'            		=> $row['member_id'],
	    'COUNT'         		=> ($row[$previous_source] == $previous_data) ? '&nbsp;' : $member_count,
        'U_VIEW_MEMBER' 		=> append_sid("{$phpbb_root_path}dkp.$phpEx" , 'page=viewmember&amp;' .URI_DKPSYS . '=' . $row['member_dkpid'] . '&amp;' . URI_NAMEID . '='.$row['member_id']),    
        'NAME' 					=> $row['member_name'],
        'FIRST_RAID' 			=> ( !empty($row['member_firstraid']) ) ? date($config['bbdkp_date_format'], $row['member_firstraid']) : '&nbsp;',
        'LAST_RAID' 			=> ( !empty($row['member_lastraid']) ) ? date($config['bbdkp_date_format'], $row['member_lastraid']) : '&nbsp;',
        'ATTENDED_COUNT' 		=> $memberraidcount,
        'C_ATTENDED_PERCENT' 	=> $attended_percent, true,
        'ATTENDED_PERCENT' 		=> $attended_percent,
        'EP_TOTAL' 				=> $row['ep'],
        'EP_PER_DAY' 			=> sprintf("%.2f", $row['ep_per_day']),
        'EP_PER_RAID' 			=> sprintf("%.2f", $row['ep_per_raid']),
        'GP_TOTAL' 				=> $row['gp'],
        'GP_PER_DAY' 			=> sprintf("%.2f", $row['gp_per_day']),
        'GP_PER_RAID' 			=> sprintf("%.2f", $row['gp_per_raid']),
        'PR'			 		=> sprintf("%.2f", $row['pr']),
        'C_CURRENT' 			=> $row['member_current'],
        'CURRENT' 				=> $row['member_current'], 
        'C_CURRENT'				=> ($row['member_current'] > 0 ? 'positive' : 'negative'), 
    )
    );

    $previous_data = $row[$previous_source];
}

if ( ($config['bbdkp_hide_inactive'] == 1) && (!$show_all) )
{
    $footcount_text = sprintf($user->lang['STATS_ACTIVE_FOOTCOUNT'], $db->sql_affectedrows($members_result),
    '<a href="' . append_sid("{$phpbb_root_path}dkp.$phpEx" , 'page=stats&amp;o='.$current_order['uri']['current']. '&amp;show=all' ) . '" class="rowfoot">');
}
else
{
    $footcount_text = sprintf($user->lang['STATS_FOOTCOUNT'], $db->sql_affectedrows($members_result));
}

$db->sql_freeresult($members_result);


/**
*  graph of attendance over number of raids
*
* */

// pChart library inclusions
include($phpbb_root_path . 'includes/bbdkp/pchart/class/pData.class.' . $phpEx);
include($phpbb_root_path . 'includes/bbdkp/pchart/class/pDraw.class.' . $phpEx);
include($phpbb_root_path . 'includes/bbdkp/pchart/class/pImage.class.' . $phpEx);
include($phpbb_root_path . 'includes/bbdkp/pchart/class/pScatter.class.' . $phpEx);
 
 unset($myPicture);
 unset($MyData); 
 // Create the pData object
 $myData = new pData();  

 // Create the X axis with the number of raids per member
 /*
 $MyData->addPoints($memberraidcount_g,"Raiders");
 
 
 $myData->setAxisName(0,"Raid Count");
 $myData->setAxisXY(0,AXIS_X);
 $myData->setAxisPosition(0,AXIS_POSITION_TOP);
 
 // Create the Y axis with the attendance percentages
 $MyData->addPoints(  $memberattendancepct_g ,"");
 $myData->setAxisName(1,"Attendance%");
 $myData->setAxisXY(1,AXIS_Y);
 $myData->setAxisPosition(1,AXIS_POSITION_LEFT);

 $MyData->setSerieDescription("Classes","Class");
 $MyData->setAbscissa("Classes"); 

 // Create the 1st scatter chart binding
 $myData->setScatterSerie("Probe 1","Probe 3",0);
 $myData->setScatterSerieDescription(0,"This year");
 $myData->setScatterSerieColor(0,array("R"=>0,"G"=>0,"B"=>0));

 // Create the 2nd scatter chart binding
 $myData->setScatterSerie("Probe 2","Probe 3",1);
 $myData->setScatterSerieDescription(1,"Last Year");

 // Create the pChart object
 $myPicture = new pImage(400,400,$myData);

 //Turn of Anti-aliasing
 $myPicture->Antialias = FALSE;

//Add a border to the picture
 $myPicture->drawRectangle(0,0,399,399,array("R"=>0,"G"=>0,"B"=>0));

 // Set the title font 
 $fonttitle = $phpbb_root_path . "includes/bbdkp/pchart/fonts/Forgotte.ttf";
 $myPicture->setFontProperties(array(
 	"FontName" => $fonttitle,
 	"FontSize" =>15));
 // draw the title
 //$myPicture->drawText(20,34,"Class participation vs. Class droprate",array("FontSize"=>20));

 //  Define the chart font  
 $chartfont = $phpbb_root_path . "includes/bbdkp/pchart/fonts/pf_arma_five.ttf"; 
 $myPicture->setFontProperties(array(
  	"FontName"=> $chartfont ,
  	"FontSize"=> 6));
 
 // Set the graph area 
 $myPicture->setGraphArea(40,40,370,370);

 // Create the Scatter chart object
 $myScatter = new pScatter($myPicture,$myData);

 // Draw the scale 
 $scaleSettings = array("XMargin"=>15,"YMargin"=>15,"Floating"=>TRUE,"GridR"=>200,"GridG"=>200,"GridB"=>200,"DrawSubTicks"=>TRUE,"CycleBackground"=>TRUE);
 $myScatter->drawScatterScale($scaleSettings);

 // Draw the legend 
 $myScatter->drawScatterLegend(280,380,array("Mode"=>LEGEND_HORIZONTAL,"Style"=>LEGEND_NOBORDER));

 // Draw a scatter plot chart 
 $myPicture->Antialias = TRUE;
 $myScatter->drawScatterPlotChart();

 // Render the picture (choose the best way)
 $myPicture->autoOutput("pictures/example.example.drawScatterBestFit.png"); 
*/



/***********************
 *  
 *  Class Statistics 
 *  
 **********************/

$classes = array();

// Find total # members with a dkp record
$sql = 'SELECT count(member_id) AS members FROM ' . MEMBER_DKP_TABLE ;
if ($query_by_pool)
{
    $sql .= ' where member_dkpid = '. $dkp_id . ' ';
}
$result = $db->sql_query($sql);
$total_members = (int) $db->sql_fetchfield('members');

// Find total # drops 
$sql_array = array (
	'SELECT' => ' count(item_id) AS items ', 
	'FROM' => array (
		EVENTS_TABLE => 'e', 
		RAIDS_TABLE => 'r', 
		RAID_ITEMS_TABLE => 'i', 
		), 
	'WHERE' => ' e.event_id = r.event_id 
			  AND i.raid_id = r.raid_id
			  AND item_value != 0'
);

if ($query_by_pool)
{
  $sql_array['WHERE'] .= ' and event_dkpid = '. (int) $dkp_id . ' ';
}

$sql = $db->sql_build_query ( 'SELECT', $sql_array );
$result = $db->sql_query($sql);
$total_drops = (int) $db->sql_fetchfield('items');
$db->sql_freeresult($result);

// get #classcount, #drops per class
$sql_array = array(
    'SELECT'    => 	'c1.name as class_name,  c.class_id , c.colorcode, 
    	c.imagename, count(m.member_id) AS class_count, count(i.item_id) as itemcount ', 
    'FROM'      => array(
       MEMBER_DKP_TABLE => 'm',
        CLASS_TABLE 		=> 'c',
        MEMBER_DKP_TABLE 	=> 'm',
        MEMBER_LIST_TABLE  	=> 'l',
        BB_LANGUAGE			=> 'c1'
    	),
    
    'LEFT_JOIN' => array(
        array(
            'FROM'  => array(RAID_ITEMS_TABLE => 'i'),
            'ON'    => 'm.member_id=i.member_id'
        )
    ),
    
    'WHERE'     =>  "m.member_id = l.member_id 
        AND l.member_class_id = c.class_id and l.game_id = c.game_id
    	AND c1.attribute_id = c.class_id 
    	AND c1.language= '" . $config['bbdkp_lang'] . "' 
    	AND c1.attribute = 'class' and c1.game_id = c.game_id " ,
    	
    'GROUP_BY' => ' c1.name, c.class_id,  c.colorcode, c.imagename ',	
     
);

if ($query_by_pool)
{
     $sql_array['WHERE'] .= ' AND m.member_dkpid = '. $dkp_id . ' ';
}
$sql = $db->sql_build_query('SELECT', $sql_array);

$result = $db->sql_query($sql);

$class_drop_pct_cum = 0;
$classname_g = array();
$class_drop_pct_g = array();
$classpct_g = array();

while ($row = $db->sql_fetchrow($result) )
{
	$classname_g[] = $row['class_name'];
	// get class count and pct
	$class_count = $row['class_count'];
	$classpct = (float) ($total_members > 0) ? round(($row['class_count'] / $total_members) * 100,1)  : 0;
	$classpct_g[] = $classpct;
	
	// get drops per class and pct
	$loot_drops = (int) $row['itemcount'];
    $class_drop_pct = (float) ( $total_drops > 0 ) ? round( ( (int) $row['itemcount'] / $total_drops) * 100, 1 ) : 0;
    $class_drop_pct_g[] = $class_drop_pct;
	$class_drop_pct_cum +=  $class_drop_pct;
			
    // class factor is the absolute ratio of #classdrops to #classcount
    // so it's the average droprate per class 
    $class_factor = ( $row['class_count'] > 0 ) ? round(( (int) $row['itemcount'] / $row['class_count']) * 100  ) : 0 ;
    //the loot factor is the ratio of class drops pct to class pct. 
    // this should be close to 100, meaning  that this class gets an even amount of loot.
    // if loot factor is > 100 then this class gets above proportional loot
	// if loot factor is < 100 then this class gets below proportional loot
    // positive interval is [60% to 140%], anything outside that is a serious inbalance.
    $loot_factor = ( $classpct > 0 ) ? round(  ( $class_drop_pct / $classpct),2  ) *100 : '0';

    if ($query_by_pool)
    {
        $lmlink =  append_sid("{$phpbb_root_path}dkp.$phpEx" , 'page=standings&amp;filter=class_' . $row['class_id'] . '&amp;' . URI_DKPSYS .'=' . $dkp_id); 
    }
    else 
    {
        $lmlink =  append_sid("{$phpbb_root_path}dkp.$phpEx" , 'page=standings&amp;filter=class_' . $row['class_id']);
    }
    
      //$total_drops += (int) $row['itemcount'];
       $template->assign_block_vars('class_row', array(
    	'U_LIST_MEMBERS' 	=> $lmlink ,
		'COLORCODE'  		=> ($row['colorcode'] == '') ? '#123456' : $row['colorcode'],
    	'CLASS_IMAGE' 		=> (strlen($row['imagename']) > 1) ? $phpbb_root_path . "images/class_images/" . $row['imagename'] . ".png" : '',  
		'S_CLASS_IMAGE_EXISTS' => (strlen($row['imagename']) > 1) ? true : false, 		
        'CLASS_NAME'		=> $row['class_name'],
		
        'CLASS_COUNT' 		=> (int) $class_count,
        'CLASS_PCT' 		=> sprintf("%s %%", $classpct ),
    
        'LOOT_COUNT' 		=> $loot_drops,
    	'CLASS_DROP_PCT'	=> sprintf("%s %%", $class_drop_pct  ),
    
    	'CLASS_FACTOR'		=> sprintf("%s %%", $class_factor),
    
    	'LOOT_FACTOR'		=> sprintf("%s %%", $loot_factor),
    	'C_LOOT_FACTOR'		=> ($loot_factor < 	60 || $loot_factor > 140 ) ? 'negative' : 'positive', 
    
		)
    );
}

/* chart generation */

/* CAT:Bar Chart */

 /* Create and populate the pData object */
 $MyData = new pData();  

 /* for each class, add point array */
 
 $MyData->addPoints($classpct_g,"Class%");
 $MyData->addPoints( $class_drop_pct_g,"Drop%");
 $MyData->setAxisName(0,"%");
 
 $MyData->addPoints(  $classname_g ,"Classes");
 $MyData->setSerieDescription("Classes","Class");
 $MyData->setAbscissa("Classes"); 

/* Create the pChart object */
 $myPicture = new pImage(500,400,$MyData);
 
 /* make a background gradient */
 //$myPicture->drawGradientArea(0,0,700,230,DIRECTION_VERTICAL,array("StartR"=>240,"StartG"=>240,"StartB"=>240,"EndR"=>180,"EndG"=>180,"EndB"=>180,"Alpha"=>100));
 //$myPicture->drawGradientArea(0,0,700,230,DIRECTION_HORIZONTAL,array("StartR"=>240,"StartG"=>240,"StartB"=>240,"EndR"=>180,"EndG"=>180,"EndB"=>180,"Alpha"=>20));
 
 // set the fonts
 $fonttitle = $phpbb_root_path . "includes/bbdkp/pchart/fonts/Forgotte.ttf";
 $myPicture->setFontProperties(array(
 	"FontName" => $fonttitle,
 	"FontSize" =>15));
 // draw the title
 //$myPicture->drawText(20,34,"Class participation vs. Class droprate",array("FontSize"=>20));

 /* Define the chart font */ 
 $chartfont = $phpbb_root_path . "includes/bbdkp/pchart/fonts/pf_arma_five.ttf"; 
 $myPicture->setFontProperties(array(
  	"FontName"=> $chartfont ,
  	"FontSize"=> 6));

/* Draw the scale  */
 $myPicture->setGraphArea(50,30,500,350);
 $myPicture->drawScale(array("CycleBackground"=>TRUE,"DrawSubTicks"=>TRUE,"GridR"=>0,"GridG"=>0,"GridB"=>0,"GridAlpha"=>10));

 /* Turn on shadow computing */ 
 $myPicture->setShadow(TRUE,array("X"=>1,"Y"=>1,"R"=>0,"G"=>0,"B"=>0,"Alpha"=>10));

 /* Draw the chart */
 $settings = array(
 	"Gradient"=>TRUE,
 	"DisplayPos"=>LABEL_POS_INSIDE,
 	"DisplayValues"=>TRUE,
 	"DisplayR"=>255,
 	"DisplayG"=>255,
 	"DisplayB"=>255,
 	"DisplayShadow"=>TRUE,
 	"Surrounding"=>5);
 $myPicture->drawBarChart($settings);

 /* Write the chart legend */
 $myPicture->drawLegend(100,12,array("Style"=>LEGEND_NOBORDER,"Mode"=>LEGEND_HORIZONTAL));
 
 /* Render the picture */
 $imagepath= $phpbb_root_path . "images/pchart/barchart". gen_rand_string_friendly(8) . ".png";
 $myPicture->render($imagepath);
 unset($myPicture);
 unset($MyData); 

$navlinks_array = array(
array(
 'DKPPAGE' => $user->lang['MENU_STATS'],
 'U_DKPPAGE' => append_sid("{$phpbb_root_path}stats.$phpEx"),
)); 

foreach( $navlinks_array as $name )
{
	 $template->assign_block_vars('dkpnavlinks', array(
	 'DKPPAGE' => $name['DKPPAGE'],
	 'U_DKPPAGE' => $name['U_DKPPAGE'],
	 ));
}

/* send information to template */
$template->assign_vars(array(
	'CHART1'   => $imagepath,
   	'S_DISPLAY_STATS'		=> true,
	'F_STATS' => append_sid("{$phpbb_root_path}stats.$phpEx"),
	
    'O_NAME'       => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][0] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')), 
    'O_FIRSTRAID' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][1] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) ,
	'O_LASTRAID' =>  append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][2] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')),
    'O_RAIDCOUNT' =>  append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][3] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) ,
    'O_EARNED' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][4] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) ,
    'O_EARNED_PER_DAY' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][5] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 
    'O_EARNED_PER_RAID' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][6] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 
    'O_SPENT' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][7] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 
    'O_SPENT_PER_DAY' =>append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][8] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 
    'O_SPENT_PER_RAID' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][9] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 
    'O_PR' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][10] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 
    'O_CURRENT' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats&amp;o=' . $current_order['uri'][11] . '&amp;' . URI_DKPSYS . '=' . ($query_by_pool ? $dkp_id : 'All')) , 

	'U_STATS' => append_sid("{$phpbb_root_path}dkp.$phpEx", 'page=stats'),
    'SHOW' => ( isset($_GET['show']) ) ? request_var('show', '') : '',
    'STATS_FOOTCOUNT' 	=> $footcount_text,
	'TOTAL_MEMBERS' 	=> $total_members, 
	'TOTAL_DROPS' 		=> $total_drops, 
	'CLASSPCTCUMUL'		=> round($class_drop_pct_cum), 

    )
);


$title = $user->lang['MENU_STATS'];

// Output page
page_header($title);

?>