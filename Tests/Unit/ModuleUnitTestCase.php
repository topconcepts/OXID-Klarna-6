<?php
/**
 * Created by PhpStorm.
 * User: arekk
 * Date: 23.03.2018
 * Time: 10:50
 */

namespace TopConcepts\Klarna\Tests\Unit;

use OxidEsales\Eshop\Application\Model\Payment;
use OxidEsales\Eshop\Core\Field;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\Eshop\Core\ConfigFile;

use OxidEsales\TestingLibrary\Services\Library\DatabaseHandler;
use OxidEsales\TestingLibrary\TestConfig;
use OxidEsales\TestingLibrary\UnitTestCase;


class ModuleUnitTestCase extends UnitTestCase
{

    /** @var string */
    protected $moduleName;

    /** @var DatabaseHandler */
    protected $dbHandler;

    /** @var TestConfig  */
    protected $testConfig;


    /**
     * ModuleUnitTestCase constructor.
     * @param null $name
     * @param array $data
     * @param string $dataName
     * @throws \Exception
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->moduleName = 'klarna';
        $this->testConfig = new TestConfig();
        /** @var ConfigFile $configFile */
        $configFile = Registry::get(ConfigFile::class);
        $this->dbHandler = new DatabaseHandler($configFile);
    }

    public function setUpBeforeTestSuite()
    {
        parent::setUpBeforeTestSuite();
    }

    protected function removeQueryString($url)
    {
        $parsed = parse_url($url);
        if (isset($parsed['query']))
            return str_replace($parsed['query'], '', $url);

        return $url;
    }

    protected function tearDown()
    {
        parent::tearDown(); // TODO: Change the autogenerated stub

        \oxUtilsHelper::$sRedirectUrl = null;
    }

    protected function setupKlarnaExternals()
    {
        $config = [
            'oxidcashondel' => ['payment'],
            'oxidpayadvance' => ['checkout']
        ];

        foreach($config as $oxid => $values) {
            $oPayment = oxNew(Payment::class);
            $oPayment->load($oxid);

            if(in_array('payment', $values)) {
                $oPayment->oxpayments__klexternalpayment = new Field(1, Field::T_RAW);
            }

            if(in_array('checkout', $values)) {
                $oPayment->oxpayments__klexternalcheckout = new Field(1, Field::T_RAW);
            }

            $oPayment->save();
        }
    }

    public function setModuleMode($mode)
    {
        $this->getConfig()->saveShopConfVar(null, 'sKlarnaActiveMode', $mode, $this->getShopId(), 'klarna');
    }

    public function setModuleConfVar($name, $value)
    {
        $this->getConfig()->saveShopConfVar(null, $name, $value, $this->getShopId(), 'klarna');
    }

    /**
     * @throws \Exception
     */
    public function insertOrderData()
    {
        $this->dbHandler->import($this->getModuleTestDataDir() . "insert_orders.sql");
    }

    /**
     * @param $tableName
     * @throws \Exception
     */
    public function truncateTable($tableName)
    {
        $this->dbHandler->execSql("TRUNCATE $tableName");
    }

    /** Gets test path for current module */
    protected function getModuleTestDir()
    {
        foreach($this->testConfig->getPartialModulePaths() as $modulePartialPath){
            if(strpos($modulePartialPath, $this->moduleName)){
                return $this->testConfig->getShopPath() . 'modules/' . $modulePartialPath .'/Tests/';
            }
        }
    }

    protected function getModuleTestDataDir()
    {
        return $this->getModuleTestDir() . "Unit/Testdata/";
    }


}