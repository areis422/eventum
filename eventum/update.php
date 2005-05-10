<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003, 2004, 2005 MySQL AB                              |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 59 Temple Place - Suite 330                                          |
// | Boston, MA 02111-1307, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: Jo�o Prado Maia <jpm@mysql.com>                             |
// +----------------------------------------------------------------------+
//
// @(#) $Id: s.update.php 1.15 03/10/31 17:09:07-00:00 jpradomaia $
//
include_once("config.inc.php");
include_once(APP_INC_PATH . "class.template.php");
include_once(APP_INC_PATH . "class.auth.php");
include_once(APP_INC_PATH . "class.issue.php");
include_once(APP_INC_PATH . "class.category.php");
include_once(APP_INC_PATH . "class.priority.php");
include_once(APP_INC_PATH . "class.project.php");
include_once(APP_INC_PATH . "class.release.php");
include_once(APP_INC_PATH . "class.misc.php");
include_once(APP_INC_PATH . "class.notification.php");
include_once(APP_INC_PATH . "class.status.php");
include_once(APP_INC_PATH . "class.group.php");
include_once(APP_INC_PATH . "db_access.php");

$prj_id = Auth::getCurrentProject();
$usr_id = Auth::getUserID();
$role_id = Auth::getCurrentRole();

$tpl = new Template_API();
$tpl->setTemplate("update.tpl.html");

Auth::checkAuthentication(APP_COOKIE);

$issue_id = @$HTTP_POST_VARS["issue_id"] ? $HTTP_POST_VARS["issue_id"] : $HTTP_GET_VARS["id"];
$details = Issue::getDetails($issue_id);
$tpl->assign("issue", $details);
$tpl->assign("extra_title", "Update Issue #$issue_id");

if (($role_id == User::getRoleID('customer')) && (User::getCustomerID($usr_id) != $details['iss_customer_id'])) {
    $tpl->assign("auth_customer", 'denied');
} elseif (!Issue::canAccess($issue_id, $usr_id)) {
    $tpl->assign("auth_customer", 'denied');
} else {
    $associated_projects = @array_keys(Project::getAssocList($usr_id));
    $new_prj_id = Issue::getProjectID($issue_id);
    $auto_switched = false;
    if ((!empty($new_prj_id)) && (in_array($new_prj_id, $associated_projects)) && ($new_prj_id != $prj_id)) {
        $cookie = Auth::getCookieInfo(APP_PROJECT_COOKIE);
        Auth::setCurrentProject($new_prj_id, $cookie["remember"], true);
        Auth::redirect(APP_BASE_URL . "update.php?id=$issue_id");
    }
    if (@$HTTP_POST_VARS["cat"] == "update") {
        $res = Issue::update($HTTP_POST_VARS["issue_id"]);
        $tpl->assign("update_result", $res);
        if (Issue::hasDuplicates($HTTP_POST_VARS["issue_id"])) {
            $tpl->assign("has_duplicates", "yes");
        }
    }
    
    $prj_id = Auth::getCurrentProject();
    
    $setup = Setup::load();
    
    // if currently selected release is in the past, manually add it to list
    $releases = Release::getAssocList($prj_id);
    if ($details["iss_pre_id"] != 0 && empty($releases[$details["iss_pre_id"]])){
        $releases = array($details["iss_pre_id"] => $details["pre_title"]) + $releases;
    }
    
    if (Workflow::hasWorkflowIntegration($prj_id)) {
        $statuses = Workflow::getAllowedStatuses($prj_id, $issue_id);
        // if currently selected release is not on list, go ahead and add it.
    } else {
        $statuses = Status::getAssocStatusList($prj_id, false);
    }
    if ((!empty($details['iss_sta_id'])) && (empty($statuses[$details['iss_sta_id']]))) {
        $statuses[$details['iss_sta_id']] = Status::getStatusTitle($details['iss_sta_id']);
    }
    
    $tpl->assign(array(
        "subscribers"  => Notification::getSubscribers($issue_id),
        "categories"   => Category::getAssocList($prj_id),
        "priorities"   => Priority::getAssocList($prj_id),
        "status"       => $statuses,
        "releases"     => $releases,
        "resolutions"  => Resolution::getAssocList(),
        "users"        => Project::getUserAssocList($prj_id, 'active', User::getRoleID('Customer')),
        "issues"       => Issue::getColList("iss_id <> $issue_id"),
        "assoc_issues" => array_map("htmlspecialchars", Issue::getAssocList()),
        "one_week_ts"  => time() + (7 * DAY),
        "allow_unassigned_issues"   =>  @$setup["allow_unassigned_issues"],
        "groups"       => Group::getAssocList($prj_id)
    ));
    
    $cookie = Auth::getCookieInfo(APP_PROJECT_COOKIE);
    if (!empty($cookie['auto_switched_from'])) {
        $tpl->assign(array(
            "project_auto_switched" =>  1,
            "old_project"   =>  Project::getName($cookie['auto_switched_from'])
        ));
    }
}
$tpl->displayTemplate();
?>