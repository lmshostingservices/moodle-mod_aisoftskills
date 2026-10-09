@mod @mod_aisoftskills @javascript
Feature: A third, very poor response and traffic-light indicators
  In order to practise harder decisions
  As a learner
  I need a very poor response that makes things much worse, with indicators that show red, amber or green

  Background:
    Given the following "courses" exist:
      | fullname        | shortname |
      | Leadership 101  | LD101     |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | LD101  | editingteacher |
      | student1 | LD101  | student        |
    And the following "activities" exist:
      | activity     | course | idnumber | name             | level      | industry | practicemode | testmode | kpiamber | kpigreen |
      | aisoftskills | LD101  | ss1      | Leading the team | supervisor | retail   | 1            | 0        | 30       | 60       |
    And the following config values are set as admin:
      | name        | value                                         | plugin           |
      | unlockstate | {"status":"unlocked","checkedat":1790000000} | mod_aisoftskills |
    And the following "mod_aisoftskills > scenes" exist:
      | activity | title            | better                    | poorer    | worst                            |
      | ss1      | Behind on target | What would help you most? | Hurry up! | You are all useless, stay late. |

  Scenario: The very poor response makes things much worse, turns the indicator red, and stays crossed out
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "student1"
    And I click on "Start" "button"
    Then I should see "C" in the ".ss-options" "css_element"
    And I click on "You are all useless, stay late." "button"
    And I should see "That made things much worse." in the ".ss-consequence" "css_element"
    And I click on "Try another response" "button" in the ".ss-consequence" "css_element"
    And ".ss-kpichip.is-red" "css_element" should exist
    And the ".ss-option.is-tried" "css_element" should be disabled
    And I click on "Hurry up!" "button"
    And I should see "That didn't go well." in the ".ss-consequence" "css_element"
    And I click on "Try another response" "button" in the ".ss-consequence" "css_element"
    And I click on "What would help you most?" "button"
    And I click on "See my results" "button" in the ".ss-consequence" "css_element"
    And I click on "Next" "button" in the ".ss-slidenav" "css_element"
    And I should see "This one made things much worse"

  Scenario: A teacher sees the indicator colour bands in the activity settings
    When I am on the "Leading the team" "aisoftskills activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then I should see "Indicator colours"
    And the field "kpiamber" matches value "30"
    And the field "kpigreen" matches value "60"
