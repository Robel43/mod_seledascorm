@mod @mod_seledascorm
Feature: Scorm availability
  In order to control when a SELEDASCORM activity is available to students
  As a teacher
  I need be able to set availability dates for the SELEDASCORM

  Background:
    Given the following "users" exist:
      | username | firstname  | lastname  | email                |
      | student1 | Student    | 1         | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user      | course | role    |
      | student1  | C1     | student |
    And the following "activities" exist:
      | activity | course | name          | packagefilepath                                | timeopen      | timeclose     |
      | seledascorm    | C1     | Past SELEDASCORM    | mod/seledascorm/tests/packages/singlesco_seledascorm12.zip | ##yesterday## | ##yesterday## |
      | seledascorm    | C1     | Current SELEDASCORM | mod/seledascorm/tests/packages/singlesco_seledascorm12.zip | ##yesterday## | ##tomorrow##  |
      | seledascorm    | C1     | Future SELEDASCORM  | mod/seledascorm/tests/packages/singlesco_seledascorm12.zip | ##tomorrow##  | ##tomorrow##  |

  Scenario: Scorm activity with dates in the past should not be available.
    When I am on the "Past SELEDASCORM" "seledascorm activity" page logged in as "student1"
    Then the activity date in "Past SELEDASCORM" should contain "Opened:"
    And the activity date in "Past SELEDASCORM" should contain "##yesterday noon##%A, %d %B %Y, %I:%M##"
    And the activity date in "Past SELEDASCORM" should contain "Closed:"
    And the activity date in "Past SELEDASCORM" should contain "##yesterday noon##%A, %d %B %Y, %I:%M##"
    And "Enter" "button" should not exist
    And I should not see "Preview"
    And I am on the "Current SELEDASCORM" "seledascorm activity" page
    And the activity date in "Current SELEDASCORM" should contain "Opened:"
    And the activity date in "Current SELEDASCORM" should contain "##yesterday noon##%A, %d %B %Y, %I:%M##"
    And the activity date in "Current SELEDASCORM" should contain "Closes:"
    And the activity date in "Current SELEDASCORM" should contain "##tomorrow noon##%A, %d %B %Y, %I:%M##"
    And "Enter" "button" should exist
    And I should see "Preview"
    And I am on the "Future SELEDASCORM" "seledascorm activity" page
    And the activity date in "Future SELEDASCORM" should contain "Opens:"
    And the activity date in "Future SELEDASCORM" should contain "##tomorrow noon##%A, %d %B %Y, %I:%M##"
    And the activity date in "Future SELEDASCORM" should contain "Closes:"
    And the activity date in "Future SELEDASCORM" should contain "##tomorrow noon##%A, %d %B %Y, %I:%M##"
    And "Enter" "button" should not exist
    And I should not see "Preview"
