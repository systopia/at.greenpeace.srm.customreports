<?php
/*-------------------------------------------------------+
| Greenpeace SRM Custom reports                          |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: J. Schuppe (schuppe@systopia.de)
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

require_once 'customreports.civix.php';
use CRM_Customreports_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function customreports_civicrm_config(&$config) {
  _customreports_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function customreports_civicrm_install() {
  _customreports_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function customreports_civicrm_enable() {
  _customreports_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *

 // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function customreports_civicrm_navigationMenu(&$menu) {
  _customreports_civix_insert_navigation_menu($menu, 'Reports', array(
    'label' => E::ts('Greenpeace reports'),
    'name' => 'Greenpeace reports',
    'navID' => _customreports_navhelper_create_unique_nav_id($menu),
  ));

  _customreports_civix_insert_navigation_menu($menu, 'Reports/Greenpeace reports', array(
    'label' => E::ts('Monthly report'),
    'name' => 'monthly_report',
    'url' => 'civicrm/report/greenpeace/monthly',
    'navID' => _customreports_navhelper_create_unique_nav_id($menu),
  ));

  _customreports_civix_navigationMenu($menu);
}

/**
 * Helper function for civicrm_navigationMenu
 *
 * Will create a new, unique ID for the navigation menu
 */
function _customreports_navhelper_create_unique_nav_id($menu) {
  $max_stored_navId = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
  $max_current_navId = _customreports_navhelper_get_max_nav_id($menu);
  return max($max_stored_navId, $max_current_navId) + 1;
}

/**
 * Helper function for civicrm_navigationMenu
 *
 * Will find the (currently) highest nav_item ID
 */
function _customreports_navhelper_get_max_nav_id($menu) {
  $max_id = 1;
  foreach ($menu as $entry) {
    $max_id = (isset($entry['attributes']['navID']) ? max($max_id, $entry['attributes']['navID']) : 0);
    if (!empty($entry['child'])) {
      $max_id_children = _customreports_navhelper_get_max_nav_id($entry['child']);
      $max_id = max($max_id, $max_id_children);
    }
  }
  return $max_id;
}
