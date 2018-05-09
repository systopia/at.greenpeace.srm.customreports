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
   * @var \DateTime
   */
  protected $startDate;

  /**
   * @var \DateTime
   */
  protected $endDate;

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
   */
  public static function getAssociates() {
    // TODO: Contacts from group, tag, relationship?
  }

  /**
   * TODO.
   */
  public static function getActivities() {
    // TODO: Activity type(s)?
  }

  /**
   * TODO.
   */
  public static function getCampaigns() {
    // TODO: Campaign type(s)?
  }

}
