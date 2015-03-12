# language: en
@mink:my_session_selenium
Feature: I would like to log in to the system

  Scenario: Log in as admin
    Given I go to "/login"
     Then the response should contain "behatFormLogin"
     Then I should not see "Logged in as admin"
      And I fill in "username" with "admin"
      And I fill in "password" with "admin"
      And I press "Connexion"
     Then I should see "Logged in as admin"
     Then the response should not contain "behatFormLogin"
     When I wait for 2 seconds
      And I click on ".connexion-my-account"
     When I follow "behatLinkProfile"
     Then the response should contain "user_from"
     When I wait for 2 seconds
     And I click on ".connexion-my-account"
     When I follow "behatLinkUsers"
     Then the response should contain "grid_customer_wrapper"
     When I follow "logout"
     Then I should not see "Logged in as admin"
     Then the response should contain "form-connexion"

  Scenario: Unsuccessful login
    Given I go to "/login"
     Then the response should contain "behatFormLogin"
     Then I should not see "Logged in as admin"
      And I fill in "username" with "wrong username"
      And I fill in "password" with "wrong password"
      And I press "Connexion"
     Then I should see "Bad credentials"
     Then the response should contain "behatFormLogin"
