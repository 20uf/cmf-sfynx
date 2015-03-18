# language: en
@mink:my_session_selenium
Feature: I would like to log in to the system

    Background: Log in as admin
      Given I am on "/en/"
       Then the response should contain "form-connexion"
        And I fill in "_username" with "admin"
        And I fill in "_password" with "admin"
        And I press "OK"
       When I wait for 4 seconds

    Scenario: Create a new cmf page
      Given I am logged as "admin"
        And I click on ".menu-xp"
       When I wait for 2 seconds
        And I click on ".page_action_copy"
       When I wait for 2 seconds
       Then I register the new page

    Scenario: Edit the new cmf page
      Given I go to the new page
        And I click on ".menu-xp"
       When I wait for 2 seconds
        And I click on ".page_action_edit"
       When I wait for 6 seconds
       Then I switch to iframe "modalIframeId"
       When I click on the element with xpath "//*[contains(@id,'tabs')]//form[@class='myform']//div[@id='piapp_adminbundle_pagetype']//fieldset//div[4]//button"
       When I wait for 2 seconds
       Then I switch to main window
       Then I switch to iframe "modalIframeId"
       When I click on the element with xpath "//body//label[contains(@for,'ui-multiselect-piapp_adminbundle_pagetype_layout-option-13')]//span"
       When I wait for 2 seconds
        And I press "Save"
       When I wait for 2 seconds
       Then I switch to main window
       When I wait for 2 seconds   
       When I click on the element with xpath "//body//button[contains(@class,'ui-dialog-titlebar-close')]//span"
       When I wait for 2 seconds 
       Then I register the new page  

    Scenario: Create a new bloc
      Given I go to the new page
        And I click on ".menu-xp"
       When I wait for 2 seconds
        And I click on ".veneer_blocks_widgets"
       When I wait for 2 seconds
       When I click on the element with xpath "//*[contains(@class,'block_action_import')][contains(@data-id,'3')]"
       When I wait for 2 seconds