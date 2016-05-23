<?php
/* 
OPT army match response system (OPT-MRS)

This plugin is based on:
MyLeagues by Filip Klar 2012 http://fklar.pl/tag/myleagues/ author: Filip Klar <kontakt@fklar.pl>
MyBB-Plugin-OPT-Armies by TerranUlm  https://github.com/TerranUlm/MyBB-Plugin-OPT-Armies author: Dieter Gobbers

License: The MIT License (MIT)
*/

/*
mybb pluginlibrary and myleagues Plugin are required to use this Plugin!

Following Tables should use Engine InnoDB:
    myleagues_leagues
    myleagues_matches
    armies
    users
    usergroups

pages:
    match_response_hide_notice
    called to hide the register to war notice
    misc.php?action=match_response_hide_notice

match_response
    display the form to join a war
    All upcoming wars for the league are diplayed
    misc.php?action=match_response (use the next war as reference)
    misc.php?action=match_response&lid=xx (use the league id as reference)

match_response_display
    displays the responses of the users
    misc.php?action=match_response_display (use the next war as reference)
    misc.php?action=match_response_display&mid=xx (use the match id as reference)


*/
// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

if (!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}


abstract class Response
{
    const No_Response = 0;
    const Unsure = 1;
    const Yes = 3;
    const No = 2;
}

abstract class Response_permission
{
    const No_Permission = 0;
    const All = 1;
    const Own_Army = 3;
    const No = 2;
}

class Responses_count
{
    public $No_Response = 0;
    public $Unsure = 0;
    public $Yes = 0;
    public $No = 0;
    
    function nul()
    {
        $this->No_Response = 0;
        $this->Unsure      = 0;
        $this->Yes         = 0;
        $this->No          = 0;
    }
    
    function add(Responses_count $class)
    {
        $this->No_Response += $class->No_Response;
        $this->Unsure += $class->Unsure;
        $this->Yes += $class->Yes;
        $this->No += $class->No;
    }
}
function opt_army_match_response_info()
{
    return array(
        "name" => "OPT Army MRS",
        "description" => "An OPT Match response System.",
        "website" => "http://opt-community.de/",
        "author" => "natter",
        "authorsite" => "http://opt-community.de/",
        "version" => "1.2.3",
        "guid" => "",
        "codename" => "",
        "compatibility" => "16*"
    );
}

function opt_army_match_response_install()
{
    global $db, $lang, $cache;
    
    if (!file_exists(PLUGINLIBRARY)) {
        flash_message("PluginLibrary is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
    
    global $PL;
    $PL or require_once PLUGINLIBRARY;
    
    if ($PL->version < 12) {
        flash_message("PluginLibrary is too old: " . $PL->version, "error");
        admin_redirect("index.php?module=config-plugins");
    }
    
    if (!$db->table_exists("myleagues_leagues")) {
        flash_message("myleagues is required for this Plugin", "error");
        admin_redirect("index.php?module=config-plugins");
    }
    
    $create_table_match_response = "CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "match_response` (
            `lid` int(10) NOT NULL COMMENT 'myleagues league ID',
            `mid` int(10) NOT NULL COMMENT 'myleagues match ID',
            `aid` int(11) NOT NULL COMMENT 'OPT Army ID',
            `uid` int(10) unsigned NOT NULL COMMENT 'MyBB user ID',
            `response` tinyint(1) unsigned NOT NULL DEFAULT '" . Response::No_Response . "' COMMENT '" . Response::No_Response . "=no response, " . Response::Unsure . "=unsure, " . Response::No . "=no, " . Response::Yes . "=yes',
            `comment` text COMMENT 'comment',
            `response_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT pk_response PRIMARY KEY (`mid`,`uid`),
            KEY `lid` (`lid`),
            KEY `aid` (`aid`)   
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='match response'";
    
    $db->query($create_table_match_response);
    
    $create_table_match_response_setting = "CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "match_response_setting` (
            `gid` smallint(5) unsigned NOT NULL COMMENT 'Group ID',
            `canuseresp` tinyint(1) unsigned NOT NULL COMMENT '1=Can use match response System',
            `view` text COMMENT 'can view this group',
            `viewsum` text COMMENT 'can view this group sum',
            `only` tinyint(1) unsigned NOT NULL COMMENT '1=Can only view his group',
            `special` tinyint(1) unsigned NOT NULL COMMENT 'for special display request(e.g. show all no response user)',
            PRIMARY KEY(`gid`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='match response setting'";
    
    $db->query($create_table_match_response_setting);
    
    
    $alter_table[TABLE_PREFIX . "myleagues_leagues"] = "ALTER TABLE `" . TABLE_PREFIX . "match_response`
            ADD CONSTRAINT `opt_mrs_lid_ibfk` FOREIGN KEY (`lid`) REFERENCES `" . TABLE_PREFIX . "myleagues_leagues` (`lid`) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE";
    $alter_table[TABLE_PREFIX . "myleagues_matches"] = "ALTER TABLE `" . TABLE_PREFIX . "match_response`       
            ADD CONSTRAINT `opt_mrs_mid_ibfk` FOREIGN KEY (`mid`) REFERENCES `" . TABLE_PREFIX . "myleagues_matches` (`mid`) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE";
    $alter_table[TABLE_PREFIX . "armies"]            = "ALTER TABLE `" . TABLE_PREFIX . "match_response`     
            ADD CONSTRAINT `opt_mrs_aid_ibfk` FOREIGN KEY (`aid`) REFERENCES `" . TABLE_PREFIX . "armies` (`aid`) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE";
    $alter_table[TABLE_PREFIX . "users"]             = "ALTER TABLE `" . TABLE_PREFIX . "match_response`   
            ADD CONSTRAINT `opt_mrs_uid_ibfk` FOREIGN KEY (`uid`) REFERENCES `" . TABLE_PREFIX . "users` (`uid`) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE";
    $alter_table[TABLE_PREFIX . "usergroups"]        = "ALTER TABLE `" . TABLE_PREFIX . "match_response_setting`  
           ADD CONSTRAINT `opt_mrs_uid_gid_ibfk` FOREIGN KEY (`gid`) REFERENCES `" . TABLE_PREFIX . "usergroups` (`gid`) 
                ON DELETE CASCADE 
                ON UPDATE CASCADE";
    
    $table_status = "show table STATUS WHERE 
    Name IN('" . TABLE_PREFIX . "myleagues_leagues','" . TABLE_PREFIX . "myleagues_matches',
    '" . TABLE_PREFIX . "armies','" . TABLE_PREFIX . "users','" . TABLE_PREFIX . "usergroups')";
    $query        = $db->query($table_status);
    
    while ($table_status = $db->fetch_array($query)) {
        if ($table_status['Engine'] == 'InnoDB')
            $db->query($alter_table[$table_status['Name']]);
    }
    
}

function opt_army_match_response_is_installed()
{
    global $db;
    
    if ($db->table_exists("match_response")) {
        return TRUE;
    } else {
        return FALSE;
    }
    
}

function opt_army_match_response_uninstall()
{
    global $db;
    global $PL;
    $PL or require_once PLUGINLIBRARY;
    
    $db->query("DROP TABLE `" . TABLE_PREFIX . "match_response`");
    $db->query("DROP TABLE `" . TABLE_PREFIX . "match_response_setting`");
    
}

function opt_army_match_response_activate()
{
    global $PL;
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    
    $PL or require_once PLUGINLIBRARY;
    opt_army_match_gesponse_setup_templates();
    opt_army_match_gesponse_setup_stylesheet();
    
    //add Field to display the war reminder
    find_replace_templatesets("header", "#(" . preg_quote("{\$pm_notice}") . ")#i", "$1{\$opt_mrs}");
}

function opt_army_match_response_deactivate()
{
    global $PL,$cache;
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";

    $PL or require_once PLUGINLIBRARY;
    $PL->templates_delete('optmatchresponse');
    $PL->stylesheet_delete('optmatchresponse');
    
    //remove Field to display the war reminder
    find_replace_templatesets("header", "#" . preg_quote("{\$opt_mrs}") . "#i", "", 0);
    $cache->update('opt_mrs_hide_msg', NULL);
}

if (defined("IN_ADMINCP")) {
    $plugins->add_hook('admin_config_permissions', 'opt_army_match_response_admin_permissions');
    $plugins->add_hook('admin_config_action_handler', 'opt_army_match_response_admin_config_action_handler');
    $plugins->add_hook('admin_config_menu', 'opt_army_match_response_admin_config_menu');
    $plugins->add_hook('admin_load', 'opt_army_match_response_admin_load');
}

$plugins->add_hook('global_start', 'opt_army_match_response_global_start');
$plugins->add_hook('global_end', 'opt_army_match_response_global_end');
$plugins->add_hook('misc_start', 'opt_army_match_response');

//set placeholder for the war reminder 
function opt_army_match_response_global_start()
{
    global $opt_mrs;
    $opt_mrs = '<!--opt_match_response_system-->';
}

//display the war reminder 
function opt_army_match_response_global_end()
{
    global $db, $mybb, $lang, $templates, $headerinclude, $header, $footer, $theme, $cache, $opt_mrs, $header;      
    //if not logged in return
    if(empty($mybb->user['uid']))
        return;
        
    $lang->load('opt_army_match_response');
    $uid = (int) $mybb->user['uid'];
    
    
    //do not display war reminder in match_response* pages
    if (strpos($mybb->input['action'], 'match_response') === false)
    {
        //if opt_mrs_hide_msg is set in cache
        if ($opt_mrs_hide_msg = $cache->read('opt_mrs_hide_msg')) {
            //delete expired match response hide
            foreach ($opt_mrs_hide_msg as $user_id => $settings) {
                if ($settings['expire'] <= time())
                    unset($opt_mrs_hide_msg[$user_id]);
            }
            $cache->update('opt_mrs_hide_msg', $opt_mrs_hide_msg);
            //if hide is active for this user return
            if (!empty($opt_mrs_hide_msg[$uid]['hide']))
                return;
        }
        $setting=opt_army_match_gesponse_get_settings($uid);
        //if user can use resposne system check for no response for next match
        if (!empty($setting['canuseresp'])) {
            
            //get next match 
            if($temp_match=opt_army_match_gesponse_get_next_myleages_match())
            {
                //if there is a next match    
                $mid        = $temp_match['mid'];
                $query = $db->simple_select("match_response", "*", "`mid` = '" . (int) $mid . "' AND `uid`='" . $uid . "'");
                //check for response if no response display notice
                if ($db->num_rows($query) == 0) {
                    $mrs_text = $lang->opt_army_match_remember;
                    $base_url = $mybb->settings['bburl'];
                    eval("\$opt_mrs = \"" . $templates->get("optmatchresponse_notice") . "\";");
                }
            }  
        }
    } 
    //replace placeholder in header wiith notice
    $header = str_replace("<!--opt_match_response_system-->", $opt_mrs, $header);  
}

//admin CP related
function opt_army_match_response_admin_permissions(&$admin_permissions)
{
    global $lang;
    $lang->load('opt_army_match_response');
    $admin_permissions['opt_army_match_response'] = $lang->opt_army_match_response_can_manage;
}
//admin CP related
function opt_army_match_response_admin_config_action_handler(&$action)
{
    $action['opt_army_match_response'] = array(
        'active' => 'opt_army_match_response'
    );
}
//admin CP related
function opt_army_match_response_admin_config_menu(&$submenu)
{
    global $lang;
    $lang->load('opt_army_match_response');
    $submenu[] = array(
        'id' => 'opt_army_match_response',
        'title' => $lang->opt_army_match_response_title,
        'link' => 'index.php?module=config-opt_army_match_response'
    );
}

//admin CP Setting Page
function opt_army_match_response_admin_load()
{
    global $lang, $mybb, $db, $page, $cache, $errors;
    if ($page->active_action != 'opt_army_match_response')
        return false;
    
    $query_armies = $db->simple_select('armies', '*', '', array(
        'order_by' => "displayorder",
        'order_dir' => "ASC"
    ));
    if(empty($db->num_rows($query_armies)))
        error('no army avaible','no army');
    
    $active_aid   = (int) $mybb->input['army'];
    //generate army tabs
    while ($army = $db->fetch_array($query_armies)) {
        if (empty($active_aid))
            $active_aid = (int) $army['aid'];
        $tabs['opt_mrs_army_' . $army['aid']] = array(
            'title' => $army['name'],
            'link' => 'index.php?module=config-opt_army_match_response&army=' . (int) $army['aid'],
            'description' => ''
        );
    }
    $db->free_result($query_armies);
    
    if (isset($mybb->input['submit'])) {
        if (verify_post_check($mybb->input['my_post_key'], false)) //Check Key and throw error if false
            {
            $settings = $mybb->input['setting'];
            if (is_array($settings)) {
                foreach ($settings as $gid => $setting) {
                    $setting_par = array();
                    
                    $setting_par['gid'] = (int) $gid;
                    
                    if (isset($setting['canuseresp']))
                        $setting_par['canuseresp'] = (int) $setting['canuseresp'];
                    else
                        $setting_par['canuseresp'] = 0;
                    if (isset($setting['view']))
                        $setting_par['view'] = $db->escape_string(implode(",", $setting['view']));
                    else
                        $setting_par['view'] = '';
                    if (isset($setting['viewsum']))
                        $setting_par['viewsum'] = $db->escape_string(implode(",", $setting['viewsum']));
                    else
                        $setting_par['viewsum'] = '';
                    if (isset($setting['only']))
                        $setting_par['only'] = (int) $setting['only'];
                    else
                        $setting_par['only'] = 0;  
                    if (isset($setting['special']))
                        $setting_par['special'] = (int) $setting['special'];
                    else
                        $setting_par['special'] = 0;
                        
                    $db->replace_query("match_response_setting", $setting_par);
                }
                flash_message($lang->opt_army_match_response_done, 'success');
            } else {
                flash_message('Nothing to do', 'error');
            }
        }
    }
    
    $lang->load('opt_army_match_response');
    $page->output_header($lang->opt_army_match_response_title);
    $page->output_nav_tabs($tabs, 'opt_mrs_army_' . $active_aid);
    $page->add_breadcrumb_item($lang->opt_army_match_response_title, 'index.php?module=config-opt_army_match_response');
    
    $form = new Form("index.php?module=config-opt_army_match_response&army=" . (int) $active_aid, "post");
    
    $form_container = new FormContainer($lang->opt_army_match_response_title);
    $form_container->output_row_header($lang->opt_army_match_response_group, array(
        "class" => "align_center",
        'style' => 'width: 40%'
    ));
    $form_container->output_row_header($lang->opt_army_match_response_permission, array(
        "class" => "align_center"
    ));
    
    
    $query_armies = $db->simple_select('armies', '*', 'aid=' . (int) $active_aid, array(
        'order_by' => "displayorder",
        'order_dir' => "ASC"
    ));
    while ($army = $db->fetch_array($query_armies)) {
        $depth = 0;
        if (!empty($army['HCO_gid']))
            opt_army_match_gesponse_admin_show_group($army['HCO_gid'], $rights, $form, $form_container, $depth, $army['gid']);
        if (!empty($army['CO_gid']))
            opt_army_match_gesponse_admin_show_group($army['CO_gid'], $rights, $form, $form_container, $depth, $army['gid']);
        
        if (!empty($army['uugid']))
            opt_army_match_gesponse_admin_show_group($army['uugid'], $rights, $form, $form_container, $depth, $army['gid']);
        $depth--;
        $query_groups = $db->simple_select('armies_structures', '*', 'pagrid IS NULL AND aid=' . $army['aid'], array(
            'order_by' => 'displayorder',
            'order_dir' => 'ASC'
        ));
        while ($group = $db->fetch_array($query_groups)) {
            opt_army_match_gesponse_admin_recursive_group($group['agrid'], $rights, $form, $form_container, $depth, $army['gid']);
        }
        $db->free_result($query_groups);
    }
    $db->free_result($query_armies);
    
    
    $form_container->end();
    $buttons[] = $form->generate_submit_button($lang->opt_army_match_response_submit, array(
        'name' => 'submit'
    ));
    $form->output_submit_wrapper($buttons);
    
    $page->output_footer();
}

//admin CP build the settings for the match response groups
function opt_army_match_gesponse_admin_recursive_group($agrid, $rights, Form &$form, FormContainer &$form_container, &$depth, $pagid)
{
    global $db, $templates, $lang, $theme, $cache;
    
    
    $query2 = $db->simple_select('armies_structures', '*', 'agrid=' . intval($agrid));
    $group2 = $db->fetch_array($query2);
    
    opt_army_match_gesponse_admin_show_group($group2['gid'], $rights, $form, $form_container, $depth + 1, $pagid);
    
    // find all sub groups of this group
    $query = $db->simple_select('armies_structures', '*', 'pagrid=' . intval($agrid), array(
        'order_by' => 'displayorder',
        'order_dir' => 'ASC'
    ));
    $depth++;
    while ($group = $db->fetch_array($query)) {
        opt_army_match_gesponse_admin_recursive_group($group['agrid'], $rights, $form, $form_container, $depth, $group2['gid']);
    }
    $depth--;
    $db->free_result($query);
    $db->free_result($query2);
    
}

//admin CP build the settings for the match response group
function opt_army_match_gesponse_admin_show_group($gid, $rights, Form &$form, FormContainer &$form_container, $depth, $pagid)
{
    global $db, $templates, $lang, $theme, $cache;
    
    $query   = $db->simple_select('match_response_setting', '*', 'gid=' . intval($gid), array(
        'LIMIT' => 1
    ));
    $setting = $db->fetch_array($query);
    $db->free_result($query);
    
    $groupscache  = $cache->read('usergroups');
    $query_armies = $db->simple_select('armies', '*', '', array(
        'order_by' => "displayorder",
        'order_dir' => "ASC"
    ));
    
    
    $form_container->output_cell($groupscache[$gid]['title'] . ' (' . $gid . ')', array(
        'style' => 'padding-left:' . ($depth * 5) . '%'
    ));
    $permissions = array();
    
    $permissions[] = $form->generate_check_box('setting[' . $gid . '][canuseresp]', 1, $lang->opt_army_match_response_can_use_response, array(
        'checked' => $setting['canuseresp'],
        'id' => 'canuseresp' . $gid . '_' . $groups2['gid']
    ));
    
    while ($groups2 = $db->fetch_array($query_armies)) {
        $permissions[] = $form->generate_check_box('setting[' . $gid . '][view][]', $groups2['gid'], $lang->opt_army_match_response_can_view_entire . ' ' . $groupscache[$groups2['gid']]['title'], array(
            'checked' => in_array($groups2['gid'], explode(',', $setting['view'])),
            'id' => 'view' . $gid . '_' . $groups2['gid']
        ));
        $permissions[] = $form->generate_check_box('setting[' . $gid . '][viewsum][]', $groups2['gid'], $lang->opt_army_match_response_can_view_summary . ' ' . $groupscache[$groups2['gid']]['title'], array(
            'checked' => in_array($groups2['gid'], explode(',', $setting['viewsum'])),
            'id' => 'viewsum' . $gid . '_' . $groups2['gid']
        ));
    }
    $permissions[] = $form->generate_check_box('setting[' . $gid . '][only]', 1, $lang->opt_army_match_response_can_only_his_group . ' (' . $groupscache[$gid]['title'] . ')', array(
        'checked' => $setting['only'],
        'id' => 'only' . $gid . '_' . $groups2['gid']
    ));
    $permissions[] = $form->generate_check_box('setting[' . $gid . '][special]', 1, $lang->opt_army_match_response_can_view_alle_no_resp_user, array(
        'checked' => $setting['special'],
        'id' => 'special' . $gid . '_' . $groups2['gid']
    ));
    
    $permissions[] = $form->generate_hidden_field('setting[' . $gid . '][hidden]', $gid);
    $form_container->output_cell("<div class=\"forum_settings_bit\">" . implode("</div><div class=\"forum_settings_bit\">", $permissions) . "</div>");
    $form_container->construct_row();
    
    
}



/*Response Display Page
actions:
match_response_hide_notice
match_response
match_response_display
*/
function opt_army_match_response()
{
    global $db, $mybb, $lang, $templates, $headerinclude, $header, $footer, $theme, $cache, $opt_mrs;
    $actions=array('match_response_hide_notice','match_response','match_response_display');
       
    //check if this function is responsible
    if(!in_array($mybb->input['action'],$actions))
        return; 
      
    //if not logged in show login message
    if(empty($mybb->user['uid']))
        error_no_permission();       
    require_once MYBB_ROOT . "inc/class_myleagues.php";
    $uid = (int) $mybb->user['uid'];
    
    $lang->load('opt_army_match_response');
    
    if ($mybb->input['action'] == 'match_response_hide_notice') {
        if (verify_post_check($mybb->input['my_post_key'], false)) {
            
            $setting=opt_army_match_gesponse_get_settings($uid);
            
            if (!empty($setting['canuseresp'])) {
                $opt_mrs_hide_msg = $cache->read('opt_mrs_hide_msg');
                if (!is_array($opt_mrs_hide_msg))
                    $opt_mrs_hide_msg = array();
                $expire_time = time() + 10 * 24 * 60 * 60; //default expire 10 days  
                $match       = opt_army_match_gesponse_get_next_myleages_match();
                if (!empty($match))
                    $expire_time = $match['dateline'] + 36 * 60 * 60; //set expire 1,5 days after match ends
                
                $opt_mrs_hide_msg[$uid] = array(
                    'expire' => $expire_time,
                    'hide' => 1
                );
                $cache->update('opt_mrs_hide_msg', $opt_mrs_hide_msg);
            }
            //redict to previus page
            if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'match_response_hide_notice') === false)
                header('Location: ' . $_SERVER['HTTP_REFERER']);
            else
                header('Location: ' . $mybb->settings['bburl']);
            exit;
        }
    }

    // show match response system
    if ($mybb->input['action'] == 'match_response') {
        
        if (isset($mybb->input['lid'])) {
            $lid = (int) $mybb->input['lid'];
        } elseif (isset($mybb->input['mid'])) {
            $mid   = (int) $mybb->input['mid'];
            $query = $db->simple_select("myleagues_matches", "*", "`mid` = {$mid}");
            
            $temp_match = $db->fetch_array($query);
            $match      = array(
                'mid' => $temp_match['mid'],
                'dateline' => $temp_match['dateline'],
                'league' => $temp_match['league'],
                'matchday' => $temp_match['matchday']
            );
            $db->free_result($query);
            $lid = $match['league'];
        } else {
            $match = opt_army_match_gesponse_get_next_myleages_match();
            if (!empty($match)) {
                $lid = $match['league'];
            }
        }
        if(empty($lid))
        {
            //No future matches then show error and return
            error($lang->opt_army_match_response_no_match, 'No Match');
        }
        
        add_breadcrumb($lang->opt_army_match_response_title, "misc.php?action=match_response&lid=" . $lid);
        
        $setting=opt_army_match_gesponse_get_settings($uid);
        
        if (empty($setting['canuseresp'])) {
            error_no_permission();
        }
        
        if (isset($mybb->input['submit'])) {
            if (verify_post_check($mybb->input['my_post_key'], false)) //Check Key and throw error if false
                {
                $aid = opt_army_match_gesponse_get_aid_by_uid($uid);
                if ($aid < 0) {
                    error('Not in an army');
                }
                
                $mid_submit  = 0;
                $mids = array();
                $successfull_update=false;
                
                foreach ($mybb->input['match_resp'][$uid] as $mid_submit => $match) {
                    if ((int) $match['radio'] > 0) {
                        $mids[]       = $mid_submit;
                        $conditions   = array(
                            'lid' => (int) $lid,
                            'mid' => (int) $mid_submit,
                            'aid' => (int) $aid,
                            'uid' => (int) $uid,
                            'response' => (int) $match['radio'],
                            'comment' => $db->escape_string(substr($match['comment'], 0, 100))
                        );
                        $where_string = '';
                        $i            = 0;
                        foreach ($conditions as $key => $value) {
                            if ($i == 0)
                                $where_string .= " `" . $key . "` = '" . $value . "'";
                            else
                                $where_string .= " AND `" . $key . "` = '" . $value . "'";
                            $i++;
                        }
                        $query = $db->simple_select("match_response", "*", $where_string);
                        if ($db->num_rows($query) == 0) //If same entry not exist or changed
                            {
                            // update user response
                            $db->replace_query('match_response', $conditions);
                            if ($db->affected_rows()>0)
                                $successfull_update=true;    
                        }
                        $db->free_result($query);
                    }
                }
                if($successfull_update===true)
                    $opt_mrs= "<div id=\"flash_message\" class=\"success\">{$lang->opt_army_match_response_sucess}</div>\n";
                
                if (!empty($mids)) {
                    //hide the war notice for the next war if response is not nul
                    //update match responses
                    $opt_mrs_hide_msg = $cache->read('opt_mrs_hide_msg');
                    if (!is_array($opt_mrs_hide_msg))
                        $opt_mrs_hide_msg = array();
                    $match       = opt_army_match_gesponse_get_next_myleages_match();
                    $expire_time = $match['dateline'] + 24 * 60 * 60;
                    if (in_array($match['mid'], $mids)) {
                        $opt_mrs_hide_msg[$uid] = array(
                            'expire' => $expire_time,
                            'hide' => 1
                        );
                        $cache->update('opt_mrs_hide_msg', $opt_mrs_hide_msg);
                    }
                }
            }
        }
        
        //Code from myleagues to generate list
        $myleagues = new myleagues;
        $lang->load("myleagues");
        
        $league            = $db->fetch_array($db->simple_select("myleagues_leagues", "*", "`lid` = {$lid}"));
        $number_of_matches = $db->num_rows($db->simple_select("myleagues_matches", "`mid`", "`league` = {$lid}"));
        $list_of_teams     = array_filter(explode(";", $league['teams']));
        $number_of_teams   = count($list_of_teams);
        
        $title = $league['name'] . " " . $league['season'];
        
        if (empty($league) || ($league['public'] == "no" && $mybb->user['ismoderator'] !== 1) || $number_of_matches == 0 || $number_of_teams == 0) {
            error_no_permission();
        }
        
        
        //get all Responses for this league
        $responses = array();
        $query     = $db->simple_select("match_response", "*", "`lid` = '" . (int) $lid . "' AND `uid`='" . $uid . "'");
        while ($entries = $db->fetch_array($query)) {
            $responses[$entries['uid']][$entries['mid']] = $entries;
        }
        $db->free_result($query);
        
        
        // Loads all of the matchdays and matches. Code from myleagues       
        if (empty($mybb->input['mid'])) {
            $query = $db->simple_select("myleagues_matches", "`mid`, `matchday`, `dateline`, `hometeam`, `awayteam`, `homeresult`, `awayresult`", "`league` = {$lid} AND `dateline`>=UNIX_TIMESTAMP() ", array(
                'order_by' => "dateline",
                'order_dir' => "ASC"
            ));
        } else {
            $query = $db->simple_select("myleagues_matches", "`mid`, `matchday`, `dateline`, `hometeam`, `awayteam`, `homeresult`, `awayresult`, `league`", "`mid` = {$mid}");
        }
        
        while ($temp_match = $db->fetch_array($query)) {
            foreach ($temp_match as $name => $value) {
                $matches[$temp_match['matchday']][$temp_match['mid']][$name] = $value;
            }
        }
        
        if (empty($mybb->input['mid'])) {
            $query = $db->simple_select("myleagues_matchdays", "`mid`, `name`, `startdate`, `enddate`", "`league` = {$lid} AND `enddate`>=UNIX_TIMESTAMP()", array(
                'order_by' => "no",
                'order_dir' => "ASC"
            ));
        } else {
            $query = $db->simple_select("myleagues_matchdays", "`mid`, `name`, `startdate`, `enddate`", "`mid` = " . key($matches));
        }
        
        while ($temp_matchday = $db->fetch_array($query)) {
            foreach ($temp_matchday as $name => $value) {
                $matchdays[$temp_matchday['mid']][$name] = $value;
            }
        }
        
        $form_content = '';
        
        
        // Show the matchdays.
        if (count($matchdays) > 0) {
            $form_content .= "<table border=\"0\" cellspacing=\"{$theme['borderwidth']}\" cellpadding=\"{$theme['tablespace']}\" class=\"tborder\">\n";
            $form_content .= "<thead>\n";
            $form_content .= "<tr><td class=\"thead\" colspan=\"4\"><strong>{$lang->myleagues_schedule} - {$league['name']} {$league['season']}</strong></td></tr>\n";
            $form_content .= "</thead>\n";
            foreach ($matchdays as $matchday) {
                
                $start = my_date($mybb->settings['dateformat'], $matchday['startdate']);
                $end   = my_date($mybb->settings['dateformat'], $matchday['enddate']);
                
                if ($start == $end) {
                    $time = $start;
                } else {
                    $time = $start . " - " . $end;
                }
                
                $form_content .= "<tr>\n";
                $form_content .= "<td class=\"tcat\" colspan=\"3\"><strong>{$matchday['name']}</strong></td><td class=\"tcat\" align=\"center\">{$time}</span></td>\n";
                $form_content .= "</tr>\n";
                
                foreach ((array) $matches[$matchday['mid']] as $match) {
                    
                    $class = $myleagues->trow();
                    
                    $form_content .= "<tr cellspacing=\"0\">\n";
                    $form_content .= "<td class=\"{$class}\" align=\"right\" width=\"35%\" style=\"padding-right: 10px;\">" . $myleagues->get_name($match['hometeam'], "teams") . "</td>\n";
                    $form_content .= "<td class=\"{$class}\" align=\"center\">{$match['homeresult']}:{$match['awayresult']}</td>\n";
                    $form_content .= "<td class=\"{$class}\" align=\"left\" width=\"35%\" style=\"padding-left: 10px;\">" . $myleagues->get_name($match['awayteam'], "teams") . "</td>\n";
                    $form_content .= "<td class=\"{$class}\" align=\"center\">" . my_date($mybb->settings['dateformat'], $match['dateline']) . " " . my_date($mybb->settings['timeformat'], $match['dateline']) . "</td>\n";
                    $form_content .= "</tr>\n";
                    $form_content .= opt_army_match_response_generate_radio($responses, $uid, $match['mid']);
                }
            }
            
            $form_content .= "</table>\n";
            eval("\$content = \"" . $templates->get("optmatchresponse_form") . "\";");
        } else {
            //No future matches
            $content = $lang->opt_army_match_response_no_match;
        }
        opt_army_match_response_global_end();
        eval("\$opt_army_match_response = \"" . $templates->get("optmatchresponse_misc_page") . "\";");
        
        output_page($opt_army_match_response);
    }
    
    // show match responses
    if ($mybb->input['action'] == 'match_response_display') {
        
        $uid = (int) $mybb->user['uid'];
        
        if (isset($mybb->input['lid'])) {
            $lid   = (int) $mybb->input['lid'];
            $match = opt_army_match_gesponse_get_next_myleages_match($lid);
        } elseif (isset($mybb->input['mid'])) {
            $mid   = (int) $mybb->input['mid'];
            $query = $db->simple_select("myleagues_matches", "*", "`mid` = {$mid}");
            
            $temp_match = $db->fetch_array($query);
            $match      = array(
                'mid' => $temp_match['mid'],
                'dateline' => $temp_match['dateline'],
                'league' => $temp_match['league'],
                'matchday' => $temp_match['matchday']
            );
            $db->free_result($query);
        } else {
            $match = opt_army_match_gesponse_get_next_myleages_match();
        }
        if (!empty($match)) {
            $lid      = $match['league'];
            $mid      = $match['mid'];
            $matchday = $match['matchday'];
        } else {
            error($lang->opt_army_match_response_no_match, 'No Match');
        }
        if(empty($lid))
            error($lang->opt_army_match_response_no_match, 'No Match');
        
        $myleagues = new myleagues;
        $lang->load("myleagues");
        
        
        $league            = $db->fetch_array($db->simple_select("myleagues_leagues", "*", "`lid` = {$lid}"));
        $number_of_matches = $db->num_rows($db->simple_select("myleagues_matches", "`mid`", "`league` = {$lid}"));
        $list_of_teams     = array_filter(explode(";", $league['teams']));
        $number_of_teams   = count($list_of_teams);
        
        $title = $league['name'] . " " . $league['season'];
        
        if (empty($league) || ($league['public'] == "no" && $mybb->user['ismoderator'] !== 1) || $number_of_matches == 0 || $number_of_teams == 0) {
            error_no_permission();
        }
        
        $query         = $db->simple_select("myleagues_matchdays", "`name`,`name`", "`mid` = {$matchday}");
        $temp_matchday = $db->fetch_array($query);
        $matchname     = $temp_matchday['name'].' ('.my_date($mybb->settings['dateformat'], $match['dateline']).')';
        $db->free_result($query);
        
        add_breadcrumb($matchname, "misc.php?action=match_response_display");
        //get all Responses for this league and Match
        $responses = array();
        $query     = $db->simple_select("match_response", "*", "`lid` = '" . (int) $lid . "' AND `mid` = '" . (int) $mid . "'", array(
            'order_by' => "`response` DESC, `uid` ASC"
        ));
        while ($entries = $db->fetch_array($query)) {
            $responses[$entries['uid']] = $entries;
        }
        $db->free_result($query);
        
        $setting=opt_army_match_gesponse_get_settings($uid);
        
        
        $conditions  = '';
        if (!empty($setting['view']))
            $conditions = $setting['view'];
        if (!empty($setting['viewsum'])) {
            if (empty($conditions))
                $conditions = $setting['viewsum'];
            else
                $conditions .= ',' . $setting['viewsum'];
        }
        
        //if only his group can be viewed display the group and exit
        if ($setting['only'] == 1 AND empty($conditions)) {
            $content       = '';
            $responses_ctn = new Responses_count();
            foreach($setting['gid'] as $tmp_gid)
                $content .= opt_army_match_gesponse_show_group($tmp_gid, $responses, $responses_ctn, false);
            eval("\$opt_army_match_response = \"" . $templates->get("optmatchresponse_misc_page") . "\";");
            output_page($opt_army_match_response);
            return;
        }else if($setting['only'] == 1)
        {
            $aid=opt_army_match_gesponse_get_aid_by_uid($uid);       
            $agid=opt_army_match_gesponse_get_gid_from_aid($aid);
            if (empty($conditions))
                $conditions = $agid; 
            else   
                $conditions .= ',' . $agid; 
        }
        
        if (empty($conditions)) {
            $content = 'Nothing to view';
            eval("\$opt_army_match_response = \"" . $templates->get("optmatchresponse_misc_page") . "\";");
            output_page($opt_army_match_response);
            return;
        }

        $conditions=implode(',',array_unique(explode(',',$conditions)));
        
        $query_armies = $db->simple_select('armies', '*', 'gid IN(' . $conditions . ')', array(
            'order_by' => "`displayorder` ASC"
        ));
        $content      = '';
        while ($army = $db->fetch_array($query_armies)) {
            $armygroups   = '';
            $summary='';
            $army_nation  = $army['nation'];
            $army_name    = $army['name'];
            $army_gid     = $army['gid'];       
            
            $has_response_members = array();
            $query     = $db->simple_select("match_response", "uid", "`lid` = '" . (int) $lid . "' AND `mid` = '" . (int) $mid . "'", array(
                'order_by' => "`uid` ASC"
            ));
            while ($entries = $db->fetch_array($query)) {
                $has_response_members[$entries['uid']] = $entries['uid'];
            }
            $db->free_result($query);
            $army_members_array=array();
            $army_members_array += opt_army_match_gesponse_get_groupmembers($army['gid']);
            if (!empty($army['HCO_gid'])) {
                $army_members_array += opt_army_match_gesponse_get_groupmembers($army['uugid']);
            }
            $no_response_members=array_diff($army_members_array,$has_response_members);
            
            $army_members = count($army_members_array); //count army members
            unset($army_members_array);
            
            $responses_ctn     = new Responses_count();
            $sum_responses_ctn = new Responses_count();
            
            // build the group display
            
            if (!empty($army['HCO_gid'])) //HL
                {
                $responses_ctn->nul();
                $armygroups .= opt_army_match_gesponse_show_group($army['HCO_gid'], $responses, $responses_ctn, in_array($army['gid'], array_diff(explode(',', $setting['viewsum']), explode(',', $setting['view']))));
                $sum_responses_ctn->add($responses_ctn);
                $responses_ctn->nul();
                if(!empty(str_replace('<br>','',$armygroups)))
                    $armygroups .= '<br>';
            }
            if (!empty($army['CO_gid'])) //Offiziere       
                {
                $responses_ctn->nul();
                $armygroups .= opt_army_match_gesponse_show_group_CO($army['CO_gid'], $responses, $responses_ctn, in_array($army['gid'], array_diff(explode(',', $setting['viewsum']), explode(',', $setting['view']))), $army);
                $sum_responses_ctn->add($responses_ctn);
                $army_members+=$responses_ctn->Yes + $responses_ctn->No  + $responses_ctn->No_Response + $responses_ctn->Unsure;
                $responses_ctn->nul();
                if(!empty(str_replace('<br>','',$armygroups)))
                    $armygroups .= '<br>';
            } 
            if(substr($armygroups, -1 * strlen('<br><br>'))=='<br><br>')
                $armygroups=substr($armygroups,0, -1 * strlen('<br>'));   
            
            // recursively build all subgroups
            $query_groups = $db->simple_select('armies_structures', '*', 'pagrid IS NULL AND aid=' . $army['aid'], array(
                'order_by' => 'displayorder',
                'order_dir' => 'ASC'
            ));
            
            
            while ($group = $db->fetch_array($query_groups)) {
                
                $armygroups .= opt_army_match_gesponse_recursive_group($group['agrid'], $responses, $responses_ctn, in_array($army['gid'], array_diff(explode(',', $setting['viewsum']), explode(',', $setting['view']))));
                $sum_responses_ctn->add($responses_ctn);
                $responses_ctn->nul();
            }
            
            $db->free_result($query_groups);
            
            $armygroups .= '<br>';
            
            // and finally (resulting on top) the default group
            $query_default = $db->simple_select('usergroups', '*', 'gid=' . $army['uugid']);
            $defaultgroup  = $db->fetch_array($query_default);
            $db->free_result($query_default);
            
            $responses_ctn->nul();
            $armygroups .= opt_army_match_gesponse_show_group($defaultgroup['gid'], $responses, $responses_ctn, in_array($army['gid'], array_diff(explode(',', $setting['viewsum']), explode(',', $setting['view']))));
            $sum_responses_ctn->add($responses_ctn);
            
            
            $responses_ctn = $sum_responses_ctn;
            unset($sum_responses_ctn);
            
                     
            
            $army_members_match_response_yes         = $responses_ctn->Yes;
            $army_members_match_response_no          = $responses_ctn->No;
            $army_members_match_response_unsure      = $responses_ctn->Unsure;
            $army_members_match_response_no_response = $responses_ctn->No_Response;   
            //responses are saved for the template dont need $responses_ctn anymore
             
           
            //if user can only view his group and his army is currently processed
            if ($setting['only'] == 1 AND $army['aid'] == opt_army_match_gesponse_get_aid_by_uid($uid)) {
                $responses_ctn->nul(); 
                
                if(empty(str_replace('<br>','',$armygroups)))
                {
                    foreach($setting['gid'] as $tmp_gid)    
                        $armygroups .= opt_army_match_gesponse_show_group($tmp_gid, $responses, $responses_ctn, false).'<br><br>';   
                }                                               
                if(substr($armygroups, -1 * strlen('<br><br>'))=='<br><br>')
                    $armygroups=substr($armygroups,0, -1 * strlen('<br><br>'));    
                $responses_ctn->nul();     
                if($setting['special']==1)           
                    $armygroups .= '<br>'.opt_army_match_gesponse_show_group_sub($no_response_members,$lang->opt_army_match_response_no_response_members,null, $responses_ctn,false,'style="display: none;"');
                if(substr($armygroups, -1 * count('<br><br>'))=='<br><br>')
                    $armygroups=substr($armygroups,0, -1 * strlen('<br><br>'));
                //if user can view the summary
                if(strpos($setting['viewsum'],$army_gid)!==false)
                {
                    eval("\$summary = \"" . $templates->get("optmatchresponse_show_summary") . "\";"); 
                }
                eval("\$content .= \"" . $templates->get("optmatchresponse_show_army_response") . "\";"); 
            }else{     
                $responses_ctn->nul();     
                if($setting['special']==1 AND $army['aid'] == opt_army_match_gesponse_get_aid_by_uid($uid))      
                    $armygroups .= '<br>'.opt_army_match_gesponse_show_group_sub($no_response_members,$lang->opt_army_match_response_no_response_members,null, $responses_ctn,false,'style="display: none;"');
                if(substr($armygroups, -1 * strlen('<br><br>'))=='<br><br>')
                    $armygroups=substr($armygroups,0, -1 * strlen('<br><br>'));
                //if user can view the summary
                if(strpos($setting['viewsum'],$army_gid)!==false)
                {  
                    eval("\$summary = \"" . $templates->get("optmatchresponse_show_summary") . "\";"); 
                }
                eval("\$content .= \"" . $templates->get("optmatchresponse_show_army_response") . "\";");              
            }    

            unset($responses_ctn);
            unset($sum_responses_ctn);           
        }
        $db->free_result($query_armies);
        
        eval("\$opt_army_match_response = \"" . $templates->get("optmatchresponse_misc_page") . "\";");
        output_page($opt_army_match_response);
    }
    
}

//generate the radio fields for the match response form
function opt_army_match_response_generate_radio($responses, $mybb_uid, $mid)
{
    global $db, $mybb, $lang, $templates, $headerinclude, $header, $footer, $theme, $cache;
    $checked        = array(
        0 => "",
        1 => "",
        2 => "",
        3 => ""
    );
    $match_resp_txt = '';
    
    if (isset($responses[$mybb_uid][$mid])) {
        $checked[$responses[$mybb_uid][$mid]['response']] = 'checked="checked"';
        $match_resp_txt                                   = htmlspecialchars($responses[$mybb_uid][$mid]['comment']);
    } else {
        $checked[0] = 'checked="checked"'; //no entry set no response
    }
    
    eval("\$content = \"" . $templates->get("optmatchresponse_radio") . "\";");
    
    return $content;
}


//generate the group view for display Offiziere (checks if user exist already in other squad member group)
function opt_army_match_gesponse_show_group_CO($gid, $responses, Responses_count &$responses_ctn, $only_sum = false, $army)
{
    global $db, $cache, $templates, $lang, $theme, $mybb;
    
    $group_members_uids = opt_army_match_gesponse_get_groupmembers($gid);
    if (!empty($army['HCO_gid']))
        $normal_groups = array(
            $army['HCO_gid'] => $army['HCO_gid']
        );
    else
        $normal_groups = array();
        
    if (!empty($army['uugid']))
        $normal_groups[$army['uugid']] = $army['uugid'];
        
    $query_groups = $db->simple_select('armies_structures', '*', 'aid=' . $army['aid']);
    while ($group = $db->fetch_array($query_groups)) {
        $normal_groups[$group['gid']] = $group['gid'];
    }
    foreach ($group_members_uids as $uid) {
        $usergroups = opt_army_match_gesponse_get_usergroups($uid);
        foreach ($usergroups as $tmp_gid) {
            if (in_array($tmp_gid, $normal_groups)) //wenn Offizier in normaler gruppe schon vorhanden   
                unset($group_members_uids[$uid]); //nicht anzeigen
        }
    }
    if (count($group_members_uids) > 0) {
        $usergroups = $cache->read('usergroups');
        $group_name = $usergroups[$gid]['title'];
        $content=opt_army_match_gesponse_show_group_sub($group_members_uids,$group_name,$responses, $responses_ctn, $only_sum);
        return $content; 
    }
}

//generate the group view for display
function opt_army_match_gesponse_show_group($gid, $responses, Responses_count &$responses_ctn, $only_sum = false, $child='')
{
    global $db, $cache, $templates, $lang, $theme, $mybb;
    
    $group_members_uids = opt_army_match_gesponse_get_groupmembers($gid);
    $usergroups = $cache->read('usergroups');
    $group_name = $usergroups[$gid]['title'];
    $content=opt_army_match_gesponse_show_group_sub($group_members_uids,$group_name,$responses, $responses_ctn, $only_sum,'',$child);
    return $content; 
}

function opt_army_match_gesponse_show_group_sub($group_members_uids,$group_name, $responses, Responses_count &$responses_ctn, $only_sum = false, $style='', $child='')
{
    global $db, $cache, $templates, $lang, $theme, $mybb;
    $group_name = $group_name;
    $subgroups  = '';
    $data       = '';
    $content    = '';
    foreach ($group_members_uids as $uid) {
        $username_link = opt_army_match_gesponse_nice_username($uid);
        if (empty($responses[$uid])) {
            $army_members_match_response = $lang->opt_army_match_response_no_response;
            $army_members_match_response_num = Response::No_Response;
            $responses_ctn->No_Response++;
            $army_members_match_response_comment = '';
            $army_members_match_response_time    = '';
        } else {
            switch ((int) $responses[$uid]['response']) {
                case Response::Unsure:
                    $army_members_match_response_num = Response::Unsure;
                    $army_members_match_response = $lang->opt_army_match_response_unsure;
                    $responses_ctn->Unsure++;
                    break;
                case Response::Yes:
                    $army_members_match_response_num = Response::Yes;
                    $army_members_match_response = $lang->opt_army_match_response_yes;
                    $responses_ctn->Yes++;
                    break;
                case Response::No:
                    $army_members_match_response_num = Response::No;
                    $army_members_match_response = $lang->opt_army_match_response_no;
                    $responses_ctn->No++;
                    break;
                default:
                    $army_members_match_response_num = Response::No_Response;
                    $responses_ctn->No_Response++;
                    $army_members_match_response = $lang->opt_army_match_response_no_response;
            }
            $army_members_match_response_comment = htmlspecialchars($responses[$uid]['comment']);
            $army_members_match_response_time    = '';
        }             
        if (!$only_sum)
            eval("\$data .= \"" . $templates->get("optmatchresponse_show_response_user") . "\";");
    }
    $responses = $data;
    if (!$only_sum)
        eval("\$content = \"" . $templates->get("optmatchresponse_show_group_response") . "\";");
    return $content;  
}

//generate revursive the  group view for display
function opt_army_match_gesponse_recursive_group($agrid, $responses, Responses_count &$responses_ctn, $only_sum = false)
{
    global $db, $templates, $lang, $theme, $cache;
    // find all sub groups of this group
    $query     = $db->simple_select('armies_structures', '*', 'pagrid=' . intval($agrid), array(
        'order_by' => 'displayorder',
        'order_dir' => 'ASC'
    ));
    $groupdata = '';
    
    $sum_responses_ctn = new Responses_count();
    
    while ($group = $db->fetch_array($query)) {
        $groupdata .= opt_army_match_gesponse_recursive_group($group['agrid'], $responses, $responses_ctn, $only_sum);
        $sum_responses_ctn->add($responses_ctn);
        $responses_ctn->nul();
    }
    $responses_ctn = $sum_responses_ctn;
    unset($sum_responses_ctn);
    $db->free_result($query);
    
    if (!empty($groupdata)) {
        $subgroups = $groupdata;
        eval("\$groupdata = \"" . $templates->get("optmatchresponse_group_subgroups") . "\";");
        $subgroups = '';
    }
    
    $query = $db->simple_select('armies_structures', '*', 'agrid=' . intval($agrid));
    $group = $db->fetch_array($query);
    $db->free_result($query);
    
    return opt_army_match_gesponse_show_group($group['gid'], $responses, $responses_ctn, $only_sum, $groupdata);
}

//aus opt_armies.php
function opt_army_match_gesponse_get_aid_by_uid($uid)
{
    global $db;
    
    $aid        = -1; // not in an army
    $usergroups = opt_army_match_gesponse_get_usergroups($uid);
    $query      = $db->simple_select('armies', '*');
    while ($army = $db->fetch_array($query)) {
        if (in_array($army['gid'], $usergroups)) {
            $aid = $army['aid'];
            break;
        }
    }
    $db->free_result($query);
    if ($aid == -1) {
        $query = $db->simple_select('armies');
        while ($army = $db->fetch_array($query)) {
            if (in_array($army['gid'], $usergroups)) {
                $aid = $army['aid'];
            }
            if (!empty($army['hco_gid']) && in_array($army['hco_gid'], $usergroups)) {
                $aid = $army['aid'];
            }
            if (!empty($army['co_gid']) && in_array($army['co_gid'], $usergroups)) {
                $aid = $army['aid'];
            }
            if (in_array($army['uugid'], $usergroups)) {
                $aid = $army['aid'];
            }
        }
        $db->free_result($query);
    }
    
    return $aid;
}
//aus opt_armies.php
function opt_army_match_gesponse_get_usergroups($uid)
{
    global $db;
    
    $query = $db->simple_select('users', 'usergroup, additionalgroups', 'uid=' . intval($uid));
    $data  = $db->fetch_array($query);
    $db->free_result($query);
    $usergroup_string=$data['usergroup'];
    if(!empty($data['additionalgroups']))
        $usergroup_string.=','.$data['additionalgroups'];
    $usergroups = explode(',', $usergroup_string);
    $usergroups = array_diff( $usergroups, array(''),array(' '));//remove all empty elements
    return $usergroups;
}
//aus opt_armies.php
function opt_army_match_gesponse_get_groupmembers($gid)
{
    global $db;
    $groupmembers = array();
    $query        = $db->simple_select('users', 'uid,usergroup,additionalgroups', '', array(
        'order_by' => 'uid',
        'order_dir' => 'ASC'
    ));
    while ($user = $db->fetch_array($query)) {
        $groups   = explode(',', $user['additionalgroups']);
        $groups[] = $user['usergroup'];
        if(is_array($gid))
        {
            foreach($gid as $_gid)
            if (in_array($_gid, $groups)) {
                $groupmembers[$user['uid']] = $user['uid'];
            }   
        }else{
            if (in_array($gid, $groups)) {
                $groupmembers[$user['uid']] = $user['uid'];
            }
        }
    }
    $db->free_result($query);
    
    return $groupmembers;
}
//aus opt_armies.php
function opt_army_match_gesponse_nice_username($uid)
{
    $user_data = opt_army_match_gesponse_user_by_uid($uid);      
    $user      = build_profile_link(format_name($user_data['username'], $user_data['usergroup']), $uid);
    
    return $user;
}       
//aus opt_armies.php
function opt_army_match_gesponse_username_by_uid($uid)
{
    global $db;
    
    $result = opt_army_match_gesponse_user_by_uid($uid);
    if (empty($result[ 'username' ]))
        $result[ 'username' ] = $lang->opt_armies_uid_unknown;
    return $result[ 'username' ];
}
//aus opt_armies.php
function opt_army_match_gesponse_user_by_uid($uid)
{
    global $db;
    
    $query = $db->simple_select('users', '*', 'uid=' . intval($uid));
    $user  = $db->fetch_array($query);
    $db->free_result($query);
    
    $user[ 'usergroups' ] = array_merge(array(
        $user[ 'usergroup' ]
    ), explode(',', $user[ 'additionalgroups' ]));
    
    return $user;
}
//aus opt_armies.php
function opt_army_match_gesponse_get_gid_from_agrid($agrid)
{
    global $db;
    
    $query = $db->simple_select('armies_structures', 'gid', 'agrid=' . intval($agrid));
    $gid   = $db->fetch_field($query, 'gid');
    $db->free_result($query);
    
    return $gid;
}


function opt_army_match_gesponse_get_gid_from_aid($aid)
{
    global $db;
    
    $query = $db->simple_select('armies', 'gid', 'aid=' . intval($aid));
    $gid   = $db->fetch_field($query, 'gid');
    $db->free_result($query);
    
    return $gid;
}

//get next myleague match
function opt_army_match_gesponse_get_next_myleages_match($lid = 0)
{
    global $db;
    
    if (!empty($lid)) 
        $query_string_add="AND `league` = '{$lid}'";    
    else 
        $query_string_add='';   
    
    $query_string="SELECT `mid`, `dateline`, `league` ,`matchday` 
            FROM `".TABLE_PREFIX."myleagues_matches` 
            LEFT JOIN (`".TABLE_PREFIX."myleagues_leagues`) 
                ON `".TABLE_PREFIX."myleagues_matches`.`league` = `".TABLE_PREFIX."myleagues_leagues`.`lid`
            WHERE `dateline`>UNIX_TIMESTAMP() 
                AND `".TABLE_PREFIX."myleagues_leagues`.`public`='1'
                {$query_string_add}  
            ORDER BY `dateline` ASC
            LIMIT 1";
            
    $query = $db->query($query_string);        
    
    if ($db->num_rows($query) == 0)
        return false;
    
    $temp_match = $db->fetch_array($query);
    $ret_var    = array(
        'mid' => $temp_match['mid'],
        'dateline' => $temp_match['dateline'],
        'league' => $temp_match['league'],
        'matchday' => $temp_match['matchday']
    );
    $db->free_result($query);
    return $ret_var;
}

function opt_army_match_gesponse_get_settings($uid)
{
    global $db;
    
    $groups = implode(',', opt_army_match_gesponse_get_usergroups($uid));
    if (empty($groups))
        $groups = 0;
    $query = $db->simple_select('match_response_setting', '*', 'gid IN(' .  $groups . ')');
        
        $setting = array();
        $setting['only']=0;
        $setting['canuseresp']=0;
        
        while ($tmp_setting = $db->fetch_array($query)) {
            if (!empty($tmp_setting['view'])) {
                if (!empty($setting['view']))
                    $setting['view'] .= ',';
                
                $setting['view'] .= $tmp_setting['view'];
            }
            
            if (!empty($tmp_setting['viewsum'])) {
                if (!empty($setting['viewsum']))
                    $setting['viewsum'] .= ',';
                
                $setting['viewsum'] .= $tmp_setting['viewsum'];
            }
            
            if (!empty($tmp_setting['special'])) {
                $setting['special'] = $tmp_setting['special'];
            }
            
            if ($tmp_setting['only'] == 1) {
                $setting['only'] = $tmp_setting['only'];
                $setting['gid'][]  = $tmp_setting['gid'];
            }
            if ($tmp_setting['canuseresp'] == 1)
                $setting['canuseresp'] = $tmp_setting['canuseresp'];
        }
        //if no gid was set assign last fetched gid
        if (empty($setting['gid']))
            $setting['gid'][] = $tmp_setting['gid'];
        
        $db->free_result($query);
        return $setting;
}

//setup the tempaltes for the war response system
function opt_army_match_gesponse_setup_templates()
{
    global $PL;
    $PL or require_once PLUGINLIBRARY;
    $PL->templates('optmatchresponse', 'OPT Match Response', array(
        'form' => '
            <form action="misc.php?action=match_response&lid={$lid}" method="post">
                <input type="hidden" name="my_post_key" value="{$mybb->post_code}" >
                {$form_content}
                <input type="submit" name="submit" value="{$lang->opt_army_match_response_submit}" >
            </form>',
        'radio' => '
            <td colspan="4">
                <fieldset class="fieldset_match_resp">
                    <input type="radio" id="radio_match_resp_2_$mid" name="match_resp[{$mybb_uid}][{$mid}][radio]" value="' . Response::Yes . '" {$checked[' . Response::Yes . ']} ><label for="radio_match_resp_2_$mid" class="optmatchresponse_resp_' . Response::Yes . '">{$lang->opt_army_match_response_yes}</label>
                    <input type="radio" id="radio_match_resp_3_$mid" name="match_resp[{$mybb_uid}][{$mid}][radio]" value="' . Response::No . '" {$checked[' . Response::No . ']} ><label for="radio_match_resp_3_$mid" class="optmatchresponse_resp_' . Response::No . '">{$lang->opt_army_match_response_no}</label>
                    <input type="radio" id="radio_match_resp_1_$mid" name="match_resp[{$mybb_uid}][{$mid}][radio]" value="' . Response::Unsure . '" {$checked[' . Response::Unsure . ']} ><label for="radio_match_resp_1_$mid" class="optmatchresponse_resp_' . Response::Unsure . '">{$lang->opt_army_match_response_unsure}</label>    
                    <input type="radio" id="radio_match_resp_0_$mid" name="match_resp[{$mybb_uid}][{$mid}][radio]" value="' . Response::No_Response . '" {$checked[' . Response::No_Response . ']} disabled="disabled"><label for="radio_match_resp_0_$mid" class="optmatchresponse_resp_' . Response::No_Response . '">{$lang->opt_army_match_response_no_response}</label>
                <hr style="background-color: #CCCCCC;">
                {$lang->opt_army_match_response_text}: <input type="text" name="match_resp[{$mybb_uid}][{$mid}][comment]" maxlength="80" value="{$match_resp_txt}">
            </td></fieldset>
                ',
        
        'misc_page' => '
            <html>
                <head>
                    <title>{$mybb->settings[\'bbname\']} - {$lang->opt_army_match_response_title}</title>
                    {$headerinclude}
                    <script type="text/javascript">
                    jQuery(document).ready(function($){
                             $("thead").click(function () {
                                   $(this).next("tbody").toggle();
                                }
                             )
                    });
                    </script>
                </head>
                <body>
                    {$header}
                    {$errors}
                    {$content}
                    {$footer}
                </body>
            </html>',
        'show_summary' => '<tr class="tcat">
        <td><strong>{$lang->opt_army_match_response_nation}</strong></td>
        <td><strong>{$lang->opt_army_match_response_members}</strong></td>
        <td><strong>{$lang->opt_army_match_response_yes}</strong></td>
        <td><strong>{$lang->opt_army_match_response_no}</strong></td>
        <td><strong>{$lang->opt_army_match_response_unsure}</strong></td>
        <td><strong>{$lang->opt_army_match_response_no_response}</strong></td>
    </tr>
    <tr class="trow1">
        <td>{$army_nation}</td>
        <td>{$army_members}</td>
        <td>{$army_members_match_response_yes}</td>
        <td>{$army_members_match_response_no}</td>
        <td>{$army_members_match_response_unsure}</td>
        <td>{$army_members_match_response_no_response}</td>
    </tr>',
        'show_army_response' => '
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
    <thead>
    <tr>
        <td class="thead" width="100%" colspan="6"><strong>{$army_name}</strong></td>
    </tr>
    {$summary}
    </thead>
    <tbody>
    <tr>
        <td class="trow2" width="100%" colspan="6">{$armygroups}</td>
    </tr> 
    <tbody>
</table><br>
<br>
<br>',
        'show_group_response' => '
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<thead>
    <tr>
            <td class="thead" width="80%" colspan="2"><strong>{$lang->opt_armies_group}: {$group_name}</strong></td>
            <td class="thead" colspan="2"><strong>{$responses_ctn->Yes} $lang->opt_army_match_response_yes</strong></td>
    </tr>
</thead>
<tbody {$style}>
    <tr class="tcat">
        <td width="40%"><strong>{$lang->opt_armies_army_members}</strong></td>
        <td><strong>{$lang->opt_army_match_response}</strong></td>
        <td><strong>{$lang->opt_army_match_response_text}</strong></td>
        <td></td>
    </tr>
    {$responses}  
    <tr><td colspan="4">{$child}</td></tr>   
</tbody>
</table>',
        'show_response_user' => '<tr class="trow1">
                                    <td>{$username_link}</td>
                                    <td class="optmatchresponse_resp_{$army_members_match_response_num}">{$army_members_match_response}</td>
                                    <td>{$army_members_match_response_comment}</td>
                                    <td>{$army_members_match_response_time}</td>
                                </tr>',
        'group_subgroups' => '<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
    <tr>
        <td class="" colspan="4" style="padding-left: 40px">{$subgroups}</td>
    </tr>
</table>',
        'notice' => '<div class="pm_alert" id="mrs_notice">
    <div class="float_right"><a href="{$base_url}/misc.php?action=match_response_hide_notice&my_post_key={$mybb->post_code}" title="{$lang->opt_army_match_dismiss_notice}"><img src="{$theme[\'imgdir\']}/dismiss_notice.gif" alt="{$lang->opt_army_match_dismiss_notice}" title="[x]" /></a></div>
    <div><a href="{$base_url}/misc.php?action=match_response">{$mrs_text}</a></div>
</div>
<br>'
    ));
    
function opt_army_match_gesponse_setup_stylesheet()
{ 
    global $PL;
    
    $styles = array(
        '.optmatchresponse_resp_'.Response::No_Response => array(
            'color' => '#333333'
        ),
        '.optmatchresponse_resp_'.Response::Unsure => array(
            'color' => '#A0A000'
        ),
        '.optmatchresponse_resp_'.Response::Yes => array(
            'color' => '#008800'
        ),
        '.optmatchresponse_resp_'.Response::No => array(
            'color'=> '#AA0000'
        ),
        
    '#flash_message'=>'
    margin: 10px 0;
    padding: 10px 0 10px 24px;
    font-weight: bold;
    background: #efefef;
    border: 1px solid #ccc;',
       
    '#flash_message.error '=>'
    border: 1px solid #FC6;
    background: #FFC 4px 9px;
    color: #C00;',
    
    '#flash_message.success '=>'
    border: #080 1px solid;
    color: #080;
    background: #E8FCDC 4px 9px;',

    '.alert '=>'
    margin: 10px 0;
    padding: 5px 10px;
    border: #FC6 1px solid;
    background-color: #ffc;
    color: #C00; 
    font-style: normal;
    font-weight: bold;
    padding-left: 24px;
    display: block;'
    );
    $PL->stylesheet('optmatchresponse', $styles);
}    
    
}