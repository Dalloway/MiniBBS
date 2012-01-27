<?php

/**
 * This file contains the default text for MiniBBS's interface. Unless you're developing MiniBBS, don't
 * edit this file. Instead, use the message manager linked on the Stuff page. If you do edit this file for
 * a live board, the changes won't show immediately due to caching.
 *
 * MiniBBS is currently available only in English. To add another language to MiniBBS, create a copy of this file 
 * as /lang/xx.php, where xx is your language's ISO 639-1 language code, and then translate the values in $messages 
 * (leaving the keys untouched). You can then switch to the language from the admin dashboard. (Please contact
 * me (MiniBBS.org) if you do decide to translate!)
 *
 * (This file is still very far from completion -- only a fraction of the interface is currently translatable!) 
 *
 * For syntax documentation, etc., see /includes/class.language.php or the message manager. 
 */

$messages = array (

'Anonymous'                 => 'Anonymous',
'CAPTCHA preface'           => 'Please fill in the following CAPTCHA to continue:',
'DEFCON 2'                  => 'Posting has been temporarly disabled for all users.',
'DEFCON 3'                  => 'Posting has been temporarly disabled for non-regulars.',
'DEFCON 4'                  => 'Creation of new accounts has been temporarly disabled. If you have an account you can restore it in the dashboard.',
'Error: 404'                => 'The page you requested (<strong>$1</strong>) could not be located on the server.',
'Error: Access denied'      => 'You do not have permission to access this page.',
'Error: Invalid token'      => 'Your session expired. Try again.',
'Error: No ID'              => 'The page that you tried to access requires that you have a valid internal ID. This is supposed to be automatically created the first time you load a page here. Maybe you were linked directly to this page? Upon loading this page, assuming that you have cookies supported and enabled in your Web browser, you have been assigned a new ID. If you keep seeing this page, something is wrong with your setup; stop refusing/modifying/deleting cookies!',
'Lockdown mode'             => 'The board is currently in lockdown mode. Please come back later.',
'Notice label'              => '<strong>Notice</strong>:',
'Notice: New PM'            => 'You have <a href="{{DIR}}private_message/$1"><strong>$2</strong> unread</a> private {{PLURAL:$2|message|messages}}.',
'Notice: New PM clear'      => ' Too many? <a href="{{DIR}}dismiss_all_PMs" onclick="return quickAction(this, \'Really dismiss all current PMs?\')">Mark all as read</a>.',
'Notice: Reply posted'      => 'Reply posted.',
'Notice: Reply edited'      => 'Reply edited.',
'Notice: Topic created'     => 'Topic created.',
'Notice: Topic edited'      => 'Topic edited.',
'Notice: Voted'             => 'Thanks for voting.',
'Notice: Welcome'           => 'Welcome to <strong>$1</strong>. An account has automatically been created and assigned to you. You don\'t have to register or <a href="{{DIR}}restore_ID">log in</a> to use the board, but don\'t clear your cookies unless you have <a href="{{DIR}}dashboard">set a memorable name and password</a>.',
'Post: Help'                => 'Please familiarize yourself with the <a href="{{DIR}}markup_syntax">markup syntax</a> before posting.',
'PM: Deleted topic'         => 'One of your topics was recently deleted. You can find its remains [{{URL}}topic/$1 here].',
'PM: Deleted reply'         => 'One of your replies was recently deleted. You can find its remains [{{URL}}reply/$1 here].',
'Footer'                    => 'Powered by <strong><a href="http://minibbs.org">MiniBBS</a></strong>. This page was generated in <strong>$1</strong> seconds. $2% of that was spent running <strong>$3</strong> SQL queries. <noscript><br />Note: Your browser\'s JavaScript is disabled; some site features may not fully function.</noscript>',
'Report: Help'              => 'The report feature should be used to inform moderators of rule-breaking content. Please do not report a post simply because you disagree with it.',
'Search: Help'              => 'The search is not keyword-based; you must type an exact phrase.',
'Search: No results'        => '(No matches. Sorry.)',
'System'                    => '<em>System</em>',
'Topic: (OP)'               => '(OP)',
'Topic: (OP, you)'          => '(OP, you)',
'Topic: (you)'              => '(you)',
'Watchlist: No results'     => 'You haven\'t watched any topics yet. Once you do, you can keep track of their latest replies here.',

);

?>