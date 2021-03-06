<?php
require_once 'PEAR/Command.php';
require_once 'PEAR/Frontend.php';

/**
*   Installation dialog and user interaction interface
*   for the PEAR Gtk2 installer
*
*   @author Christian Weiske <cweiske@php.net>
*/
class PEAR_Frontend_Gtk2_Installation extends PEAR_Frontend
{
    protected $dlgParent     = null;
    protected $glade         = null;
    protected $dlgInstalling = null;
    protected $txtLog        = null;

    protected $strPackage    = null;
    protected $strChannel    = null;

    /**
    *   The command which shall be executed (upgrade or uninstall)
    *   @var string
    */
    protected $strCommand    = null;

    /**
    *   If the log detects an unrecoverable error (e.g. failed dependency)
    *   an error symbol is shown, no matter if the callback for "uninstall ok"
    *   is called.
    *
    *   @var boolean
    */
    protected $bSeriousError = false;

    /**
    *   Array with widgets from the glade file. They
    *   are loaded in the buildDialog() methods
    *   @var array
    */
    protected $arWidgets     = array();

    protected $bQuitLoopOnClose  = true;

    protected $nPercentage = 0;



    /**
    *   The widgets which shall be loaded from the glade
    *   file into the $arWidgets array
    *   @var array
    */
    protected static $arRequestedWidgets = array(
        'dlgInstalling', 'imgInstalling', 'progBarInstaller', 'btnCloseInstalling',
        'lblInstalling', 'txtInstalling', 'expLog'
    );



    public function __construct(GtkWidget $parent, GladeXML $glade)
    {
        $this->dlgParent = $parent;
        $this->glade = $glade;
        $this->buildDialog();
    }//public function __construct(GtkWidget $parent)



    /**
    *   load the glade file, load the widgets, connect the signals
    */
    protected function buildDialog()
    {
        foreach (self::$arRequestedWidgets as $strWidgetName) {
            $this->arWidgets[$strWidgetName] = $this->glade->get_widget($strWidgetName);
        }
        $this->txtLog        = $this->arWidgets['txtInstalling'];
        $this->dlgInstalling = $this->arWidgets['dlgInstalling'];
        $this->dlgInstalling->set_transient_for($this->dlgParent);

        $this->arWidgets['btnCloseInstalling']->connect_simple('clicked', array($this, 'hideMe'));
        $this->dlgInstalling->connect('delete-event', array($this, 'deleteWindow'));
    }//protected function buildDialog()



    /**
    *   Installs (or uninstalls) the given package.
    *   If you want to install a file, set $strChannel and $strVersion
    *   to NULL.
    *
    *   @param string   $strChannel The channel on which the package can be found
    *   @param string   $strPackage The package to install
    *   @param string   $strVersion Package version to install
    *   @param boolean  $bInstall   If the package shall be installed (true) or uninstalled (false)
    */
    public function installPackage($strChannel, $strPackage, $strVersion, $bInstall = true, $strDepOptions = null, $bForce = false)
    {
        //Bad code, but PEAR doesn't offer the possibility to do that a better way
        $GLOBALS['_PEAR_FRONTEND_SINGLETON'] = $this;

        $this->bSeriousError = false;//there is no serious error *yet*
        $this->strPackage    = $strPackage;
        $this->strChannel    = $strChannel;

        $strText = $bInstall ? 'I' : 'Uni';
        $strText .= 'nstalling ' . $strChannel . '/' . $strPackage;
        $this->dlgInstalling->set_title($strText);
        $this->setCurrentAction($strText);
        $this->setCurrentIcon(Gtk::STOCK_EXECUTE);
        $this->setPercentage(0);

        $this->clearLog();
//        $this->arWidgets['expLog']->set_expanded(false);

        $this->appendToLog($strText . "\r\n");

        $this->showMe();

        $cmd              = PEAR_Command::factory('install', PEAR_Config::singleton());
        if ($bInstall) {
            if ($strChannel === null && $strVersion === null) {
                //Install file
                $strPackagePath = $strPackage;
                $strCommand     = 'upgrade';
            } else {
                $strPackagePath = 'channel://' . $strChannel . '/' . $strPackage . '-' . $strVersion;
                $strCommand     = 'upgrade';
            }
        } else {
            $strPackagePath     = 'channel://' . $strChannel . '/' . $strPackage;
            $strCommand         = 'uninstall';
        }
        $this->strCommand = $strCommand;

        while (Gtk::events_pending()) { Gtk::main_iteration();}

        if ($strCommand === 'upgrade') {
            $arOptions = array($strDepOptions => true);
        } else {
            $arOptions = array();
        }
        if ($bForce) {
            $arOptions['force'] = true;
        }

        $cmd->run($strCommand, $arOptions, array($strPackagePath));

        //own main loop so that the next functions aren't executed until the window is closed
        $this->bQuitLoopOnClose = true;
        Gtk::main();
    }//public function installPackage($strChannel, $strPackage, $strVersion, $bInstall = true, $strDepOptions = null, $bForce = false)



    /**
    *   Executes a channel command
    *
    *   @param string   $strCommand     The command (delete, update, discover)
    *   @param string   $strChannel     The channel name (or the url for discovering)
    */
    public function channelCommand($strCommand, $strChannel)
    {
        $this->prepareFresh();
        $this->showMe();
        $cmd = PEAR_Command::factory('channel-' . $strCommand, PEAR_Config::singleton());
        $ret = $cmd->run('channel-' . $strCommand, array(), array($strChannel));
        if (!PEAR::isError($ret)) {
            $this->setFinished();
        } else {
            $this->bSeriousError = true;
            $this->setFinished();
            $this->setCurrentAction($ret->getMessage());
            $this->appendToLog($ret->getMessage() . "\r\n");
            $this->appendToLog($ret->getUserinfo() . "\r\n");
        }
        $this->bQuitLoopOnClose = true;
        Gtk::main();
    }//public function channelCommand($strCommand, $strChannel)



    /**
    *   Use this method to run commands by hand, and you need
    *   the interface to be shown.
    */
    public function show($strAction, $bQuitLoopOnClose = false)
    {
        $this->bSeriousError = false;
        $this->bQuitLoopOnClose = $bQuitLoopOnClose;
        $this->prepareFresh();
        $this->setCurrentAction($strAction);
        $this->showMe();
    }//public function show($strAction, $bQuitLoopOnClose = false)



    /**
    *   Prepares the widgets to have a fresh look.
    *   Use it before starting the actual work
    */
    protected function prepareFresh()
    {
        //Bad code, but PEAR doesn't offer the possibility to do that a better way
        $GLOBALS['_PEAR_FRONTEND_SINGLETON'] = $this;

        $this->setPercentage(0);
        $this->clearLog();
        $this->setCurrentIcon(Gtk::STOCK_EXECUTE);
        $this->setCurrentAction('');
    }//protected function prepareFresh()


    protected function showMe()
    {
        $this->dlgInstalling->set_modal(true);
        $this->dlgInstalling->set_position(Gtk::WIN_POS_CENTER_ON_PARENT);
        $this->dlgInstalling->show();
        while (Gtk::events_pending()) { Gtk::main_iteration();}
    }//protected function showMe()



    public function hideMe()
    {
        $this->dlgInstalling->set_modal(false);
        $this->dlgInstalling->hide();
        if ($this->bQuitLoopOnClose) {
            Gtk::main_quit();
        }
    }//public function hideMe()



    /**
    *   The user may not close the window by hand
    */
    public function deleteWindow()
    {
        return true;
    }//public function deleteWindow()



    protected function appendToLog($strText)
    {
        $buffer = $this->txtLog->get_buffer();
        $end = $buffer->get_end_iter();
        $buffer->insert($end, $strText);

        $this->txtLog->scroll_to_iter($buffer->get_end_iter(), 0.49);
    }//protected function appendToLog($strText)



    protected function clearLog()
    {
        $buffer = $this->txtLog->get_buffer();
        $buffer->delete($buffer->get_start_iter(), $buffer->get_end_iter());
    }//protected function clearLog()



    protected function setCurrentAction($strText)
    {
        $this->arWidgets['lblInstalling']->set_text($strText);
    }//protected function setCurrentAction($strText)



    /**
    *   Set the icon for the dialog
    *
    *   @param int $nId     The stock item id
    */
    protected function setCurrentIcon($nId)
    {
        $this->arWidgets['imgInstalling']->set_from_stock($nId, Gtk::ICON_SIZE_DIALOG);
    }//protected function setCurrentIcon($nId)



    /**
    *   Set the progress bar progress in percent (0-100)
    */
    protected function setPercentage($nPercent)
    {
        $nPercent = $nPercent % 100;
        $this->nPercentage = $nPercent;
        if ($nPercent <= 0) {
            $this->arWidgets['progBarInstaller']->set_fraction(0);
        } else {
            $this->arWidgets['progBarInstaller']->set_fraction($nPercent/100);
        }
    }//protected function setPercentage($nPercent)



    /**
    *   Add some percent to the progress bar
    */
    protected function addPercentage($nPercentToAdd)
    {
        $this->setPercentage($this->nPercentage + $nPercentToAdd);
    }//protected function addPercentage($nPercentToAdd)



    protected function setFinished()
    {
        if ($this->bSeriousError) {
            $this->setCurrentIcon(Gtk::STOCK_DIALOG_ERROR);
            $this->arWidgets['expLog']->set_expanded(true);
        } else {
            $this->setCurrentIcon(Gtk::STOCK_APPLY);
        }
        $this->setPercentage(100);
    }//protected function setFinished()



    public function __call($function, $arguments)
    {
        $this->appendToLog('__call: ' . $function . ' with ' . count($arguments) . ' arguments.' . "\r\n");
        foreach ($arguments as $strName => $strValue) {
            $this->appendToLog('   arg:' . $strName . ':' . gettype($strValue) . '::' . $strValue . "\r\n");
        }
        while (Gtk::events_pending()) { Gtk::main_iteration();}
    }//public function __call($function, $arguments)



    /**
    *   All functions which might be expected in a PEAR_Frontend go here
    */
    public function outputData($data, $command = null)
    {
        $this->addPercentage(5);

        switch ($command) {
            case 'install':
            case 'upgrade':
            case 'upgrade-all':
                //FIXME: check for release warnings $data['release_warnings']
                $this->setCurrentAction('Installation of ' . $this->strPackage . ' done');
                $this->appendToLog($data['data'] . "\r\n");
                $this->setFinished();
                break;

            case 'uninstall':
                if ($this->bSeriousError) {
                    $this->setCurrentAction('Error uninstalling ' . $this->strPackage);
                    $this->arWidgets['expLog']->set_expanded(true);
                } else {
                    $this->setCurrentAction('Uninstallation of ' . $this->strPackage . ' ok');
                    $this->appendToLog('Uninstall ok' . "\r\n");
                }
                $this->setFinished();
                break;
            case 'channel-discover':
            case 'channel-delete':
                $this->setCurrentAction($data);
                $this->appendToLog($data . "\r\n");
                $this->setFinished();
                break;

            case 'build':
                $this->appendToLog($data . "\r\n");
                break;

            default:
if ($command !== null) {
    echo 'Unsupported command in outputData: '; var_dump($command);
}
                if (!is_array($data)) {
                    $this->setCurrentAction($data);
                    $this->appendToLog($data . "\r\n");
                    if (strpos($data, 'is up to date') !== false) {
                        $this->setFinished();
                    } else if (strpos(strtolower($data), 'error:') !== false) {
                        $this->bSeriousError = true;
                        $this->setFinished();
                    }

                } else if (isset($data['headline'])) {
                    $this->setCurrentAction($data['headline']);
                    $this->appendToLog('!!!' . $data['headline'] . '!!!' . "\r\n");
                    if (stripos(strtolower($data['headline']), 'error') !== false) {
                        $this->bSeriousError = true;
                        $this->setFinished();
                    }
                    if (is_array($data['data'])) {
                        //could somebody tell me if there is a fixed format?
                        foreach ($data['data'] as $nId => $arSubData) {
                            $this->appendToLog(implode(' / ', $arSubData) . "\r\n");
                        }
                    } else if (is_string($data['data'])) {
                        $this->appendToLog($data['data'] . "\r\n");
                    } else {
                        //What's this?
                        $this->appendToLog('PEAR_Frontend_Gtk2_Installation::outputData: Unhandled type "' . gettype($data['data']) . "\"\r\n");
                    }
                }

                while (Gtk::events_pending()) { Gtk::main_iteration();}
                break;
        }
    }//public function outputData($data, $command = null)



    public function log($msg, $append_crlf = true)
    {
//require_once 'Gtk2/VarDump.php'; new Gtk2_VarDump(array($level, $msg, $append_crlf), 'msg');
        if (strpos($msg, 'is required by installed package') !== false
            || strpos(strtolower($msg), 'error:')
        ) {
            $this->bSeriousError = true;
        }
        if ($msg == '.' || $append_crlf === false) {
            $this->addPercentage(1);
        } else {
            $this->addPercentage(10);
        }

        $this->appendToLog($msg . ($append_crlf ? "\r\n":''));
        while (Gtk::events_pending()) { Gtk::main_iteration();}
    }//public function log($msg, $append_crlf = true)



    /**
    *   Ask the user a question
    *
    *   @param string   $prompt     The question to ask
    *   @param string   $default    The default value
    */
    public function userConfirm($prompt, $default = 'yes')
    {
        $dialog = new GtkMessageDialog(
            $this->arWidgets['dlgInstalling'], Gtk::DIALOG_MODAL, Gtk::MESSAGE_QUESTION,
            Gtk::BUTTONS_YES_NO, $prompt
        );
        $answer = $dialog->run();
        $dialog->destroy();

        return ($answer == Gtk::RESPONSE_YES);
    }//public function userConfirm($prompt, $default = 'yes')


}//class PEAR_Frontend_Gtk2_Installation extends PEAR_Frontend
?>