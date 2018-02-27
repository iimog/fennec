# -*- coding: utf-8 -*-
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import Select
from selenium.common.exceptions import NoSuchElementException
from selenium.common.exceptions import NoAlertPresentException
import unittest, time, re, os

### INSTALLATION:
# conda create --name selenium python=2
# conda install selenium
# # download 'geckodriver' https://github.com/mozilla/geckodriver/releases and add to PATH
# # now run
# source activate selenium
# python take_screenshot_selenium.py

class Test(unittest.TestCase):
    def setUp(self):
        self.driver = webdriver.Firefox()
        self.driver.implicitly_wait(10)
        self.base_url = "http://localhost:3141"
        self.verificationErrors = []
        self.accept_next_alert = True

    def test_(self):
        driver = self.driver
        driver.get(self.base_url + "/default_data/startpage")
        driver.get_screenshot_as_file("screenshots/startpage.png")
        driver.find_element_by_link_text("Home").click()
        driver.get(self.base_url + "/login")
        driver.find_element_by_id("username").clear()
        driver.find_element_by_id("username").send_keys("demo")
        driver.find_element_by_id("password").clear()
        driver.find_element_by_id("password").send_keys("demo")
        driver.find_element_by_css_selector("input[type=\"submit\"]").click()
        driver.find_element_by_link_text("Projects").click()
        driver.find_element_by_xpath("//input[@type='search']").clear()
        driver.get_screenshot_as_file("screenshots/projects.png")
        driver.find_element_by_id("project-fileupload").clear()
        # dir_path = os.path.dirname(os.path.realpath(__file__))
        # driver.find_element_by_id("project-fileupload").send_keys(dir_path + "/beta/J.biom")
        driver.find_element_by_link_text("No Table ID").click()
        driver.find_element_by_id("project-overview-table-id")
        driver.find_element_by_xpath("//*[contains(text(),'1002')]")
        time.sleep(1)
        driver.get_screenshot_as_file("screenshots/project_details.png")
#        driver.find_element_by_link_text("Mapping").click()
#        driver.find_element_by_xpath("(//button[@type='button'])[8]").click()
#        driver.find_element_by_link_text("OTUs").click()
#        driver.find_element_by_xpath("(//button[@type='button'])[10]").click()
#        driver.find_element_by_xpath("//div[@id='mapping']/div/div[2]/div[2]/div[3]/div/ul/li[3]/a/span").click()
#        Select(driver.find_element_by_id("mapping-metadata-observation-select")).select_by_visible_text("ncbi_taxid")
#        driver.find_element_by_xpath("(//button[@type='button'])[11]").click()
#        driver.find_element_by_link_text("NCBI taxid").click()
#        driver.find_element_by_id("mapping-action-button").click()
#        time.sleep(1)
#        driver.get_screenshot_as_file("screenshots/project_mapping.png")
        driver.find_element_by_xpath("(//a[contains(text(),'Traits')])[2]").click()
        time.sleep(1)
        driver.get_screenshot_as_file("screenshots/project_traits.png")
        driver.find_element_by_id("inspect-with-phinch-tab").click()
        driver.switch_to.frame(driver.find_element_by_tag_name("iframe"))
        driver.find_element_by_id("goGallery").click()
        time.sleep(1)
        driver.find_element_by_css_selector("div.col3 > img").click()
        Select(driver.find_element_by_id("trait_dropdown")).select_by_visible_text("Plant Habit")
        time.sleep(2)
        driver.switch_to.default_content()
        driver.get_screenshot_as_file("screenshots/project_phinch.png")
        driver.find_element_by_link_text("Organisms").click()
        driver.find_element_by_id("search_organism").clear()
        driver.get_screenshot_as_file("screenshots/organisms.png")
        driver.find_element_by_id("search_organism").send_keys("Bellis")
        driver.find_element_by_id("btn_search_organism").click()
        driver.find_element_by_xpath("//div[@id='page-wrapper']/div[5]/div/div[2]/div/div/a[2]/span/i").click()
        driver.find_element_by_xpath("(//a[contains(text(),'Traits')])[2]").click()
        driver.find_element_by_id("toggleCitationButton").click()
        driver.find_element_by_link_text("Taxonomy").click()
        driver.get(self.base_url + "/1.0/trait/overview")
        driver.get(self.base_url + "css=div.clearfix")

    def is_element_present(self, how, what):
        try: self.driver.find_element(by=how, value=what)
        except NoSuchElementException as e: return False
        return True

    def is_alert_present(self):
        try: self.driver.switch_to_alert()
        except NoAlertPresentException as e: return False
        return True

    def close_alert_and_get_its_text(self):
        try:
            alert = self.driver.switch_to_alert()
            alert_text = alert.text
            if self.accept_next_alert:
                alert.accept()
            else:
                alert.dismiss()
            return alert_text
        finally: self.accept_next_alert = True

    def tearDown(self):
        self.driver.quit()
        self.assertEqual([], self.verificationErrors)

if __name__ == "__main__":
    unittest.main()
