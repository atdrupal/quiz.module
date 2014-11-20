<?php

abstract class QuizUpgradeBaseTestCase extends UpdatePathTestCase {

  protected static $testDumpFile = '…';
  protected static $dependencies = array('ctools', 'entity', 'filter', 'views', 'views_bulk_operations', 'xautoload');
  protected static $testDescription = 'Test an upgrade from various Quiz versions.';

  public function setUp() {
    $this->databaseDumpFiles = array(drupal_get_path('module', 'quiz') . '/tests/upgrade/' . static::$testDumpFile);
    parent::setUp();
    module_enable(static::$dependencies);
    $this->loadedModules = module_list();
  }

  public function testUpgrade() {
    $this->assertTrue($this->performUpgrade(), 'The update was completed successfully.');
  }

}
