<?php declare(strict_types=1);

/******************************************************************************
 *
 * This file is part of ILIAS, a powerful learning management system.
 *
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 *      https://www.ilias.de
 *      https://github.com/ILIAS-eLearning
 *
 *****************************************************************************/

use PHPUnit\Framework\TestCase;

class ilServicesGlobalCacheTest extends TestCase
{
//    private ?\ILIAS\DI\Container $dic_backup;
//
//    protected function setUp() : void
//    {
//      global $DIC;
//      $this->dic_backup = is_object($DIC) ? clone $DIC : $DIC;
//
//      $DIC = new Container();
//      $DIC['ilDB'] = $this->createMock(ilDBInterface::class);
//    }
//
//    protected function tearDown() : void
//    {
//        global $DIC;
//        $DIC = $this->dic_backup;
//    }
    
    /**
     * @return ilGlobalCacheSettings
     */
    private function getSettings() : ilGlobalCacheSettings
    {
        $settings = new ilGlobalCacheSettings();
        $settings->setActive(true);
        $settings->setActivatedComponents(['test']);
        $settings->setService(ilGlobalCache::TYPE_STATIC);
        return $settings;
    }
    
    public function testService() : void
    {
        $settings = $this->getSettings();
        ilGlobalCache::setup($settings);
        
        $cache = ilGlobalCache::getInstance('test');
        $this->assertTrue($cache->isActive());
        $this->assertEquals('test', $cache->getComponent());
        $this->assertEquals(0, $cache->getServiceType());
        
        $cache = ilGlobalCache::getInstance('test_2');
        $this->assertFalse($cache->isActive());
        $this->assertEquals('test_2', $cache->getComponent());
        $this->assertEquals(0, $cache->getServiceType());
    }
    
    public function testValues() : void
    {
        $settings = $this->getSettings();
        ilGlobalCache::setup($settings);
        $cache = ilGlobalCache::getInstance('test');
        
        $this->assertFalse($cache->isValid('test_key'));
        $cache->set('test_key', 'value');
        $this->assertTrue($cache->isValid('test_key'));
        $this->assertEquals('value', $cache->get('test_key'));
    }
}
