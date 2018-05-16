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
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Customreports_Form_MonthlyReport extends CRM_Core_Form {

  public function buildQuickForm() {

    // add form elements
    $this->add(
      'select',
      'time_period',
      'Time period',
      array(
        'current_year'      => E::ts('This year'),
        'last_year'         => E::ts('Last year'),
        'current_month'     => E::ts('This month'),
        'last_month'        => E::ts('Last month'),
        'customized_period' => E::ts('Choose date range'),
      ),
      TRUE
    );

    $this->addDateRange(
      'date_range',
      '_from',
      '_to',
      'From:',
      'searchDate',
      TRUE,
      FALSE
    );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    try {
      $values = $this->exportValues();

      // Convert date strings to timestamps.
      $values['date_range_from'] = date_create_from_format('!m/d/Y', $values['date_range_from'])
        ->getTimestamp();
      $values['date_range_to'] = date_create_from_format('!m/d/Y', $values['date_range_to'])
        ->modify('+1 day')
        ->getTimestamp();

      $export = new CRM_Customreports_MonthlyReport(
        $values['date_range_from'],
        $values['date_range_to']
      );
      $export->generateReport();

      parent::postProcess();
    }
    catch (Exception $exception) {
      CRM_Core_Session::setStatus(
        $exception->getMessage(),
        E::ts('An error occurred'),
        'no-popup'
      );
    }
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
