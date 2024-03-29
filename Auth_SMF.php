<?php

    /* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

    /**
     * This file makes MediaWiki use a SMF user database to
     * authenticate with. This forces users to have a SMF account
     * in order to log into the wiki. This should also force the user to
     * be in a group called wiki.
     *
     * This program is free software; you can redistribute it and/or modify
     * it under the terms of the GNU General Public License as published by
     * the Free Software Foundation; either version 2 of the License, or
     * (at your option) any later version.
     *
     * This program is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
     * GNU General Public License for more details.
     *
     * You should have received a copy of the GNU General Public License along
     * with this program; if not, write to the Free Software Foundation, Inc.,
     * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
     * http://www.gnu.org/copyleft/gpl.html
     *
     * @package MediaWiki
     * @subpackage Auth_SMF
     * @author Nicholas Dunnaway
     * @copyright 2004-2006 php|uber.leet
     * @license http://www.gnu.org/copyleft/gpl.html
     * @CVS: $Id: Auth_SMF.php,v 1.3 2007/04/05 22:17:56 nkd Exp $
     * @link http://uber.leetphp.com
     * @version $Revision: 1.3 $
     *
     */

    error_reporting(E_ALL); // Debug

    // First check if class has already been defined.
    if (!class_exists('AuthPlugin'))
    {
        /**
         * Auth Plugin
         *
         */
        require_once './includes/AuthPlugin.php';
    }

    /**
     * Handles the Authentication with the SMF database.
     *
     * @package MediaWiki
     * @subpackage Auth_SMF
     */
    class Auth_SMF extends AuthPlugin
    {

        /**
         * Add a user to the external authentication database.
         * Return true if successful.
         *
         * NOTE: We are not allowed to add users to SMF from the
         * wiki so this always returns false.
         *
         * @param User $user
         * @param string $password
         * @return bool
         * @access public
         */
        function addUser( $user, $password )
        {
            return false;
        }

		/**
		 * Can users change their passwords?
		 *
		 * @return bool
		 */
		function allowPasswordChange()
		{
			return true;
		}

        /**
         * Check if a username+password pair is a valid login.
         * The name will be normalized to MediaWiki's requirements, so
         * you might need to munge it (for instance, for lowercase initial
         * letters).
         *
         * @param string $username
         * @param string $password
         * @return bool
         * @access public
         * @todo Check if the password is being changed when it contains a slash or an escape char.
         */
        function authenticate($username, $password)
        {

            // Connect to the database.
            $fresMySQLConnection = $this->connect();

            // Clean $username and force lowercase username.
            $username = htmlentities(strtolower($username), ENT_QUOTES, 'UTF-8');
            $username = str_replace('&#039;', '\\\'', $username); // Allow apostrophes (Escape them though)

            // Check MySQLVersion
            if ($GLOBALS['gstrMySQLVersion'] >= 4.1)
            {
                // Check Database for username and password.
                $fstrMySQLQuery = 'SELECT `memberName`, `passwd`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = CONVERT( _utf8 \'' . $username . '\' USING latin1 )
                                   COLLATE latin1_swedish_ci
                                   LIMIT 1';
            }
            else
            {
                // Check Database for username and password.
                $fstrMySQLQuery = 'SELECT `memberName`, `passwd`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = \'' . $username . '\'
                                   LIMIT 1';
            }

            // Query Database.
            $fresMySQLResult = mysql_query($fstrMySQLQuery, $fresMySQLConnection)
                or die($this->mySQLError('Unable to view external table'));

            while($faryMySQLResult = mysql_fetch_array($fresMySQLResult))
            {
                // print $this->md5_hmac($password, $username) . ':' . $faryMySQLResult['passwd'] . '<br />'; // Debug

                // Check if password submited matches the SMF password.
                // Also check if user is a member of the SMF group 'wiki'.
                if ($this->md5_hmac($password, $username) == //<-
                    $faryMySQLResult['passwd'] && $this->isMemberOfWikiGroup($username))
                {
                    return true;
                }
            }
            return false;
        }

        /**
         * Return true if the wiki should create a new local account automatically
         * when asked to login a user who doesn't exist locally but does in the
         * external auth database.
         *
         * If you don't automatically create accounts, you must still create
         * accounts in some way. It's not possible to authenticate without
         * a local account.
         *
         * This is just a question, and shouldn't perform any actions.
         *
         * NOTE: I have set this to true to allow the wiki to create accounts.
         *       Without an accout in the wiki database a user will never be
         *       able to login and use the wiki. I think the password does not
         *       matter as long as authenticate() returns true.
         *
         * @return bool
         * @access public
         */
        function autoCreate()
        {
            return true;
        }

        /**
         * Check to see if external accounts can be created.
         * Return true if external accounts can be created.
         *
         * NOTE: We are not allowed to add users to SMF from the
         * wiki so this always returns false.
         *
         * @return bool
         * @access public
         */
        function canCreateAccounts()
        {
            return false;
        }

        /**
         * Connect to the database. All of these settings are from the
         * LocalSettings.php file. This assumes that the SMF uses the same
         * database/server as the wiki.
         *
         * {@source }
         * @return resource
         */
        function connect()
        {
            // Check if the SMF tables are in a different database then the Wiki.
            if ($GLOBALS['wgSMF_UseExtDatabase'] == true) {

                // Connect to database. I supress the error here.
                $fresMySQLConnection = @mysql_connect($GLOBALS['wgSMF_MySQL_Host'],
                                                      $GLOBALS['wgSMF_MySQL_Username'],
                                                      $GLOBALS['wgSMF_MySQL_Password'],
                                                      true);

                // Check if we are connected to the database.
                if (!$fresMySQLConnection)
                {
                    $this->mySQLError('There was a problem when connecting to the SMF database.<br />' .
                                      'Check your Host, Username, and Password settings.<br />');
                }

                // Select Database
                $db_selected = mysql_select_db($GLOBALS['wgSMF_MySQL_Database'], $fresMySQLConnection);

                // Check if we were able to select the database.
                if (!$db_selected)
                {
                    $this->mySQLError('There was a problem when connecting to the SMF database.<br />' .
                                      'The database ' . $GLOBALS['wgSMF_MySQL_Database'] .
                                      ' was not found.<br />');
                }

            }
            else
            {

                // Connect to database.
                $fresMySQLConnection = mysql_connect($GLOBALS['wgDBserver'],
                                                     $GLOBALS['wgDBuser'],
                                                     $GLOBALS['wgDBpassword'],
                                                     true);

                // Check if we are connected to the database.
                if (!$fresMySQLConnection)
                {
                    $this->mySQLError('There was a problem when connecting to the SMF database.<br />' .
                                      'Check your Host, Username, and Password settings.<br />');
                }

                // Select Database: This assumes the wiki and SMF are in the same database.
                $db_selected = mysql_select_db($GLOBALS['wgDBname']);

                // Check if we were able to select the database.
                if (!$db_selected)
                {
                    $this->mySQLError('There was a problem when connecting to the SMF database.<br />' .
                                      'The database ' . $GLOBALS['wgDBname'] . ' was not found.<br />');
                }

            }

            $GLOBALS['gstrMySQLVersion'] = substr(mysql_get_server_info(), 0, 3); // Get the mysql version.

            return $fresMySQLConnection;
        }

        /**
         * If you want to munge the case of an account name before the final
         * check, now is your chance.
         */
        function getCanonicalName( $username )
        {

            // Connect to the database.
            $fresMySQLConnection = $this->connect();

            // Clean $username and force lowercase username.
            $username = htmlentities(strtolower($username), ENT_QUOTES, 'UTF-8');
            $username = str_replace('&#039;', '\\\'', $username); // Allow apostrophes (Escape them though)

            // Check MySQLVersion
            if ($GLOBALS['gstrMySQLVersion'] >= 4.1)
            {

                // Check Database for username. We will return the correct casing of the name.
                $fstrMySQLQuery = 'SELECT `memberName`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = CONVERT( _utf8 \'' . $username . '\' USING latin1 )
                                   COLLATE latin1_swedish_ci
                                   LIMIT 1';
            }
            else
            {

                // Check Database for username. We will return the correct casing of the name.
                $fstrMySQLQuery = 'SELECT `memberName`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = \'' . $username . '\'
                                   LIMIT 1';
            }

            // Query Database.
            $fresMySQLResult = mysql_query($fstrMySQLQuery, $fresMySQLConnection)
							 or die($this->mySQLError('Unable to view external table'));

            while($faryMySQLResult = mysql_fetch_assoc($fresMySQLResult))
            {
                return ucfirst($faryMySQLResult['memberName']);
            }
        }

        /**
         * When creating a user account, optionally fill in preferences and such.
         * For instance, you might pull the email address or real name from the
         * external user database.
         *
         * The User object is passed by reference so it can be modified; don't
         * forget the & on your function declaration.
         *
         * NOTE: This gets the email address from SMF for the wiki account.
         *
         * @param User $user
         * @access public
         */
        function initUser(&$user)
        {

            // Connect to the database.
            $fresMySQLConnection = $this->connect();

            // Clean $username and force lowercase username.
            $username = htmlentities(strtolower($user->mName), ENT_QUOTES, 'UTF-8');
            $username = str_replace('&#039;', '\\\'', $username); // Allow apostrophes (Escape them though)

            // Check MySQLVersion
            if ($GLOBALS['gstrMySQLVersion'] >= 4.1)
            {
                // Check Database for username and email address.
                $fstrMySQLQuery = 'SELECT `memberName`, `emailAddress`, `realName`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = CONVERT( _utf8 \'' . $username . '\' USING latin1 )
                                   COLLATE latin1_swedish_ci
                                   LIMIT 1';
            }
            else
            {
                // Check Database for username and email address.
                $fstrMySQLQuery = 'SELECT `memberName`, `emailAddress`, `realName`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = \'' . $username . '\'
                                   LIMIT 1';
            }

            // Query Database.
            $fresMySQLResult = mysql_query($fstrMySQLQuery, $fresMySQLConnection)
                or die($this->mySQLError('Unable to view external table'));

            while($faryMySQLResult = mysql_fetch_array($fresMySQLResult))
            {
                $user->mEmail       = $faryMySQLResult['emailAddress']; // Set Email Address.
                $user->mRealName    = $faryMySQLResult['realName'];     // Set Real Name.
            }

        }

        /**
         * Checks if the user is a member of the SMF group called wiki.
         *
         * @param string $username
         * @access public
         * @return bool
         * @todo Remove 2nd connection to database. For function isMemberOfWikiGroup()
         *
         */
        function isMemberOfWikiGroup($username)
        {
            // In LocalSettings.php you can control if being
            // a member of a wiki is required or not.
            if (isset($GLOBALS['wgSMF_UseWikiGroup']) && $GLOBALS['wgSMF_UseWikiGroup'] === false)
            {
                return true;
            }

            // Connect to the database.
            $fresMySQLConnection = $this->connect();

            // Check MySQL Version
            if ($GLOBALS['gstrMySQLVersion'] >= 4.1)
            {
                // Get all the groups the user is a member of.
                $fstrMySQLQuery = 'SELECT `additionalGroups`, `ID_GROUP`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = CONVERT( _utf8 \'' . $username . '\' USING latin1 )
                                   COLLATE latin1_swedish_ci
                                   LIMIT 1';
            }
            else
            {
                // Get all the groups the user is a member of.
                $fstrMySQLQuery = 'SELECT `additionalGroups`, `ID_GROUP`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = \'' . $username . '\'
                                   LIMIT 1';
            }

            // Query Database.
            $fresMySQLResult = mysql_query($fstrMySQLQuery, $fresMySQLConnection)
                               or die($this->mySQLError('Unable to view external table'));

            while($faryMySQLResult = mysql_fetch_array($fresMySQLResult))
            {
                $faryTmp   = explode(',', $faryMySQLResult['additionalGroups']); // This is a Comma Sep List
                $faryTmp[] = $faryMySQLResult['ID_GROUP'];
            }

            // Get all the groups the user is a member of.
            $fstrMySQLQuery = 'SELECT `ID_GROUP`
                               FROM `' . $GLOBALS['wgSMF_GroupsTB'] . '`
                               WHERE `groupName` = \'' . $GLOBALS['wgSMF_WikiGroupName'] . '\'
                               LIMIT 1';

            // Query Database.
            $fresMySQLResult = mysql_query($fstrMySQLQuery, $fresMySQLConnection)
                               or die($this->mySQLError('Unable to view external table'));

            if (empty($fresMySQLResult))
            {
                die($this->mySQLError('Config Error, Unable to find group called ' . $GLOBALS['wgSMF_WikiGroupName']));
            }

            while($faryMySQLResult = mysql_fetch_array($fresMySQLResult))
            {
                $fstrWikiID = $faryMySQLResult['ID_GROUP'];
            }

            // Check if the user group was found.
            if (empty($fstrWikiID) || !isset($fstrWikiID))
            {
                die($this->mySQLError('Config Error, Unable to find group called ' . $GLOBALS['wgSMF_WikiGroupName']));
            }

            if (array_search($fstrWikiID, $faryTmp) !== false)
            {
                return true; // User is in Wiki group.
            }
            else
            {
                return false; // User is not in Wiki group.
            }
        }

        /**
         * This is the MD5 Encryption SMF uses for passwords. Taken from Load.php
         *
         * @param string $data
         * @param string $key
         * @return string
         */
        function md5_hmac($data, $key)
        {
            // Check if the user has the cfg file setup correctly.
            if (!isset($GLOBALS['wgSMF_Version']))
            {
                die('<br />Error: You did not set $wgSMF_Version in your LocalSettings.php file.<br />
                     Please read the README file that came with the Auth_SMF plug-in for more info.<br />');
            }

            if (empty($GLOBALS['wgSMF_Version']))
            {
                die('<br />Error: You did not set $wgSMF_Version in your LocalSettings.php file.<br />
                     Please read the README file that came with the Auth_SMF plug-in for more info.<br />');
            }

            // Check that a valid version was passed.
            if ($GLOBALS['wgSMF_Version'] != '1.0' && $GLOBALS['wgSMF_Version'] != '1.1')
            {
                die('<br />Error: Value passed in $wgSMF_Version is not valid.<br />
                     Please read the README file that came with the Auth_SMF plug-in for more info.<br />');
            }

            if ($GLOBALS['wgSMF_Version'] == '1.0')
            {
                $key = strtolower($key);
        	    $key = str_pad(strlen($key) <= 64 ? $key : pack('H*', md5($key)), 64, chr(0x00));
        	    return md5(($key ^ str_repeat(chr(0x5c), 64)) . pack('H*', md5(($key ^ str_repeat(chr(0x36), 64)). $data)));
            }

            if ($GLOBALS['wgSMF_Version'] == '1.1')
            {
                return sha1(strtolower($key) . $data);
            }

            // This should never happen.
            die('<br />Error: Value passed in $wgSMF_Version is not valid.<br />
                 Please read the README file that came with the Auth_SMF plug-in for more info.<br />');
        }

        /**
         * Modify options in the login template.
         *
         * NOTE: Turned off some Template stuff here. Anyone who knows where
         * to find all the template options please let me know. I was only able
         * to find a few.
         *
         * @param UserLoginTemplate $template
         * @access public
         */
        function modifyUITemplate( &$template )
        {
            $template->set('usedomain',   false); // We do not want a domain name.
            $template->set('create',      false); // Remove option to create new accounts from the wiki.
            $template->set('useemail',    false); // Disable the mail new password box.
        }

        /**
         * This prints an error when a MySQL error is found.
         *
         * @param string $message
         * @access public
         */
        function mySQLError( $message )
        {
            echo $message . '<br />';
            echo 'MySQL Error Number: ' . mysql_errno() . '<br />';
            echo 'MySQL Error Message: ' . mysql_error() . '<br /><br />';
            exit;
        }

        /**
         * Set the domain this plugin is supposed to use when authenticating.
         *
         * NOTE: We do not use this.
         *
         * @param string $domain
         * @access public
         */
        function setDomain( $domain )
        {
            $this->domain = $domain;
        }

    	/**
    	 * Set the given password in the authentication database.
    	 * Return true if successful.
    	 *
    	 * NOTE: We only allow the user to change their password via phpBB.
    	 *
    	 * @param string $password
    	 * @return bool
    	 * @access public
    	 */
    	function setPassword( $password )
    	{
    		return true;
    	}

        /**
         * Return true to prevent logins that don't authenticate here from being
         * checked against the local database's password fields.
         *
         * This is just a question, and shouldn't perform any actions.
         *
         * Note: This forces a user to pass Authentication with the above
         *       function authenticate(). So if a user changes their SMF
         *       password, their old one will not work to log into the wiki.
         *       Wiki does not have a way to update it's password when SMF
         *       does. This however does not matter.
         *
         * @return bool
         * @access public
         */
        function strict()
        {
            return true;
        }

		/**
		 * Update user information in the external authentication database.
		 * Return true if successful.
		 *
		 * @param $user User object.
		 * @return bool
		 * @public
		 */
		function updateExternalDB( $user )
		{
			return true;
		}

        /**
         * When a user logs in, optionally fill in preferences and such.
         * For instance, you might pull the email address or real name from the
         * external user database.
         *
         * The User object is passed by reference so it can be modified; don't
         * forget the & on your function declaration.
         *
         * NOTE: Not useing right now.
         *
         * @param User $user
         * @access public
         */
        function updateUser( &$user )
        {
            return true;
        }

        /**
         * Check whether there exists a user account with the given name.
         * The name will be normalized to MediaWiki's requirements, so
         * you might need to munge it (for instance, for lowercase initial
         * letters).
         *
         * NOTE: MediaWiki checks its database for the username. If it has
         *       no record of the username it then asks. "Is this really a
         *       valid username?" If not then MediaWiki fails Authentication.
         *
         * @param string $username
         * @return bool
         * @access public
         * @todo write this function.
         */
        function userExists($username)
        {
            // Connect to the database.
            $fresMySQLConnection = $this->connect();

            // Clean $username and force lowercase username.
            $username = htmlentities(strtolower($username), ENT_QUOTES, 'UTF-8');
            $username = str_replace('&#039;', '\\\'', $username); // Allow apostrophes (Escape them though)

            // Check MySQL Version
            if ($GLOBALS['gstrMySQLVersion'] >= 4.1)
            {
                // Check Database for username.
                $fstrMySQLQuery = 'SELECT `memberName`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = CONVERT( _utf8 \'' . $username . '\' USING latin1 )
                                   COLLATE latin1_swedish_ci
                                   LIMIT 1';
            }
            else
            {
                // Check Database for username.
                $fstrMySQLQuery = 'SELECT `memberName`
                                   FROM `' . $GLOBALS['wgSMF_UserTB'] . '`
                                   WHERE `memberName` = \'' . $username . '\'
                                   LIMIT 1';
            }

            // Query Database.
            $fresMySQLResult = mysql_query($fstrMySQLQuery, $fresMySQLConnection)
                                or die($this->mySQLError('Unable to view external table'));

            while($faryMySQLResult = mysql_fetch_array($fresMySQLResult))
            {
                // print htmlentities(strtolower($username), ENT_QUOTES, 'UTF-8') . ' : ' . htmlentities(strtolower($faryMySQLResult['username']), ENT_QUOTES, 'UTF-8'); // Debug

                // Double check match.
                if (htmlentities(strtolower($username), ENT_QUOTES, 'UTF-8') ==
                    htmlentities(strtolower($faryMySQLResult['memberName']), ENT_QUOTES, 'UTF-8'))
                {
                    return true; // Pass
                }
            }
            return false; // Fail
        }

        /**
         * Check to see if the specific domain is a valid domain.
         *
         * @param string $domain
         * @return bool
         * @access public
         */
        function validDomain( $domain )
        {
            return true;
        }

    }

?>
