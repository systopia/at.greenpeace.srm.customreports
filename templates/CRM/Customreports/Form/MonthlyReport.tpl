{*-------------------------------------------------------+
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
+--------------------------------------------------------*}

<div class="description">
  {ts}This will generate a monthly report containing counts of contacts related to specific activities and Greenpeace associates.{/ts}
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-section">
  <div class="label">{$form.time_period.label}</div>
  <div class="content">{$form.time_period.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section custom-period">
  <div class="label">{$form.date_range_from.label}</div>
  <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=date_range_from}</div>
  <div class="clear"></div>
</div>

<div class="crm-section custom-period">
  <div class="label">{$form.date_range_to.label}</div>
  <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=date_range_to}</div>
  <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{literal}
<script type="text/javascript">
  cj(document).ready(function() {
    var time_period = cj('#time_period');
    var custom_period = cj('.custom-period');
    var current_year = (new Date).getFullYear();
    var current_month = (new Date).getMonth();
    var current_day = (new Date).getDate();
    var from = cj('#date_range_from');
    var to = cj('#date_range_to');
    var from_display = cj("[id^='date_range_from_display']");
    var to_display = cj("[id^='date_range_to_display']");

    var set_period = function () {
      switch (time_period.val()) {
        case "customized_period":
          from.val("");
          from_display.val("");
          to.val("");
          to_display.val("");
          custom_period.show();
          break;
        case "current_year":
          from_display.datepicker('setDate', new Date(current_year, 0, 1));
          to_display.datepicker('setDate', new Date(current_year, current_month, current_day));
          custom_period.hide();
          break;
        case "last_year":
          from_display.datepicker('setDate', new Date(current_year-1, 0, 1));
          to_display.datepicker('setDate', new Date(current_year-1, 11, 31));
          custom_period.hide();
          break;
        case 'current_month':
          from_display.datepicker('setDate', new Date(current_year, current_month, 1));
          to_display.datepicker('setDate', new Date(current_year, current_month, current_day));
          custom_period.hide();
          break;
        case 'last_month':
          from_display.datepicker('setDate', new Date(current_year, current_month - 1, 1));
          to_display.datepicker('setDate', new Date(current_year, current_month, 0));
          custom_period.hide();
          break;
      }
    };

    // Evaluate initial value.
    set_period();

    // on change.
    time_period.on('change', set_period);
  });
</script>
{/literal}
