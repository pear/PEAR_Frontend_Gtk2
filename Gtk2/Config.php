<?php
/**
*   This file contains the configuration options
*   for PEAR_Frontend_Gtk2, beginning with channel
*   colors/images, default channel and more.
*   It can load and write the config from an .ini file,
*   and from and to the GUI.
*
*   @author Christian Weiske <cweiske@php.net>
*/
class PEAR_Frontend_Gtk2_Config
{
    /**
    *   Color settings for the different channels.
    *   When a channel is selected from the dropdown,
    *   this array is read and the background color
    *   as well as the text color  of the bar on the right 
    *   top is is set according to this settings here.
    *
    *   If there is no entry for a specific channel, the 
    *   settings in "default" are used.
    *
    *   @var array
    */
    public static $arChannels = array(
        'pear.php.net'  => array(
            'background-color'  => '#339900',
            'color'             => '#FFF'
        ),
        'pecl.php.net'  => array(
            'background-color'  => '#2C1D83',
            'color'             => '#FFF'
        ),
        'pear.chiaraquartet.net'  => array(
            'background-color'  => '#333333',
            'color'             => '#FFF'
        ),
        'tradebit.bogo' => array(
            'background-color'  => '#FFF',
            'color'             => '#000'
        ),
        'gnope.org' => array(
            'background-color'  => '#FFF',
            'color'             => '#000'
        ),
        'gnope.bogo' => array(
            'background-color'  => '#FFF',
            'color'             => '#000'
        ),
        'default' => array(
            'background-color'  => '#FFF',
            'color'             => '#000'
        )
    );

    /**
    *   The channel which is shown first.
    *   @var string
    */
    public static $strDefaultChannel = 'gnope.org';

    /**
    *   The package which is selected first.
    *   @var string
    */
    public static $strDefaultPackage = null;

    /**
    *   Work offline? If yes, then no internet connection is established.
    *   @var boolean
    */
    public static $bWorkOffline = false;

    /**
    *   What dependency option should be used when installing
    *   a package.
    *   One of: onlyreqdeps, alldeps, nodeps or "".
    *   @var string
    */
    public static $strDepOptions = 'onlyreqdeps';

    /**
    *   The dependency options for installation, and the 
    *   corresponding widget names from the GUI.
    *   @var array
    */
    protected static $arDepWidgets = array(
        'onlyreqdeps' => 'mnuOptDepsReq',
        'alldeps'     => 'mnuOptDepsAll',
        'nodeps'      => 'mnuOptDepsNo',
        ''            => 'mnuOptDepNothing'
    );



    /**
    *   Load the config file into the variables here.
    *
    *   @return boolean  True if all is ok, false if not
    */
    public static function loadConfig()
    {
        require_once 'Config.php';
        $config = new Config();
        $root   = $config->parseConfig(self::getConfigFilePath(), 'inifile');

        if (PEAR::isError($root)) {
            //we have default values if there is no config file yet
            return false;
        }
        $arRoot = $root->toArray();
        if (!isset($arRoot['root']['installer']) || !is_array($arRoot['root']['installer'])) {
            return false;
        }
        $arConf = array_merge(self::getConfigArray(), $arRoot['root']['installer']);

        self::$bWorkOffline     = (boolean)$arConf['offline'];
        self::$strDepOptions    = (string) $arConf['depOption'];

        self::loadCommandLineArguments();

        return true;
    }//public static function loadConfig()



    /**
    *   Loads some command line arguments into the config.
    *   Args are:
    *   * -c channelname        Select channelname
    *   * -O                    Work offline
    *   * -a                    All dependencies
    *   * -o                    Only required dependencies
    *   * -n                    No dependencies
    *   * -v                    Version information
    */
    protected static function loadCommandLineArguments()
    {
        //we can't use argc here because "pear -G" removes
        // the first element
        if (count($GLOBALS['argv']) <= 1) {
            return;
        }

        $nCurrentPos = 1;
        do {
            switch ($GLOBALS['argv'][$nCurrentPos]) {
                case '-c':
                case '--channel':
                    if ($GLOBALS['argc'] > $nCurrentPos + 1) {
                        self::$strDefaultChannel = $GLOBALS['argv'][++$nCurrentPos];
                    }
                    break;
                case '-p':
                case '--package':
                    if ($GLOBALS['argc'] > $nCurrentPos + 1) {
                        self::$strDefaultPackage = $GLOBALS['argv'][++$nCurrentPos];
                    }
                    break;
                case '-O':
                    self::$bWorkOffline = true;
                    break;
                case '-n':
                case '--nodeps':
                    self::$strDepOptions = 'nodeps';
                    break;
                case '-a':
                case '--alldeps':
                    self::$strDepOptions = 'alldeps';
                    break;
                case '-o':
                case '--onlyreqdeps':
                    self::$strDepOptions = 'onlyreqdeps';
                    break;
                case '-v':
                case '--version':
                    echo "PEAR_Frontend_Gtk2 version @VERSION_PEAR_Frontend_Gtk2@\r\n";
                    exit;
                    break;
                case '-h':
                case '--help':
echo <<<HELP
pear -G [options]

Shows the Gtk2 PEAR Frontend that allows managing
(installing/uninstalling) packages and channels.

  -c, --channel channelname
        Show channelname package on startup
  -p, --package [channel/]packagename
        Select the given package on startup
  -n, --nodeps
        ignore dependencies, upgrade anyway
  -a, --alldeps
        install all required and optional dependencies
  -o, --onlyreqdeps
        install all required dependencies
  -O, --offline
        do not attempt to download any urls or contact channels


HELP;
                    exit;
                    break;
            }
        } while (++$nCurrentPos < count($GLOBALS['argv']));

        if (self::$strDefaultPackage !== null) {
            $arSplit = explode('/', self::$strDefaultPackage);
            if (count($arSplit) == 2) {
                self::$strDefaultChannel = $arSplit[0];
                self::$strDefaultPackage = $arSplit[1];
            }
        }
        if (self::$strDefaultChannel !== null) {
            self::$strDefaultChannel =
                PEAR_Config::singleton()->getRegistry()->_getChannelFromAlias(
                    self::$strDefaultChannel
                );
        }
    }//protected static function loadCommandLineArguments()



    /**
    *   Save the config in the config file
    */
    public static function saveConfig()
    {
        require_once 'Config.php';
        $conf   = new Config_Container('section', 'installer');
        $arConf = self::getConfigArray();
        foreach ($arConf as $key => $value) {
            $conf->createDirective($key, $value);
        }

        $config = new Config();
        $config->setRoot($conf);
        $config->writeConfig(self::getConfigFilePath(), 'inifile');
    }//public static function saveConfig()



    /**
    *   The config array with all current values.
    *   Used for loading and storing
    *
    *   @return array  Arra with all the config options: option name => option value
    */
    public static function getConfigArray()
    {
        return array(
            'offline' => self::$bWorkOffline,
            'depOption' => self::$strDepOptions
        );
    }//public static function getConfigArray()



    /**
    *   The config file path. (Where the config file is/shall be stored)
    *
    *   @return string  The config file path
    */
    protected static function getConfigFilePath()
    {
        return PEAR_Config::singleton()->get('data_dir') . DIRECTORY_SEPARATOR . get_class() . '.ini';
    }//protected static function getConfigFilePath()



    /**
    *   Loads the current configuration into the GUI.
    *   Sets all the widgets which reflect the config settings
    *   in a way (e.g. the radio menu group for dep options)
    *
    *   @param PEAR_Frontend_Gtk2   $fe     The current frontend to where the config shall be transferred
    */
    public static function loadCurrentConfigIntoGui(PEAR_Frontend_Gtk2 $fe)
    {
        $fe->getWidget('mnuOffline')   ->set_active(self::$bWorkOffline);
        foreach (self::$arDepWidgets as $strValue => $strWidget) {
            $fe->getWidget($strWidget)->set_active(self::$strDepOptions == $strValue);
        }
    }//public static function loadConfigIntoGui(PEAR_Frontend_Gtk2 $fe)



    /**
    *   Loads the configuration from the GUI to this config.
    *   This needs to be done before saving the config file.
    *   It checks the widgets responsible for the config options and reads their
    *   settings, saving them intho this config class.
    *
    *   @param PEAR_Frontend_Gtk2   $fe     The current frontend to where the config shall be transferred
    */
    public static function loadConfigurationFromGui(PEAR_Frontend_Gtk2 $fe)
    {
        self::$bWorkOffline = $fe->getWidget('mnuOffline')->get_active();
        foreach (self::$arDepWidgets as $strValue => $strWidget) {
            if ($fe->getWidget($strWidget)->get_active()) {
                self::$strDepOptions = $strValue;
            }
        }
    }//public static function loadConfigurationFromGui(PEAR_Frontend_Gtk2 $fe)

}//class PEAR_Frontend_Gtk2_Config
?>