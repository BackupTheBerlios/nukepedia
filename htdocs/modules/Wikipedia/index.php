<?php
/**
 * Nukepedia
 *
 * LIZENZ
 *
 * Dieses Programm ist freie Software; Sie können sie beliebig verteilen
 * und/oder Änderungen vornehmen, solange Sie dieses unter den
 * Lizenzbedingungen der Version 2 oder höher der GNU General Public
 * License (GPL), veröffentlicht von der Free Software Foundation tun.
 *
 * Dieses Programm wurde in der Hoffnung erstellt, dass es für Sie
 * nützlich sein könnte. Es wird jedoch ohne jeden Anspruch auf
 * Gewährleistung veröffentlicht. Sie können auch nicht davon ausgehen,
 * dass das Programm Dinge verrichtet, wie es erwartet wird.
 *
 * Lesen Sie die Lizenzbedinguneg unter der URL
 * http://www.gnu.org/copyleft/gpl.html für weitere Details.
 *
 * @author Hinrich Donner
 * @version $Revision: 1.1 $
 * @since 03.07.2004
 * @package Nukepadia
 * @subpackage Main
 * @category Main
 * @link http://developer.berlios.de/projects/nukepedia/
 */

require_once 'mainfile.php';

/**
 * wikiConfig
 *
 * Diese Klasse enthält die Konfiguration des Moduls.
 *
 * @author Hinrich Donner <hd at phportals dot de>
 * @copyright Hinrich Donner, (c) 2004
 * @version 1
 * @since 4.7.2004
 */
class wikiConfig
{
    /**
     * _vars
     *
     * Enthält die Konfigurationsvariablen.
     *
     * @var array
     * @since 4.7.2004
     * @access public
     */
    var $_vars;

    /**
     * wikiConfig
     *
     * Der Konstruktor.
     *
     * @param string $filename Der Dateiname mit der Konfiguration
     * @version 1
     * @since 4.7.2004
     * @author Hinrich Donner <hd at phportals dot de>
     * @copyright Hinrich Donner, (c) 2004
     */
    function wikiConfig($filename)
    {
        $vars = parse_ini_file($filename, true);
        $this->_vars = $vars['Wikipedia'];

        // Namensräume aufbauen
        //
        $ns_var = sprintf('namespace_%s', $this->Get('server'));
        $this->_vars[$ns_var] = split(',', $this->Get($ns_var));
    }

    /**
     * Get
     *
     * Diese Methode liefert eine Konfigurationsvariable zurück.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Get($name)
    {
        return $this->_vars[$name];
    }

} // class

/**
 * wikiArticle
 *
 * Diese Klasse repräsentiert einen einzelnen Artikel.
 *
 * @author Hinrich Donner <hd at phportals dot de>
 * @copyright Hinrich Donner, (c) 2004
 * @version 1
 * @since 04.07.2004
 */
class wikiArticle
{
    /**
     * _in_database
     *
     * Diese Eigenschaft hält fest, ob der Artikel in der Datenbank ist. Der Wert -1
     * repräsentiert dabei einen undefinierten Status, 0 bedeutet FALSE und jede andere
     * positive Zahl entspricht der ID des Datensatzes.
     *
     * @var int
     * @since 04.07.2004
     * @access protected
     */
    var $_in_database = -1;

    /**
     * config
     *
     * Eine Referenz auf die Konfiguration.
     *
     * @var wikiConfig
     * @since 4.7.2004
     * @access public
     */
    var $config;

    /**
     * request_title
     *
     * Diese Eigenschaft enthält den angeforderten Artikel.
     *
     * @var string
     * @since 4.7.2004
     * @access public
     */
    var $request_title;

    /**
     * details
     *
     * Diese Eigenschaft enthält die Details des Artikles.
     *
     * @var array
     * @since 04.07.2004
     * @access public
     */
    var $details;

    /**
     * wikiArticle
     *
     * Der Konstruktor.
     *
     * @param string $title Der Titel des Beitrags
     * @param wikiConfig $config Eine Referenz auf die Konfiguration
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner <hd at phportals dot de>
     * @copyright Hinrich Donner, (c) 2004
     */
    function wikiArticle($title, &$config)
    {
        $this->config =& $config;
        $this->request_title = $title;
    }

    /**
     * _FetchFromDatabase
     *
     * List einen Datensatz aus der Datenbank. Eine Fehlerprüfung findet hier nicht
     * mehr statt.
     *
     * @access protected
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     * @see wikiArticle::_FetchFromWeb()
     */
    function _FetchFromDatabase()
    {
        if (!$this->InDatabase())
            trigger_error('wikiArticle::_FetchFromDatabase(): Invalid call!', E_USER_ERROR);
        global $dbi, $prefix;
        $sql = sprintf("SELECT `id`,
                               `title`,
                               `author`,
                               `text`,
                               UNIX_TIMESTAMP(`published`) AS `published`,
                               UNIX_TIMESTAMP(`fetched`) AS `fetched`,
                               `changed`,
                               `unchanged`,
                               (`changed`+`unchanged`) AS `total`,
                               `checksum`
                        FROM `%s_wikipedia_data`
                        WHERE `id`=%d", $prefix, $this->_in_database);
        $dbr = sql_query($sql, $dbi);
        $this->details = sql_fetch_array($dbr, $dbi);
        sql_free_result($dbr, $dbi);
    }

    /**
     * _FetchFromWeb
     *
     * Diese Methode holt einen Artikel aus dem Web.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     * @see wikiArticle::_FetchFromDatabase()
     * @todo Fehlertoleranz
     */
    function _FetchFromWeb()
    {
        // Aufräumen
        //
        unset($this->details);

        // Content Holen
        //
        $uri = sprintf('http://%s.wikipedia.org/w/wiki.phtml?title=%s&action=submit&curonly=true&pages=%s',
                       $this->config->Get('server'),
                       $this->config->Get('exportpage'),
                       urlencode($this->request_title));
        $xml = file_get_contents($uri);

        // Prüfsumme anlegen
        //
        $this->details = array('checksum' => md5($xml));

        // XML-Parser durchlaufen lassen.  Vom programmtechnischen Standpunkt ist diese Implementierung
        // grottenschlecht. Da aber interne Routinen von PHP schneller laufen als eine noch so ausgeklügelte
        // Logik, ist diese Methode einer esthetischern vorzuziehen.
        //
        $parser = xml_parser_create();
        xml_parse_into_struct($parser, $xml, $values, $index);
        xml_parser_free($parser);

        $this->details['title']       = $values[$index['TITLE'][0]]['value'];
        $this->details['author']      = ((empty($values[$index['IP'][0]]['value']))
                                    ? $values[$index['USERNAME'][0]]['value']
                                    : $values[$index['IP'][0]]['value']);
        list($year,
             $month,
             $day,
             $hour,
             $minute,
             $secound)          = sscanf($values[$index['TIMESTAMP'][0]]['value'], '%4d-%2d-%2dT%2d:%2d:%2dZ');
        $this->details['published']   = gmmktime($hour, $minute, $second, $month, $day, $year);
        $this->details['text']        = $values[$index['TEXT'][0]]['value'];
    }

    /**
     * InDatabase
     *
     * Diese Methode prüft, ob ein Artikel in der Datenbank ist.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function InDatabase()
    {
        if ($this->_in_database >= 0)
            return $this->_in_database;

        global $dbi, $prefix;
        $sql = sprintf("SELECT `id` FROM `%s_wikipedia_data` WHERE (`title`='%s')",
                       $prefix,
                       addslashes($this->request_title));
        if (false === ($dbr = sql_query($sql, $dbi)))
        {
            $this->_in_database = 0;
            return $this->_in_database;
        }
        list ($this->_in_database) = sql_fetch_row($dbr, $dbi);
        sql_free_result($dbr, $dbi);
        return $this->_in_database;
    }

    /**
     * CanUsed
     *
     * Diese Methode läd den vorhandenen Datensatz und prüft das Cache-Verhalten.  Ein
     * Artikel wird neu geladen, wenn er weniger als 100 Mal insgesamt geladen wurde, oder
     * eine Zeitperiode, die um so kürze ist, je höher der Cache-Miss-Wert ist, verstrichen
     * ist.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     * @see wikiArticle::InDatabase()
     */
    function CanUsed()
    {
        if (!$this->InDatabase())
            return false;

        // Artikel aus der Datenbank holen
        //
        $this->_FetchFromDatabase();

        // Prüfen, ob die Cache-Bedingungen erfüllt sind.
        //
        if ($this->details['total'] < $this->config->Get('readbeforecache'))
            return false;

        // Prozentwert ermitteln (im Zehnerschritt) (0..10)
        //
        $missed_rate = (int) round($this->details['changed'] / $this->details['total'] * 10, 0);
        $_expires = $this->config->Get(sprintf('expires%d', $missed_rate));
        if ($this->details['fetched'] + $_expires >= time())
            return true;
        return false;
    }

    /**
     * Save
     *
     * Speichert den Artikel in der Datenbank.
     *
     * @param string $old_checksum Der alte MD5-Hash
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Save($old_checksum)
    {
        global $dbi, $prefix;

        // Speichermethode ermitteln
        //
        if (!$this->InDatabase())
            // Neu
            //
            $sql = sprintf("INSERT INTO `%s_wikipedia_data`
                            (`title`,
                             `author`,
                             `text`,
                             `published`,
                             `fetched`,
                             `changed`,
                             `checksum`)
                            VALUES ('%s',
                                    '%s',
                                    '%s',
                                    '%s',
                                    NOW(),
                                    '1',
                                    '%s')",
                           $prefix,
                           addslashes($this->details['title']),
                           addslashes($this->details['author']),
                           addslashes($this->details['text']),
                           strftime('%Y-%m-%d %H:%M:%S', $this->details['published']),
                           $this->details['checksum']);
        elseif (!strcmp($old_checksum, $this->details['checksum']))
            // Unverändert
            //
            $sql = sprintf("UPDATE `%s_wikipedia_data`
                            SET `unchanged`=`unchanged`+1
                            WHERE `id`='%d'",
                           $prefix,
                           $this->_in_database);
        else
            // Verändert
            //
            $sql = sprintf("UPDATE `%s_wikipedia_data`
                            SET `changed`=`changed`+1,
                                `title`='%s',
                                `author`='%s',
                                `text`='%s',
                                `published`='%s',
                                `checksum`='%s',
                                `fetched`=NOW()
                            WHERE `id`='%d'",
                           $prefix,
                           addslashes($this->details['title']),
                           addslashes($this->details['author']),
                           addslashes($this->details['text']),
                           strftime('%Y-%m-%d %H:%M:%S', $this->details['published']),
                           $this->details['checksum'],
                           $this->_in_database);
        // Und speichern
        //
        sql_query($sql, $dbi);
    }

    /**
     * Load
     *
     * Läd den gewünschten Artikel.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Load()
    {
        if (!$this->CanUsed())
        {
            // Alte Prüfsumme merken
            //
            $old_checksum = $this->GetChecksum();
            $this->_FetchFromWeb();
            $this->Save($old_checksum);
        }
    }

    /**
     * GetCheckSum
     *
     * Liefert die Prüfsumme, sofern vorhanden.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function GetCheckSum()
    {
        if ($this->InDatabase())
            return $this->details['checksum'];
        return '0';
    }

    /**
     * Parse
     *
     * Diese Methode wandelt die Wiki-Daten in HTML um. Als Ergebnis wird im Array $details
     * der Schlüssel 'parsed_text' angelegt. Das Ergebnis wird zurückgegeben.
     *
     * @return string Der Text
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Parse()
    {
        if (array_key_exists('parsed_text', $this->details))
            return $this->details['parsed_text'];

        if ($this->config->Get('utf-8') == 0)
            $text = split("\n", utf8_decode($this->details['text']));
        else
            $text = split("\n", $this->details['text']);


        // Zeile für Zeile parsen ist leider notwendig, da Wikipedia nicht konsistent ist.
        //




        $this->details['parsed_text'] = join("\n", $text);

        highlight_string($this->details['parsed_text']);

        echo $this->details['parsed_text'];
//        return $this->details['parsed_text'];
    }

    /**
     * GetTitle
     *
     * Liefert den Titel des Dokuments.
     *
     * @return string Der Titel
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function GetTitle()
    {
        if ($this->config->Get('utf-8') == 0)
            return utf8_decode($this->details['title']);
        return $this->details['title'];
    }

    /**
     * GetParsedText
     *
     * Liefert den Text
     *
     * @return string;
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function GetParsedText()
    {
        return $this->Parse();
    }


} // class


/**
 * wikiInterface
 *
 * Diese Klasse stellt das Interface für das Modul dar.
 *
 * @author Hinrich Donner <hd at phportals dot de>
 * @copyright Hinrich Donner, (c) 2004
 * @version 1
 * @since 4.7.2004
 */
class wikiInterface
{
    /**
     * config
     *
     * Die Instanz mit dem Konfigurationsobjekt.
     *
     * @var wikiConfig
     * @since 4.7.2004
     * @access public
     */
    var $config;

    /**
     * wikiInterface
     *
     * Der Konstruktor.
     *
     * @version 4.7.2004
     * @since
     * @author Hinrich Donner <hd at phportals dot de>
     * @copyright Hinrich Donner, (c) 2004
     */
    function wikiInterface()
    {
        $this->module_name = basename(dirname(__FILE__));
        $this->config =& new wikiConfig(sprintf('modules/%s/wikipedia.ini', $this->module_name));
        get_lang($module_name);
    }

    /**
     * GetTitle
     *
     * Liefert den Namen des gewünschten Beitrags.
     *
     * @return string Der Name
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function GetTitle()
    {
        if (!array_key_exists('wikipage', $_REQUEST))
            return $this->config->Get('startpage');
        $result = strip_tags($_REQUEST['wikipage']);
        if (get_magic_quotes_gpc())
            $result = stripslashes($result);
        return $result;
    }

    /**
     * Run
     *
     * Die eigentliche Modulfunktion.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Run()
    {
        $this->article =& new wikiArticle($this->GetTitle(), &$this->config);
        $this->article->Load();
        $this->article->Parse();
    }

    /**
     * Header
     *
     * Zeigt den Seitenkopf.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Header($title)
    {
        global $pagetitle;

        $pagetitle .= _WIKIPEDIA_PAGETITLE . ': ' . $title;

        include 'header.php';
    }

    /**
     * Footer
     *
     * Der Seitenfuss.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Footer()
    {
        include 'footer.php';
    }

    /**
     * Display
     *
     * Die Anzeige.
     *
     * @version 1
     * @since 04.07.2004
     * @author Hinrich Donner
     * @copyright Hinrich Donner, (c) 2004
     */
    function Display()
    {
//        $this->Header($this->article->GetTitle());
//
//        OpenTable();
        echo $this->article->GetParsedText();
//        CloseTable();
//
//        $this->Footer();
    }


} // class


/**
 * Wikipedia Modul
 *
 * Der Hauptteil des Moduls.
 *
 * @since 4.7.2004
 */
$interface =& new wikiInterface();
$interface->Run();
$interface->Display();

/*
 * $Log: index.php,v $
 * Revision 1.1  2004/07/07 10:09:44  hdonner
 * - Init
 *
 */
?>
