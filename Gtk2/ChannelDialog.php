<?php
/**
*   Channel list with buttons to add and remove
*   channels
*
*   @author Christian Weiske <cweiske@php.net>
*/
class PEAR_Frontend_Gtk2_ChannelDialog
{
    /**
    *   The widgets which shall be loaded from the glade
    *   file into the $arWidgets array
    *   @var array
    */
    protected static $arRequestedWidgets = array(
        'dlgChannels', 'lstChannels', 'btnClose', 'btnAdd',
        'btnUpdate', 'btnDelete'
    );

    /**
    *   Requested widgets are loaded from glade into this array.
    *   So this is an associative array with all required widgets 
    *   from the glade file: name => widget object
    *   @var array
    */
    protected $arWidgets;

    /**
    *   The PEAR channels command object
    *   @var PEAR_Command_Channels
    */
    protected $channels;

    /**
    *   The pear gtk2 frontend object
    *   @var PEAR_Frontend_Gtk2
    */
    protected $frontend;



    /**
    *   Initialize the dialog
    *
    *   @param GladeXML             $glade      The glade to load the widgets from
    *   @param PEAR_Frontend_Gtk2   $frontend   The gtk2 frontend object
    */
    public function __construct(GladeXML $glade, PEAR_Frontend_Gtk2 $frontend)
    {
        $this->frontend = $frontend;
        $this->buildDialog($glade);
    }//public function __construct(GladeXML $glade, PEAR_Frontend_Gtk2 $frontend)



    protected function buildDialog(GladeXML $glade)
    {
        foreach (self::$arRequestedWidgets as $strWidgetName) {
            $this->arWidgets[$strWidgetName] = $glade->get_widget($strWidgetName);
        }
        $model = new GtkListStore(Gtk::TYPE_STRING, Gtk::TYPE_STRING, Gtk::TYPE_STRING);
        $this->arWidgets['lstChannels']->set_model($model);

        $cell_renderer = new GtkCellRendererText();

        $colName = new GtkTreeViewColumn('Channel', $cell_renderer, "text", 0);
        $colName->set_resizable(true);
        $colName->set_sort_column_id(0);
        $this->arWidgets['lstChannels']->append_column($colName);

        $colAlias = new GtkTreeViewColumn('Alias', $cell_renderer, "text", 1);
        $colAlias->set_resizable(true);
        $colAlias->set_sort_column_id(1);
        $this->arWidgets['lstChannels']->append_column($colAlias);

        $colSummary = new GtkTreeViewColumn('Summary', $cell_renderer, "text", 2);
        $colSummary->set_resizable(true);
        $colSummary->set_sort_column_id(2);
        $this->arWidgets['lstChannels']->append_column($colSummary);

        $selection = $this->arWidgets['lstChannels']->get_selection();
        $selection->connect('changed', array($this, 'onSelectionChanged'));

        $this->arWidgets['btnClose'] ->connect_simple('clicked', array($this, 'onClose'));
        $this->arWidgets['btnAdd']   ->connect_simple('clicked', array($this, 'onAdd'));
        $this->arWidgets['btnUpdate']->connect_simple('clicked', array($this, 'onUpdate'));
        $this->arWidgets['btnDelete']->connect_simple('clicked', array($this, 'onDelete'));
    }//protected function buildDialog(GladeXML $glade)



    public function show()
    {
        $this->loadChannels();
        $this->arWidgets['dlgChannels']->show();
    }//public function show()



    /**
    *   The close button has been pressed -> just the dialog only
    */
    public function onClose()
    {
        $this->arWidgets['dlgChannels']->hide();
    }//public function onClose()



    /**
    *   The add button has been pressed
    */
    public function onAdd()
    {
        require_once 'Gtk2/EntryDialog.php';

        $id = new Gtk2_EntryDialog($this->arWidgets['dlgChannels'],
            Gtk::DIALOG_MODAL, Gtk::MESSAGE_QUESTION,
            Gtk::BUTTONS_OK, 'Please type the channel url', ''
        );
        $id->run();
        $strUrl = $id->getText();
        $id->destroy();
        if ($strUrl == '' || $strUrl === null) {
            return;
        }

        $this->frontend->getInstaller()->channelCommand('discover', $strUrl);
        $this->reloadChannels();
    }//public function onAdd()



    /**
    *   The update button has been pressed
    */
    public function onUpdate()
    {
        $strChannel = $this->getSelectedChannelName();
        if ($strChannel === null) {
            return;
        }
        $this->frontend->getInstaller()->channelCommand('update', $strChannel);
        $this->reloadChannels();
    }//public function onUpdate()



    /**
    *   The delete button has been pressed
    */
    public function onDelete()
    {
        $strChannel = $this->getSelectedChannelName();
        if ($strChannel === null) {
            return;
        }

        $dialog = new GtkMessageDialog(
            $this->arWidgets['dlgChannels'], Gtk::DIALOG_MODAL, Gtk::MESSAGE_QUESTION,
            Gtk::BUTTONS_YES_NO, 'Do you want to remove channel "' . $strChannel . '"'
        );
        $answer = $dialog->run();
        $dialog->destroy();

        if ($answer== Gtk::RESPONSE_YES) {
            $this->frontend->getInstaller()->channelCommand('delete', $strChannel);
            $this->reloadChannels();
        }
    }//public function onDelete()



    public function onSelectionChanged($selection)
    {
        $this->arWidgets['btnDelete']->set_sensitive($selection->count_selected_rows() > 0);
        $this->arWidgets['btnUpdate']->set_sensitive($selection->count_selected_rows() > 0);
    }//public function onSelectionChanged($selection)



    /**
    *   Returns the name of the selected channel.
    *
    *   @return string  The name of the channel, or NULL if none is selected
    */
    protected function getSelectedChannelName()
    {
        $selection = $this->arWidgets['lstChannels']->get_selection();
        if ($selection->count_selected_rows() == 0) {
            return null;
        }
        list($model, $iter) = $selection->get_selected();
        return $model->get_value($iter, 0);
    }//protected function getSelectedChannelName()



    /**
    *   Loads the channel list from PEAR into the model.
    */
    protected function loadChannels()
    {
        $model = $this->arWidgets['lstChannels']->get_model();
        $model->clear();

        $arChannels = PEAR_Config::singleton()->getRegistry()->getChannels();
        foreach ($arChannels as $nId => $channel) {
            $model->append(array(
                $channel->getName(),
                $channel->getAlias(),
                $channel->getSummary()
            ));
        }

        $this->onSelectionChanged($this->arWidgets['lstChannels']->get_selection());
    }//protected function loadChannels()



    /**
    *   Call this method if channels have changed.
    *   (added, deleted, updated)
    */
    protected function reloadChannels()
    {
        $this->loadChannels();
        $this->frontend->loadChannels();
    }//protected function reloadChannels()


}//class PEAR_Frontend_Gtk2_ChannelDialog
?>