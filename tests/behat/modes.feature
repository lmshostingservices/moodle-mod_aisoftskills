@mod @mod_aisoftskills @javascript
Feature: Practice and test, the role line, and the optional voiceover step
  In order to learn first and then show what they know
  As a learner
  I need to practise with a second try and take a test with one choice per scene

  Background:
    Given the following "courses" exist:
      | fullname        | shortname | enablecompletion |
      | Leadership 101  | LD101     | 1                |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tina      | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | LD101  | editingteacher |
      | student1 | LD101  | student        |
    And the following "activities" exist:
      | activity     | course | idnumber | name             | level      | industry | practicemode | testmode | passmark |
      | aisoftskills | LD101  | ss1      | Leading the team | supervisor | retail   | 1            | 1        | 100      |
    And the following config values are set as admin:
      | name        | value                                         | plugin           |
      | unlockstate | {"status":"unlocked","checkedat":1790000000} | mod_aisoftskills |
    And the following "mod_aisoftskills > scenes" exist:
      | activity | title            | better                       | poorer     |
      | ss1      | Behind on target | What would help you most?    | Hurry up!  |

  Scenario: A learner practises with a second try, then fails the test and is asked to take it again
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "student1"
    Then I should see "Practise"
    And I should see "Take the test"
    And I click on "Practise" "button"
    And I should see "As the supervisor, how would you handle this situation?"
    And I should see "The situation"
    And I click on "Hurry up!" "button"
    And I should see "Try the other response" in the ".ss-consequence" "css_element"
    And I click on "Try the other response" "button" in the ".ss-consequence" "css_element"
    And I click on "What would help you most?" "button"
    And I click on "See my results" "button" in the ".ss-consequence" "css_element"
    And I should see "Take the test" in the ".ss-summary-actions" "css_element"
    And I click on "Take the test" "button" in the ".ss-summary-actions" "css_element"
    And I click on "Hurry up!" "button"
    And "Try the other response" "button" should not exist in the ".ss-consequence" "css_element"
    And I click on "See my results" "button" in the ".ss-consequence" "css_element"
    And I should see "Not passed yet: the pass mark is 100%."
    And I should see "Take the test again"
    And I click on "Next" "button" in the ".ss-slidenav" "css_element"
    And I should see "Learn from this one"
    And I should see "What would help you most?" in the ".ss-better" "css_element"

  Scenario: The voiceover step is optional and says when voiceover is off
    When I am on the "Leading the team" "mod_aisoftskills > Scenes" page logged in as "teacher1"
    And I should see "Step 8 of 9"
    And I click on "Back" "link" in the ".ss-setupnav" "css_element"
    Then I should see "Step 7 of 9"
    And I should see "Voiceover is switched off on this site"
    And "Next: Check the scenes" "link" should exist
