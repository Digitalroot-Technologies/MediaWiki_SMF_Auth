MediaWiki_SMF_Auth
==================

This plug-in makes MediaWiki use a SMF user database to authenticate with. This forces users to have a SMF account in order to log into the wiki. This should also force the user to be in a group called wiki. 

INSTALL:
=================

Create a group in SMF for your wiki users. I named mine "Wiki Editor".
You will need to put the name you choose in the code below.

NOTE: In order for a user to be able to use the wiki they will need to
be a member of the group you made in the step above.

Put Auth_SMF.php in /extensions/

Open LocalSettings.php. Put this at the bottom of the file. Edit as needed.

        /*-----------------[ Everything below this line. ]-----------------*/
        
        // This requires a user be logged into the wiki to make changes.
        $wgGroupPermissions['*']['edit'] = false; // MediaWiki Settings
        
        // Specify who may create new accounts: 0 means no, 1 means yes
        $wgGroupPermissions['*']['createaccount'] = false; // MediaWiki Settings
        
        // SMF User Database Plugin. (Requires MySQL Database)
        require_once './extensions/Auth_SMF.php';
        
        $wgSMF_WikiGroupName  = 'Wiki Editor';          // Name of your SMF group
                                                        // users need to be a member
                                                        // of to use the wiki. (i.e. wiki)
        
        $wgSMF_UseWikiGroup   = true;                   // This tells the Plugin to require
                                                        // a user to be a member of the above
                                                        // SMF group. (ie. wiki) Setting
                                                        // this to false will let any SMF
                                                        // user edit the wiki.
        
        $wgSMF_UseExtDatabase = false;                  // This tells the plugin that the SMF tables
                                                        // are in a different database then the wiki.
                                                        // The default settings is false.
        
        $wgSMF_Version = '1.0';                         // This is what version of SMF you are using.
                                                        // Current valid values are 1.0 and 1.1
        
        /*-[NOTE: You only need the next four settings if you set $wgSMF_UseExtDatabase to true.]-*/
        // $wgSMF_MySQL_Host     = 'host';               // SMF MySQL Host Name.
        // $wgSMF_MySQL_Username = 'username';           // SMF MySQL Username.
        // $wgSMF_MySQL_Password = 'password';           // SMF MySQL Password.
        // $wgSMF_MySQL_Database = 'database_name';      // SMF MySQL Database Name.
        
        $wgSMF_UserTB         = 'smf_members';        // Name of your SMF user table. (i.e. smf_members)
        $wgSMF_GroupsTB       = 'smf_membergroups';   // Name of your SMF groups table. (i.e. smf_membergroups)
        $wgAuth               = new Auth_SMF();       // Auth_SMF Plugin.
