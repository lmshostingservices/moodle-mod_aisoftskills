@mod @mod_aisoftskills
Feature: Teachers build AI Soft Skills scenes and learners open them
  In order to practise soft skills in realistic workplace moments
  As a teacher
  I need to build scenes that my learners can open, while learners cannot reach the teacher pages

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
      | activity     | course | idnumber | name             | intro                  | level      | industry |
      | aisoftskills | LD101  | ss1      | Leading the team | Practise motivating.   | supervisor | retail   |
    And the following config values are set as admin:
      | name        | value                                         | plugin           |
      | unlockstate | {"status":"unlocked","checkedat":1790000000} | mod_aisoftskills |

  Scenario: A teacher opening an empty activity is taken to the scene builder
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "teacher1"
    Then I should see "Which industry do your learners work in?"
    And I should see "Which career level are they practising?"
    And I should see "Which soft skills should the scenes practise?"

  Scenario: A teacher saves the builder choices and gets a prompt that uses them
    Given I am on the "Leading the team" "mod_aisoftskills > Builder" page logged in as "teacher1"
    When I set the field "Hospitality" to "1"
    And I set the field "Manager" to "1"
    And I set the field "Resolving conflict" to "1"
    And I set the field "Spanish" to "1"
    And I press "Next: Create the scenes"
    And I should see "Step 5 of 8"
    Then I should see "Copy prompt"
    And I should see "LMS Labs AI scene writing is not available on this site"
    And I should see "Creating scenes needs this site's LMS Labs connection"
    And I should see "Hospitality"
    And I should see "Resolving conflict"
    And I should see "Industry: Hospitality" in the "#ss-lessonprompt" "css_element"
    And I should see "Career level of the learner: Manager" in the "#ss-lessonprompt" "css_element"
    And I should see "write every title, context, question, response, consequence and reason in Spanish" in the "#ss-lessonprompt" "css_element"

  Scenario: A learner opening an empty activity is told it is not ready
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "student1"
    Then I should see "Your teacher is still preparing this activity."
    And I should not see "Set up the lesson"

  Scenario: A learner sees the career ladder and can start once a scene is ready
    Given the following "mod_aisoftskills > scenes" exist:
      | activity | title             | better                                                       | poorer     |
      | ss1      | Behind on target  | Is there anything I can get you to help you reach your goals faster? | Hurry up! |
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "student1"
    Then I should see "You are the Supervisor"
    And I should see "Retail"
    And "Start" "button" should exist
    And "Reports" "link" should not exist in current page administration

  Scenario: A teacher edits a scene and its two responses
    Given the following "mod_aisoftskills > scenes" exist:
      | activity | title            |
      | ss1      | Behind on target |
    And I am on the "Leading the team" "mod_aisoftskills > Scenes" page logged in as "teacher1"
    And I should see "Two responses ready"
    And I should see "Next: Finish"
    When I click on "Edit scene" "link"
    And I set the following fields to these values:
      | Scene title | Short-staffed Friday |
    And I press "Save changes"
    Then I should see "Scene saved."
    And I should see "Short-staffed Friday"

  Scenario: The editor refuses a better response that lowers the indicator
    Given the following "mod_aisoftskills > scenes" exist:
      | activity | title            |
      | ss1      | Behind on target |
    And I am on the "Leading the team" "mod_aisoftskills > Scenes" page logged in as "teacher1"
    When I click on "Edit scene" "link"
    And I set the field "kpidelta0" to "-10"
    And I press "Save changes"
    Then I should see "The better response must raise the indicator."

  Scenario: The set-up path opens at the step it is up to and only moves forward when the step is done
    Given the following "mod_aisoftskills > scenes" exist:
      | activity | title            | picture |
      | ss1      | Behind on target | none    |
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "teacher1"
    And I navigate to "Set up the lesson" in current page administration
    Then I should see "Create a picture for every scene"
    And I should see "Scenes still without a picture: 1."
    And the "Next: Check the scenes" "button" should be disabled
    And "Next: Check the scenes" "link" should not exist
    And I should see "Upload my own picture"
    And I click on "Back" "link" in the ".ss-setupnav" "css_element"
    And I should see "Use an AI assistant"
    And I should see "Step 5 of 8"

  Scenario: A teacher can open the reports
    Given the following "mod_aisoftskills > scenes" exist:
      | activity | title            |
      | ss1      | Behind on target |
    When I am on the "Leading the team" "mod_aisoftskills > Report" page logged in as "teacher1"
    Then I should see "Sam Student"
    And I should see "Best score"
    And I click on "Scenes" "link" in the ".ss-tabs" "css_element"
    And I should see "Behind on target"
    And I should see "Better first choice"

  Scenario: Nothing can be set up or played until AI Soft Skills is activated on the site
    Given the following config values are set as admin:
      | name        | value               | plugin           |
      | unlockstate | {"status":"locked"} | mod_aisoftskills |
    And the following "mod_aisoftskills > scenes" exist:
      | activity | title            |
      | ss1      | Behind on target |
    When I am on the "Leading the team" "aisoftskills activity" page logged in as "student1"
    Then I should see "This activity is not available yet."
    And "Start" "button" should not exist
    And I am on the "Leading the team" "mod_aisoftskills > Builder" page logged in as "teacher1"
    And I should see "Ask your site administrator to activate it"
    And I should not see "Which industry do your learners work in?"
