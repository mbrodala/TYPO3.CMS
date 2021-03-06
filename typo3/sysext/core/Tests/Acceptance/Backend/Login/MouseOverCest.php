<?php
namespace TYPO3\CMS\Core\Tests\Acceptance\Backend\Login;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Tests\Acceptance\Step\Backend\Kasper;

/**
 * Acceptance test
 */
class MouseOverCest
{

    /**
     * Call backend login page and verify login button changes color on mouse over,
     * verifies page is available and CSS is properly loaded.
     *
     * @param Kasper $I
     */
    public function tryToTest(Kasper $I)
    {
        $I->wantTo('check login functions');
        $I->amOnPage('/typo3/index.php');
        $I->waitForElement('#t3-username', 10);
        $I->wantTo('mouse over css change login button');

        // Make sure mouse is not over submit button from a previous test
        $I->moveMouseOver('#t3-username');
        $bs = $I->executeInSelenium(function (\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
            return $webdriver->findElement(\WebDriverBy::cssSelector('#t3-login-submit'))->getCSSValue('box-shadow');
        });

        $I->moveMouseOver('#t3-login-submit');
        $I->wait(1);
        $bsmo = $I->executeInSelenium(function (\Facebook\WebDriver\Remote\RemoteWebDriver $webdriver) {
            return $webdriver->findElement(\WebDriverBy::cssSelector('#t3-login-submit'))->getCSSValue('box-shadow');
        });
        $I->assertFalse($bs === $bsmo);
    }
}
