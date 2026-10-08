@mod @mod_aisoftskills
Feature: AI Soft Skills shows each role only the pages its capabilities allow
  In order to protect scene content and learner data
  As a site administrator
  I need the builder, scenes and reports to be offered only to the right roles

  Background:
    Given the following "courses" exist:
      | fullname       | shortname |
      | Leadership 101 | LD101     |
    And the following "users" exist:
      | username | firstname | lastname  |
      | teacher2 | Nia       | Assistant |
      | student1 | Sam       | Student   |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | LD101  | teacher |
      | student1 | LD101  | student |
    And the following "activities" exist:
      | activity     | course | idnumber | name             |
      | aisoftskills | LD101  | ss1      | Leading the team |
    And the following "mod_aisoftskills > scenes" exist:
      | activity | title            |
      | ss1      | Behind on target |

  Scenario: A learner is offered no teacher pages
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "student1"
    Then "Start" "button" should exist
    And "Reports" "link" should not exist in current page administration
    And "Set up the lesson" "link" should not exist in current page administration
    And "Scenes" "link" should not exist in current page administration

  Scenario: A non-editing teacher can see reports but cannot build or edit scenes
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "teacher2"
    Then "Reports" "link" should exist in current page administration
    And "Set up the lesson" "link" should not exist in current page administration
    And "Scenes" "link" should not exist in current page administration
    And I am on the "Leading the team" "mod_aisoftskills > Report" page
    And I should see "Sam Student"
