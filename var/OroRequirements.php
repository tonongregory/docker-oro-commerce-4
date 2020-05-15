<?php
// @codingStandardsIgnoreFile

if (is_file(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
require_once __DIR__ . '/../var/SymfonyRequirements.php';

use Oro\Bundle\AssetBundle\NodeJsExecutableFinder;
use Oro\Bundle\AssetBundle\NodeJsVersionChecker;
use Oro\Component\PhpUtils\ArrayUtil;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * This class specifies all requirements and optional recommendations that are necessary to run the Oro Application.
 */
class OroRequirements extends SymfonyRequirements
{
    const REQUIRED_PHP_VERSION  = '7.3.13';
    const REQUIRED_GD_VERSION   = '2.0';
    const REQUIRED_CURL_VERSION = '7.0';
    const REQUIRED_NODEJS_VERSION  = '>=12.0';

    const EXCLUDE_REQUIREMENTS_MASK = '/5\.[0-6]|7\.0/';

    /**
     * @param string $env
     */
    public function __construct($env = 'prod')
    {
        $phpVersion  = phpversion();

        /**
         * We should hide the deprecation varnings for php >= 7.2 because SymfonyRequirements class uses
         * 'create_function' function that was deprecated in php 7.2.
         *
         * @see http://php.net/manual/en/migration72.deprecated.php#migration72.deprecated.create_function-function
         * @see https://github.com/sensiolabs/SensioDistributionBundle/pull/336
         */
        if (version_compare($phpVersion, '7.2', '>=')) {
            $oldLevel = error_reporting(E_ALL & ~E_DEPRECATED);
        }

        parent::__construct();

        // restore the previous report level in casse of php > 7.2.
        if (version_compare($phpVersion, '7.2', '>=')) {
            error_reporting($oldLevel);
        }

        $gdVersion   = defined('GD_VERSION') ? GD_VERSION : null;
        $curlVersion = function_exists('curl_version') ? curl_version() : null;
        $icuVersion  = Intl::getIcuVersion();

        $this->addOroRequirement(
            version_compare($phpVersion, self::REQUIRED_PHP_VERSION, '>='),
            sprintf('PHP version must be at least %s (%s installed)', self::REQUIRED_PHP_VERSION, $phpVersion),
            sprintf(
                'You are running PHP version "<strong>%s</strong>", but Oro needs at least PHP "<strong>%s</strong>" to run.' .
                'Before using Oro, upgrade your PHP installation, preferably to the latest version.',
                $phpVersion,
                self::REQUIRED_PHP_VERSION
            ),
            sprintf('Install PHP %s or newer (installed version is %s)', self::REQUIRED_PHP_VERSION, $phpVersion)
        );

        $this->addOroRequirement(
            null !== $gdVersion && version_compare($gdVersion, self::REQUIRED_GD_VERSION, '>='),
            'GD extension must be at least ' . self::REQUIRED_GD_VERSION,
            'Install and enable the <strong>GD</strong> extension at least ' . self::REQUIRED_GD_VERSION . ' version'
        );

        $this->addOroRequirement(
            null !== $curlVersion && version_compare($curlVersion['version'], self::REQUIRED_CURL_VERSION, '>='),
            'cURL extension must be at least ' . self::REQUIRED_CURL_VERSION,
            'Install and enable the <strong>cURL</strong> extension at least ' . self::REQUIRED_CURL_VERSION . ' version'
        );

        $this->addOroRequirement(
            function_exists('openssl_encrypt'),
            'openssl_encrypt() should be available',
            'Install and enable the <strong>openssl</strong> extension.'
        );

        if (function_exists('iconv')) {
            $this->addOroRequirement(
                false !== @iconv('utf-8', 'ascii//TRANSLIT', 'check string'),
                'iconv() must not return the false result on converting string "check string"',
                'Check the configuration of the <strong>iconv</strong> extension, '
                . 'as it may have been configured incorrectly'
                . ' (iconv(\'utf-8\', \'ascii//TRANSLIT\', \'check string\') must not return false).'
            );
        }

        $this->addOroRequirement(
            class_exists('Locale'),
            'intl extension should be available',
            'Install and enable the <strong>intl</strong> extension.'
        );

        $localeCurrencies = [
            'de_DE' => 'EUR',
            'en_CA' => 'CAD',
            'en_GB' => 'GBP',
            'en_US' => 'USD',
            'fr_FR' => 'EUR',
            'uk_UA' => 'UAH',
        ];

        foreach ($localeCurrencies as $locale => $currencyCode) {
            $numberFormatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

            if ($currencyCode === $numberFormatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE)) {
                unset($localeCurrencies[$locale]);
            }
        }

        $this->addRecommendation(
            empty($localeCurrencies),
            sprintf('Current version %s of the ICU library should meet the requirements', $icuVersion),
            sprintf(
                'There may be a problem with currency formatting in <strong>ICU</strong> %s, ' .
                'please upgrade your <strong>ICU</strong> library.',
                $icuVersion
            )
        );

        $this->addOroRequirement(
            class_exists('ZipArchive'),
            'zip extension should be installed',
            'Install and enable the <strong>Zip</strong> extension.'
        );

        $this->addRecommendation(
            class_exists('SoapClient'),
            'SOAP extension should be installed (API calls)',
            'Install and enable the <strong>SOAP</strong> extension.'
        );

        $this->addRecommendation(
            extension_loaded('tidy'),
            'Tidy extension should be installed to make sure that any HTML is correctly converted into a text representation.',
            'Install and enable the <strong>Tidy</strong> extension.'
        );

        $this->addRecommendation(
            !extension_loaded('phar'),
            'Phar extension is disabled',
            'Disable <strong>Phar</strong> extension to reduce the risk of PHP unserialization vulnerability.'
        );

        $this->addRecommendation(
            extension_loaded('imap'),
            'IMAP extension should be installed for valid email processing on IMAP sync.',
            'Install and enable the <strong>IMAP</strong> extension.'
        );

        $tmpDir = sys_get_temp_dir();
        $this->addRequirement(
            is_writable($tmpDir),
            sprintf('%s (sys_get_temp_dir()) directory must be writable', $tmpDir),
            sprintf(
                'Change the permissions of the "<strong>%s</strong>" directory ' .
                'or the result of <string>sys_get_temp_dir()</string> ' .
                'or add the path to php <strong>open_basedir</strong> list. ' .
                'So that it would be writable.',
                $tmpDir
            )
        );

        // Windows specific checks
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->addRecommendation(
                function_exists('finfo_open'),
                'finfo_open() should be available',
                'Install and enable the <strong>Fileinfo</strong> extension.'
            );

            $this->addRecommendation(
                class_exists('COM'),
                'COM extension should be installed',
                'Install and enable the <strong>COM</strong> extension.'
            );
        }

        // Unix specific checks
        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->addRequirement(
                $this->checkFileNameLength(),
                'Maximum supported filename length must be greater or equal 242 characters.' .
                ' Make sure that the cache folder is not inside the encrypted directory.',
                'Move <strong>var/cache</strong> folder outside encrypted directory.',
                'Maximum supported filename length must be greater or equal 242 characters.' .
                ' Move var/cache folder outside encrypted directory.'
            );
        }

        $baseDir = realpath(__DIR__ . '/..');
        $mem     = $this->getBytes(ini_get('memory_limit'));

        $this->addPhpIniRequirement(
            'memory_limit',
            function ($cfgValue) use ($mem) {
                return $mem >= 512 * 1024 * 1024 || -1 == $mem;
            },
            false,
            'memory_limit should be at least 512M',
            'Set the "<strong>memory_limit</strong>" setting in php.ini<a href="#phpini">*</a> to at least "512M".'
        );

        $nodeJsExecutableFinder = new NodeJsExecutableFinder();
        $nodeJsExecutable = $nodeJsExecutableFinder->findNodeJs();
        $nodeJsExists = null !== $nodeJsExecutable;
        $this->addOroRequirement(
            $nodeJsExists,
            $nodeJsExists ? 'NodeJS is installed' : 'NodeJS must be installed',
            'Install <strong>NodeJS</strong>.'
        );

        $this->addOroRequirement(
            NodeJsVersionChecker::satisfies($nodeJsExecutable, self::REQUIRED_NODEJS_VERSION),
            sprintf('NodeJS "%s" version must be installed.', self::REQUIRED_NODEJS_VERSION),
            sprintf('Upgrade <strong>NodeJS</strong> to "%s" version.', self::REQUIRED_NODEJS_VERSION)
        );

        $npmExists = null !== $nodeJsExecutableFinder->findNpm();
        $this->addOroRequirement(
            $npmExists,
            $npmExists ? 'NPM is installed' : 'NPM must be installed',
            'Install <strong>NPM</strong>.'
        );

        $this->addOroRequirement(
            is_writable($baseDir . '/public/uploads'),
            'public/uploads/ directory must be writable',
            'Change the permissions of the "<strong>public/uploads/</strong>" directory so that the web server can write into it.'
        );
        $this->addOroRequirement(
            is_writable($baseDir . '/public/media'),
            'public/media/ directory must be writable',
            'Change the permissions of the "<strong>public/media/</strong>" directory so that the web server can write into it.'
        );
        $this->addOroRequirement(
            is_writable($baseDir . '/public/bundles'),
            'public/bundles/ directory must be writable',
            'Change the permissions of the "<strong>public/bundles/</strong>" directory so that the web server can write into it.'
        );
        $this->addOroRequirement(
            is_writable($baseDir . '/var/attachment'),
            'var/attachment/ directory must be writable',
            'Change the permissions of the "<strong>var/attachment/</strong>" directory so that the web server can write into it.'
        );
        $this->addOroRequirement(
            is_writable($baseDir . '/var/import_export'),
            'var/import_export/ directory must be writable',
            'Change the permissions of the "<strong>var/import_export/</strong>" directory so that the web server can write into it.'
        );

        if (is_dir($baseDir . '/public/js')) {
            $this->addOroRequirement(
                is_writable($baseDir . '/public/js'),
                'public/js directory must be writable',
                'Change the permissions of the "<strong>public/js</strong>" directory so that the web server can write into it.'
            );
        }

        if (is_dir($baseDir . '/public/css')) {
            $this->addOroRequirement(
                is_writable($baseDir . '/public/css'),
                'public/css directory must be writable',
                'Change the permissions of the "<strong>public/css</strong>" directory so that the web server can write into it.'
            );
        }

        if (!is_dir($baseDir . '/public/css') || !is_dir($baseDir . '/public/js')) {
            $this->addOroRequirement(
                is_writable($baseDir . '/public'),
                'public directory must be writable',
                'Change the permissions of the "<strong>public</strong>" directory so that the web server can write into it.'
            );
        }

        if (is_file($baseDir . '/config/parameters.yml')) {
            $this->addOroRequirement(
                is_writable($baseDir . '/config/parameters.yml'),
                'config/parameters.yml file must be writable',
                'Change the permissions of the "<strong>config/parameters.yml</strong>" file so that the web server can write into it.'
            );
        }

        $configYmlPath = $baseDir . '/config/config_' . $env . '.yml';
        if (is_file($configYmlPath)) {
            $config = $this->getParameters($configYmlPath);
            $pdo = $this->getDatabaseConnection($config);
            if ($pdo) {
                $this->addOroRequirement(
                    $this->isUuidSqlFunctionPresent($pdo),
                    'UUID SQL function must be present',
                    'Execute "<strong>CREATE EXTENSION IF NOT EXISTS "uuid-ossp";</strong>" SQL command so UUID-OSSP extension will be installed for database.'
                );
            }
        }
    }

    /**
     * Adds an Oro specific requirement.
     *
     * @param Boolean     $fulfilled Whether the requirement is fulfilled
     * @param string      $testMessage The message for testing the requirement
     * @param string      $helpHtml The help text formatted in HTML for resolving the problem
     * @param string|null $helpText The help text (when null, it will be inferred from $helpHtml, i.e. stripped from HTML tags)
     */
    public function addOroRequirement($fulfilled, $testMessage, $helpHtml, $helpText = null)
    {
        $this->add(new OroRequirement($fulfilled, $testMessage, $helpHtml, $helpText, false));
    }

    /**
     * Get the list of mandatory requirements (all requirements excluding PhpIniRequirement)
     *
     * @return array
     */
    public function getMandatoryRequirements()
    {
        return array_filter(
            $this->getRequirements(),
            function ($requirement) {
                return !($requirement instanceof PhpIniRequirement)
                    && !($requirement instanceof OroRequirement);
            }
        );
    }

    /**
     * Get the list of PHP ini requirements
     *
     * @return array
     */
    public function getPhpIniRequirements()
    {
        return array_filter(
            $this->getRequirements(),
            function ($requirement) {
                return $requirement instanceof PhpIniRequirement;
            }
        );
    }

    /**
     * Get the list of Oro specific requirements
     *
     * @return array
     */
    public function getOroRequirements()
    {
        return array_filter(
            $this->getRequirements(),
            function ($requirement) {
                return $requirement instanceof OroRequirement;
            }
        );
    }

    /**
     * @param  string $val
     * @return int
     */
    protected function getBytes($val)
    {
        if (empty($val)) {
            return 0;
        }

        preg_match('/([\-0-9]+)[\s]*([a-z]*)$/i', trim($val), $matches);

        if (isset($matches[1])) {
            $val = (int)$matches[1];
        }

        switch (strtolower($matches[2])) {
            case 'g':
            case 'gb':
                $val *= 1024;
            // no break
            case 'm':
            case 'mb':
                $val *= 1024;
            // no break
            case 'k':
            case 'kb':
                $val *= 1024;
            // no break
        }

        return (float)$val;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequirements()
    {
        $requirements = parent::getRequirements();

        foreach ($requirements as $key => $requirement) {
            if (!$requirement instanceof OroRequirement) {
                $testMessage = $requirement->getTestMessage();
                if (preg_match_all(self::EXCLUDE_REQUIREMENTS_MASK, $testMessage, $matches)) {
                    unset($requirements[$key]);
                }
            }
        }

        return $requirements;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecommendations()
    {
        $recommendations = parent::getRecommendations();

        foreach ($recommendations as $key => $recommendation) {
            $testMessage = $recommendation->getTestMessage();
            if (preg_match_all(self::EXCLUDE_REQUIREMENTS_MASK, $testMessage, $matches)) {
                unset($recommendations[$key]);
            }
        }

        return $recommendations;
    }

    /**
     * @return bool
     */
    protected function checkFileNameLength()
    {
        $getConf = new Process(['getconf', 'NAME_MAX', __DIR__]);

        if (isset($_SERVER['PATH'])) {
            $getConf->setEnv(array('PATH' => $_SERVER['PATH']));
        }
        $getConf->run();

        if ($getConf->getErrorOutput()) {
            // getconf not installed
            return true;
        }

        $fileLength = trim($getConf->getOutput());

        return $fileLength >= 242;
    }

    /**
     * @param PDO $pdo
     * @return bool
     */
    protected function isUuidSqlFunctionPresent(PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            try {
                $version = $pdo->query("SELECT extversion FROM pg_extension WHERE extname = 'uuid-ossp'")->fetchColumn();

                return !empty($version);
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $config
     * @return bool
     */
    protected function isPdoDriver(array $config)
    {
        return !empty($config['database_driver']) && strpos($config['database_driver'], 'pdo') === 0;
    }

    /**
     * @param array $config
     * @return bool|null|PDO
     */
    protected function getDatabaseConnection(array $config)
    {
        if ($config && $this->isPdoDriver($config)) {
            $driver = str_replace('pdo_', '', $config['database_driver']);
            $dsnParts = array(
                'host=' . $config['database_host'],
            );
            if (!empty($config['database_port'])) {
                $dsnParts[] = 'port=' . $config['database_port'];
            }
            $dsnParts[] = 'dbname=' . $config['database_name'];

            try {
                return new PDO(
                    $driver . ':' . implode(';', $dsnParts),
                    $config['database_user'],
                    $config['database_password']
                );
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param string $parametersYmlPath
     * @return array
     */
    protected function getParameters($parametersYmlPath)
    {
        $fileLocator = new FileLocator();
        $loader = new YamlFileLoader($fileLocator);

        return $loader->load($parametersYmlPath);
    }
}

class OroRequirement extends Requirement
{
}

class YamlFileLoader extends Symfony\Component\Config\Loader\FileLoader
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);

        $content = Yaml::parse(file_get_contents($path));

        // empty file
        if (null === $content) {
            return array();
        }
        if (empty($content['parameters'])) {
            $content['parameters'] = array();
        }

        // imports
        $importedParameters = $this->parseImports($content, $path);
        $content['parameters'] = ArrayUtil::arrayMergeRecursiveDistinct($content['parameters'], $importedParameters);

        // parameters
        if (isset($content['parameters'])) {
            return $content['parameters'];
        }

        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && in_array(pathinfo($resource, PATHINFO_EXTENSION), array('yml', 'yaml'), true);
    }

    /**
     * Parses all imports.
     *
     * @param array $content
     * @param string $file
     * @return array
     */
    private function parseImports($content, $file)
    {
        if (!isset($content['imports'])) {
            return array();
        }

        if (!is_array($content['imports'])) {
            throw new InvalidArgumentException(sprintf('The "imports" key should contain an array in %s. Check your YAML syntax.', $file));
        }

        $defaultDirectory = dirname($file);
        $importedParameters = array();
        foreach ($content['imports'] as $import) {
            if (!is_array($import)) {
                throw new InvalidArgumentException(sprintf('The values in the "imports" key should be arrays in %s. Check your YAML syntax.', $file));
            }

            $this->setCurrentDir($defaultDirectory);
            $importedContent = (array)$this->import($import['resource'], null, isset($import['ignore_errors']) ? (bool) $import['ignore_errors'] : false, $file);
            if (is_array($importedContent)) {
                $importedParameters = ArrayUtil::arrayMergeRecursiveDistinct($importedParameters, $importedContent);
            }
        }

        return $importedParameters;
    }
}
