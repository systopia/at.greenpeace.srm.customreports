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

use CRM_Customreports_ExtensionUtil as E;

/**
 * Class CRM_Customreports_MonthlyReport
 *
 * Generate and manage monthly reports.
 */
class CRM_Customreports_MonthlyReport {

  /**
   * @var array
   */
  protected static $_custom_fields_individuals = array(1, 2, 3, 4);

  /**
   * @var \DateTime
   */
  protected $startDate;

  /**
   * @var \DateTime
   */
  protected $endDate;

  /**
   * @var array
   */
  protected $report;

  /**
   * TODO.
   */
  const GREENPEACE_ORGANISATION_CONTACT_ID = 1;

  /**
   * TODO.
   */
  const CUSTOM_FIELD_CAMPAIGNS_ACTIVITIES = 21;

  /**
   * CRM_Customreports_MonthlyReport constructor.
   *
   * @param int | NULL $start_timestamp
   * @param int | NULL $end_timestamp
   * @param string $interval
   *   Either "month" or "year". The interval to use when at least
   *   $end_timestamp is not set.
   * @param int $offset
   *   The offset from the current date's point in time relative to $interval to
   *   use as the start date when it is not set. E.g. a value of 0 and $interval
   *   set to "month" is the current month. Defaults to -1, which corresponds to
   *   the previous month or year, depending on $interval.
   *
   * @throws Exception
   */
  public function __construct($start_timestamp = NULL, $end_timestamp = NULL, $interval = 'month', $offset = -1) {
    if (!isset($start_timestamp)) {
      $this->startDate = new DateTime('first day of this ' . $interval . ' 00:00:00');
      $this->startDate->modify((string) $offset . ' ' . $interval);
    }
    else {
      $this->startDate = new DateTime();
      $this->startDate->setTimestamp($start_timestamp);
    }

    if (!isset($end_timestamp)) {
      $this->endDate = clone $this->startDate;
      $this->endDate->modify('+1 ' . $interval);
    }
    else {
      $this->endDate = new DateTime();
      $this->endDate->setTimestamp($end_timestamp);
    }

    if ($this->startDate > $this->endDate) {
      throw new Exception(E::ts('Start date must be before end date.'));
    }
  }

  /**
   * TODO.
   *
   * @throws \Exception
   */
  public function generateReport() {
    /**
     * Report columns.
     */
    $report_columns = array(
      '',
      'Greenpeace',
    );

    // Add a column for each Greenpeace associate.
    $associates = static::getAssociates();
    foreach ($associates as $associate) {
      $report_columns[] = $associate['display_name'];
    }

    // Add a column for each relevant campaign.
    $campaigns = static::getCampaigns();
    foreach ($campaigns as $campaign) {
      $report_columns[] = $campaign['label'];
    }

    /**
     * Report rows.
     */
    $report = array(
      array('Monatsbericht'),
      array('') + $report_columns,
    );

    // Row 1:
    // Stakeholder total: Number of distinct contacts with activities with GP
    // associates involved.
    $stakeholder_record = array(
      'Stakeholder total',
    );
    // Stakeholder total all associates.
    // TODO: Respect date range.
    $query =
      "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id IN (" . implode(',', array_keys($associates)) . ")
      	         AND ac_gp.record_type_id = 3
           )";
    $stakeholder_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Stakeholder total per associate.
    foreach ($associates as $associate) {
      // TODO: Respect date range.
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id = {$associate['id']}
      	         AND ac_gp.record_type_id = 3
           )";
      $stakeholder_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    // Stakeholder total per campaign.
    foreach ($campaigns as $campaign) {
      // TODO: Respect date range.
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         WHERE
           ac_sh.record_type_id = 3
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'";
    }
    $stakeholder_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    $report[] = $stakeholder_record;

    // TODO: Row 2: Organizations

    // TODO: Row 3: Individuals

    // TODO: Row 4: Primary contacts

    // TODO: Row 5: Secondary contacts

    // TODO: Rows 6 to 13: A row for each relevant custom field
    foreach (self::$_custom_fields_individuals as $custom_field_id) {
      $custom_field_definition = civicrm_api3('CustomField', 'getsingle', array(
        'return' => array('label'),
        'id' => $custom_field_id,
      ));
      $custom_field_record = array(
        $custom_field_definition['label'],
        0, // Greenpeace total records
      );

      foreach ($associates as $associate) {
        // TODO: Query number of contacts with value in field and linked with associate (via activity).
        $custom_field_record[] = 0;
      }

      foreach ($campaigns as $campaign) {
        // TODO: Query number of contacts with value in field and linked with campaign (via activity).
      }

      $report[] = $custom_field_record;
    }

    // TODO: Row 14: Activities total

    // TODO: Rows 15 to 17: A row for each relevant activity type.

    $this->report = $report;
  }

  /**
   * TODO.
   *
   * @throws \Exception
   */
  public static function getAssociates() {
    $result = civicrm_api3('Contact', 'get', array(
      'return' => array('id', 'display_name'),
      'option.limit' => 0,
      'employer_id' => self::GREENPEACE_ORGANISATION_CONTACT_ID,
      'is_deleted' => 0,
    ));
    return $result['values'];
  }

  /**
   * TODO.
   *
   * @param $contact_id
   * @param $relationship_type
   */
  public static function getRelationships($contact_id, $relationship_type) {

  }

  /**
   * TODO.
   *
   * @return array
   */
  public static function getActivities() {
    // TODO: Activity type(s)?
    return array();
  }

  /**
   * TODO.
   *
   * @return array
   *
   * @throws \Exception
   */
  public static function getCampaigns() {
    // Get all multi-select options from custom field "Campaigns" on activities.
    $option_group = civicrm_api3('CustomField', 'getsingle', array(
      'return' => array('option_group_id'),
      'id' => self::CUSTOM_FIELD_CAMPAIGNS_ACTIVITIES,
    ));
    $campaigns = civicrm_api3('OptionValue', 'get', array(
      'sequential' => 1,
      'return' => array('label', 'value'),
      'option_group_id' => $option_group['option_group_id'],
    ));
    return $campaigns['values'];
  }

}
