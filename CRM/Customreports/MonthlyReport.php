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
   * @var array $_custom_fields_interests
   *   Custom fields to include in the report (IS NOT NULL).
   */
  protected static $_custom_fields_interests = array(
    1 => 'politik_1',
    2 => 'wirtschaft_2',
    3 => 'gesellschaft_3',
    4 => 'medien_4',
  );

  /**
   * @var array $_custom_field_values_engagement
   *   Field values of custom field "Engagement" wo onclude in the report.
   */
  protected static $_custom_field_values_engagement = array(
    1,
    2,
  );

  /**
   * @var array $_custom_field_values_relevance
   *   Field values of custom field "Relevance" wo onclude in the report.
   */
  protected static $_custom_field_values_relevance = array(
    1,
    2,
  );

  /**
   * @var array $_activity_type_ids
   *   Activity types to include in the report.
   */
  protected static $_activity_type_ids = array(
    1,
    55,
    2,
  );

  /**
   * @var \DateTime $startDate
   *   Start date of range to generate the report for.
   */
  protected $startDate;

  /**
   * @var \DateTime $endDate
   *   End date of range to generate the report for.
   */
  protected $endDate;

  /**
   * @var array $report
   *   Array matrix of the report.
   */
  protected $report = array();

  /**
   * @var array $reportColumns
   *   Column names for the report.
   */
  protected $reportColumns = array();

  /**
   * Contact ID of the Greenpeace organization that associates have as employer.
   */
  const GREENPEACE_ORGANISATION_CONTACT_ID = 1;

  /**
   * Custom field ID "Campaign" on activities.
   */
  const CUSTOM_FIELD_CAMPAIGNS_ACTIVITIES = 21;

  /**
   * Relationship ID of type "Primary contacts".
   */
  const RELATIONSHIP_TYPE_ID_PRIMARY_CONTACTS = 12;

  /**
   * Relationship ID of type "Secondary contacts".
   */
  const RELATIONSHIP_TYPE_ID_SECONDARY_CONTACTS = 13;

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
   * Retrieves report data and sets $this->report and $this->reportColumns.
   *
   * @throws \Exception
   */
  public function generateReport() {
    /***************************************************************************
     * Report columns.                                                         *
     **************************************************************************/
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

    /***************************************************************************
     * Report rows.                                                            *
     **************************************************************************/
    $report = array(
      array('Monatsbericht'),
      array('') + $report_columns,
    );

    /**
     * Row 0.1: Contacts total: Distinct number of contacts not being GP
     * associates.
     * -------------------------------------------------------------------------
     */
    $contacts_total_record = array(
      'Contacts total',
    );
    // Contacts total.
    $query =
      "SELECT
         COUNT(DISTINCT c.id)
       FROM
         civicrm_contact c
       WHERE
         c.id NOT IN (" . implode(',', array_keys($associates)) . ")";
    $contacts_total_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    $report[] = $contacts_total_record;

    /**
     * Row 0.2: Individuals total: Distinct number of individual contacts not
     * being GP associates.
     * -------------------------------------------------------------------------
     */
    $individuals_total_record = array(
      'Individuals total',
    );
    // Individuals total.
    $query =
      "SELECT
         COUNT(DISTINCT c.id)
       FROM
         civicrm_contact c
       WHERE
         c.id NOT IN (" . implode(',', array_keys($associates)) . ")
         AND c.contact_type = 'Individual'";
    $individuals_total_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    $report[] = $individuals_total_record;

    /**
     * Row 0.3: Organizations total: Distinct number of organization contacts
     * not being GP associates.
     * -------------------------------------------------------------------------
     */
    $organizations_total_record = array(
      'Organizations total',
    );
    // Individuals total.
    $query =
      "SELECT
         COUNT(DISTINCT c.id)
       FROM
         civicrm_contact c
       WHERE
         c.id NOT IN (" . implode(',', array_keys($associates)) . ")
         AND c.contact_type = 'Organization'";
    $organizations_total_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    $report[] = $organizations_total_record;

    /**
     * Row 4: Primary contacts: Distinct number of contacts with relationship of
     * type "Primary contact" with GP associate.
     * -------------------------------------------------------------------------
     */
    $primary_contacts_record = array(
      'Primary contacts',
    );
    // Primary contacts all associates.
    $query =
      "SELECT
         COUNT( DISTINCT r.contact_id_a)
         FROM
           civicrm_relationship r
         WHERE
           r.is_active = 1
           AND r.relationship_type_id = " . self::RELATIONSHIP_TYPE_ID_PRIMARY_CONTACTS . "
           AND r.contact_id_b IN (" . implode(',', array_keys($associates)) . ")
           AND (
               r.start_date >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
               OR r.start_date IS NULL
           )
           AND (
               r.end_date < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
               OR r.end_date IS NULL
           )";
    $primary_contacts_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Primary contacts per associate.
    foreach ($associates as $associate) {
      $query =
        "SELECT
         COUNT( DISTINCT r.contact_id_a)
         FROM
           civicrm_relationship r
         WHERE
           r.is_active = 1
           AND r.relationship_type_id = " . self::RELATIONSHIP_TYPE_ID_PRIMARY_CONTACTS . "
           AND r.contact_id_b = {$associate['id']}
           AND (
               r.start_date >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
               OR r.start_date IS NULL
           )
           AND (
               r.end_date < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
               OR r.end_date IS NULL
           )";
      $primary_contacts_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    $report[] = $primary_contacts_record;

    /**
     * Row 5: Secondary contacts: Distinct number of contacts with relationship
     * of type "Secondary contact" with GP associate.
     * -------------------------------------------------------------------------
     */
    $secondary_contacts_record = array(
      'Secondary contacts',
    );

    // Secondary contacts all associates.
    $query =
      "SELECT
         COUNT( DISTINCT r.contact_id_a)
         FROM
           civicrm_relationship r
         WHERE
           r.is_active = 1
           AND r.relationship_type_id = " . self::RELATIONSHIP_TYPE_ID_SECONDARY_CONTACTS . "
           AND r.contact_id_b IN (" . implode(',', array_keys($associates)) . ")
           AND (
               r.start_date >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
               OR r.start_date IS NULL
           )
           AND (
               r.end_date < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
               OR r.end_date IS NULL
           )";
    $secondary_contacts_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Secondary contacts per associate.
    foreach ($associates as $associate) {
      $query =
        "SELECT
         COUNT( DISTINCT r.contact_id_a)
         FROM
           civicrm_relationship r
         WHERE
           r.is_active = 1
           AND r.relationship_type_id = " . self::RELATIONSHIP_TYPE_ID_SECONDARY_CONTACTS . "
           AND r.contact_id_b = {$associate['id']}
           AND (
               r.start_date >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
               OR r.start_date IS NULL
           )
           AND (
               r.end_date < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
               OR r.end_date IS NULL
           )";
      $secondary_contacts_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    $report[] = $secondary_contacts_record;

    /**
     * Rows 6 to 9: Contacts with interests: Distinct number of contacts with at
     * least one interest in each custom field with activities with GP
     * associates involved.
     * -------------------------------------------------------------------------
     */
    foreach (self::$_custom_fields_interests as $custom_field_id => $custom_field_name) {
      $custom_field_definition = civicrm_api3('CustomField', 'getsingle', array(
        'return' => array('label'),
        'id' => $custom_field_id,
      ));
      $custom_field_record = array(
        $custom_field_definition['label'],
      );

      // Contacts with interests per custom field all associates.
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = c_sh.id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND (
             z.{$custom_field_name} IS NOT NULL
             AND z.{$custom_field_name} != ''
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id IN (" . implode(',', array_keys($associates)) . ")
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $custom_field_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

      // Contacts with interests per custom field per associate.
      foreach ($associates as $associate) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = c_sh.id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND (
             z.{$custom_field_name} IS NOT NULL
             AND z.{$custom_field_name} != ''
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id = {$associate['id']}
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $custom_field_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      // Contacts with interests per custom field per campaign.
      foreach ($campaigns as $campaign) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND (
             z.{$custom_field_name} IS NOT NULL
             AND z.{$custom_field_name} != ''
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $custom_field_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      $report[] = $custom_field_record;
    }

    /**
     * Rows 10 to 11: Contacts with relevance values: Distinct number of
     * contacts with specific value in relevance custom field with activities
     * with GP associates involved.
     * -------------------------------------------------------------------------
     */
    foreach (self::$_custom_field_values_relevance as $field_value) {
      $custom_field_definition = civicrm_api3('CustomField', 'getsingle', array(
        'return' => array('option_group_id'),
        'id' => 6,
      ));
      $custom_field_value_definition = civicrm_api3('OptionValue', 'getsingle', array(
        'return' => array('label'),
        'option_group_id' => $custom_field_definition['option_group_id'],
        'value' => $field_value,
      ));
      $custom_field_value_record = array(
        $custom_field_value_definition['label'],
      );

      // Contacts with relevance per custom field value all associates.
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = c_sh.id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND z.topinfluencer_6 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $field_value . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id IN (" . implode(',', array_keys($associates)) . ")
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $custom_field_value_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

      // Contacts with relevance per custom field value per associate.
      foreach ($associates as $associate) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = c_sh.id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND z.topinfluencer_6 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $field_value . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id = {$associate['id']}
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $custom_field_value_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      // Contacts with relevance per custom field value per campaign.
      foreach ($campaigns as $campaign) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND z.topinfluencer_6 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $field_value . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $custom_field_value_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      $report[] = $custom_field_value_record;
    }

    /**
     * Rows 12 to 13: Contacts with engagement values: Distinct number of
     * contacts with specific value in engagement custom field with activities
     * with GP associates involved.
     * -------------------------------------------------------------------------
     */
    foreach (self::$_custom_field_values_engagement as $field_value) {
      $custom_field_definition = civicrm_api3('CustomField', 'getsingle', array(
        'return' => array('option_group_id'),
        'id' => 5,
      ));
      $custom_field_value_definition = civicrm_api3('OptionValue', 'getsingle', array(
        'return' => array('label'),
        'option_group_id' => $custom_field_definition['option_group_id'],
        'value' => $field_value,
      ));
      $custom_field_value_record = array(
        $custom_field_value_definition['label'],
      );

      // Contacts with engagement per custom field value all associates.
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = c_sh.id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND z.engagement_5 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $field_value . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id IN (" . implode(',', array_keys($associates)) . ")
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $custom_field_value_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

      // Contacts with engagement per custom field value per associate.
      foreach ($associates as $associate) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = c_sh.id
         WHERE
           ac_sh.record_type_id = 3
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND z.engagement_5 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $field_value . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id = {$associate['id']}
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $custom_field_value_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      // Contacts with engagement per custom field value per campaign.
      foreach ($campaigns as $campaign) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_zusatzinformationen_1 z
           ON z.entity_id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND z.engagement_5 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $field_value . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $custom_field_value_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      $report[] = $custom_field_value_record;
    }

    /**
     * Row 14: Activities total: Distinct number of activities with stakeholders
     * and GP associates involved.
     * -------------------------------------------------------------------------
     */
    $activities_total_record = array(
      'Activities total',
    );

    // Activities with stakeholders and GP associates involved all associates.
    $query =
      "SELECT
         COUNT(DISTINCT ac_sh.activity_id)
       FROM
         civicrm_activity_contact ac_sh
       LEFT JOIN
         civicrm_contact c_sh
         ON c_sh.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
             AND ac_gp.record_type_id IN(2,3)
         )
         AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
         AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
    $activities_total_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Activities with stakeholders and GP associates involved per associate.
    foreach ($associates as $associate) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.activity_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
               AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $activities_total_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    // Activities with stakeholders and GP associates involved per campaign.
    foreach ($campaigns as $campaign) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.activity_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
               AND ac_gp.record_type_id IN(2,3)
           )
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $activities_total_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    $report[] = $activities_total_record;

    /**
     * Rows 15 to 17: Activities with activity types: Distinct number of
     * activities with specific activity type with stakeholders and GP
     * associates involved.
     * -------------------------------------------------------------------------
     */
    foreach (self::$_activity_type_ids as $activity_type_id) {
      $activity_type_definition = civicrm_api3('OptionValue', 'getsingle', array(
        'return' => array('label'),
        'option_group_id' => 'activity_type',
        'value' => $activity_type_id,
      ));
      $activities_type_record = array(
        $activity_type_definition['label'],
      );

      // Activities with stakeholders and GP associates involved all associates.
      $query =
        "SELECT
         COUNT(DISTINCT ac_sh.activity_id)
       FROM
         civicrm_activity_contact ac_sh
       LEFT JOIN
         civicrm_contact c_sh
         ON c_sh.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
             AND ac_gp.record_type_id IN(2,3)
         )
         AND a.activity_type_id = " . $activity_type_id . "
         AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
         AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
         AND a.activity_type_id != 3";
      $activities_type_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

      // Activities with stakeholders and GP associates involved per associate.
      foreach ($associates as $associate) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.activity_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
               AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_type_id = " . $activity_type_id . "
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $activities_type_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      // Activities with stakeholders and GP associates involved per campaign.
      foreach ($campaigns as $campaign) {
        $query =
          "SELECT
           COUNT(DISTINCT ac_sh.activity_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
               AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_type_id = " . $activity_type_id . "
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
        $activities_type_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
      }

      $report[] = $activities_type_record;
    }

    /**
     * Row 1: Stakeholder total: Distinct number of contacts with activities
     * with GP associates involved.
     * -------------------------------------------------------------------------
     */
    $stakeholder_record = array(
      'Stakeholder total',
    );
    // Stakeholder total all associates.
    $query =
      "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
    $stakeholder_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Stakeholder total per associate.
    foreach ($associates as $associate) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
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
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $stakeholder_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    // Stakeholder total per campaign.
    foreach ($campaigns as $campaign) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         WHERE
           ac_sh.record_type_id = 3
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $stakeholder_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    $report[] = $stakeholder_record;

    /**
     * Row 2: Organizations: Distinct number of Organizations with activities
     * with GP associates involved.
     * -------------------------------------------------------------------------
     */
    $organizations_record = array(
      'Organizations',
    );

    // Organizations all associates.
    $query =
      "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND c_sh.contact_type = 'Organization'
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id IN (" . implode(',', array_keys($associates)) . ")
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
    $organizations_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Organizations per associate.
    foreach ($associates as $associate) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND c_sh.contact_type = 'Organization'
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id = {$associate['id']}
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) ."'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $organizations_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    // Organizations per campaign.
    foreach ($campaigns as $campaign) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND c_sh.contact_type = 'Organization'
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $organizations_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    $report[] = $organizations_record;

    /**
     * Row 3: Individuals: Distinct number of Individuals with activities with
     * GP associates involved.
     * -------------------------------------------------------------------------
     */
    $individuals_record = array(
      'Individuals',
    );

    // Individuals all associates.
    $query =
      "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND c_sh.contact_type = 'Individual'
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id IN (" . implode(',', array_keys($associates)) . ")
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
    $individuals_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();

    // Individuals per associate.
    foreach ($associates as $associate) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id)
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND c_sh.contact_type = 'Individual'
           AND (
             c_sh.employer_id != 1
             OR c_sh.employer_id IS NULL
           )
           AND EXISTS(
               SELECT * FROM civicrm_activity_contact ac_gp
               WHERE
                 ac_gp.activity_id = ac_sh.activity_id
                 AND ac_gp.contact_id = {$associate['id']}
      	         AND ac_gp.record_type_id IN(2,3)
           )
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $individuals_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    // Individuals per campaign.
    foreach ($campaigns as $campaign) {
      $query =
        "SELECT
           COUNT(DISTINCT ac_sh.contact_id) AS sh_id
         FROM
           civicrm_activity_contact ac_sh
         LEFT JOIN
           civicrm_activity a
           ON a.id = ac_sh.activity_id
         LEFT JOIN
           civicrm_value_campaigns_7 avc
           ON avc.entity_id = ac_sh.activity_id
         LEFT JOIN
           civicrm_contact c_sh
           ON c_sh.id = ac_sh.contact_id
         WHERE
           ac_sh.record_type_id = 3
           AND c_sh.contact_type = 'Individual'
           AND avc.campaigns_21 LIKE '%" . CRM_Core_DAO::VALUE_SEPARATOR . $campaign['value'] . CRM_Core_DAO::VALUE_SEPARATOR . "%'
           AND a.activity_date_time >= '" . date('Y-m-d H:i:s', $this->startDate->getTimestamp()) . "'
           AND a.activity_date_time < '" . date('Y-m-d H:i:s', $this->endDate->getTimestamp()) . "'
           AND a.activity_type_id != 3";
      $individuals_record[] = CRM_Core_DAO::executeQuery($query)->fetchValue();
    }

    $report[] = $individuals_record;

    $this->reportColumns = $report_columns;
    $this->report = $report;
  }

  /**
   * Retrieves a list of Greenpeace associates.
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
   * Retrieves a list of campaigns available for activities (custom field
   * values).
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
      'option.limit' => 0,
    ));
    return $campaigns['values'];
  }

  /**
   * Generates a CSV file and disposes it for download.
   *
   * @param string $filename
   * @param string $delimiter
   */
  public function exportCSV($filename = 'export.csv', $delimiter=';') {
    header('Content-Disposition: attachment;filename="'.$filename.'";');
    header('Content-Encoding: UTF-8');
    header('Content-type: text/csv; charset=UTF-8');
    // Add UTF-8 BOM for Excel.
    echo "\xEF\xBB\xBF";
    $f = fopen('php://output', 'w');

    foreach ($this->report as $line) {
      // Fill incomplete records with empty string columns.
      $line += array_fill(count($line), count($this->reportColumns) - count($line), '');
      fputcsv($f, $line, $delimiter);
    }
    exit;
  }

}
