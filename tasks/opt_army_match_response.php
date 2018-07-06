<?php
/* 
OPT army match response system (OPT-MRS)

This plugin is based on:
MyLeagues by Filip Klar 2012 http://fklar.pl/tag/myleagues/ author: Filip Klar <kontakt@fklar.pl>
MyBB-Plugin-OPT-Armies by TerranUlm  https://github.com/TerranUlm/MyBB-Plugin-OPT-Armies author: Dieter Gobbers

License: The MIT License (MIT)
*/
  
// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

function task_opt_army_match_response($task)
{
    opt_army_match_response_auto_match_attendance();

    add_task_log($task, "processed all users for automatic match attendance");
}

