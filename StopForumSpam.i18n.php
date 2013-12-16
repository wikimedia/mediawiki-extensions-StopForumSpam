<?php

$messages = array();

/** English
 * @author Kunal Mehta
 */
$messages['en'] = array(
	'stopforumspam-desc' => 'Allows administrators to send data to [http://stopforumspam.com/ stopforumspam.com]',
	'stopforumspam-checkbox' => 'Send user information to stopforumspam.com',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'stopforumspam.com confidence level',
	'stopforumspam-blocked' => 'Editing from your IP address ($1) has been blocked since it has recently been used to spam websites.',
	'stopforumspam-is-blocked' => 'The IP address <strong>[http://stopforumspam.com/ipcheck/$1 $1]</strong> has been blocked for spamming.',
);

/** Message documentation (Message documentation)
 * @author Kunal Mehta
 * @author Shirayuki
 */
$messages['qqq'] = array(
	'stopforumspam-desc' => '{{desc|name=StopForumSpam|url=http://www.mediawiki.org/wiki/Extension:StopForumSpam}}',
	'stopforumspam-checkbox' => "Used as label for checkbox on [[Special:Block]] for administrators to submit a user's information.",
	'abusefilter-edit-builder-vars-sfs-confidence' => 'Abuse filter syntax option in a dropdown from the group {{msg-mw|abusefilter-edit-builder-group-vars}}.',
	'stopforumspam-blocked' => "Message a user sees when their IP address is blacklisted and they cannot edit.

Parameters:
* $1 - the user's current IP address",
	'stopforumspam-is-blocked' => 'Shown when going to block an IP address that is currently blacklisted.

Parameters:
* $1 - the IP address',
);

/** German (Deutsch)
 * @author Metalhead64
 */
$messages['de'] = array(
	'stopforumspam-desc' => 'Ermöglicht es Administratoren, Daten an [http://stopforumspam.com/ stopforumspam.com] zu senden.',
	'stopforumspam-checkbox' => 'Benutzerinformation an stopforumspam.com senden',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'stopforumspam.com-Vertrauensstufe',
	'stopforumspam-blocked' => 'Das Bearbeiten von deiner IP-Adresse ($1) wurde gesperrt, seit sie kürzlich auf Spam-Websites verwendet wurde.',
	'stopforumspam-is-blocked' => 'Die IP-Adresse <strong>[http://stopforumspam.com/ipcheck/$1 $1]</strong> wurde für Spamming gesperrt.',
);

/** Japanese (日本語)
 * @author Shirayuki
 */
$messages['ja'] = array(
	'stopforumspam-desc' => '管理者がデータを [http://stopforumspam.com/ stopforumspam.com] に送信できるようにする',
	'stopforumspam-checkbox' => '利用者情報を stopforumspam.com に送信',
	'stopforumspam-blocked' => 'あなたの IP アドレス ($1) は、ウェブサイトへのスパム攻撃に最近使用されたため、編集ブロックされています。',
	'stopforumspam-is-blocked' => 'IP アドレス <strong>[http://stopforumspam.com/ipcheck/$1 $1]</strong> は、スパム攻撃を行なったためブロックされています。',
);

/** Russian (русский)
 * @author Okras
 */
$messages['ru'] = array(
	'stopforumspam-desc' => 'Позволяет администраторам отправлять данные на [http://stopforumspam.com/ stopforumspam.com]',
	'stopforumspam-checkbox' => 'Отправить информацию об участнике на stopforumspam.com',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'уровень доверия stopforumspam.com',
	'stopforumspam-blocked' => 'Внесение правок с вашего IP-адреса ($1) было заблокировано, так как он недавно был использован для спама веб-сайтов.',
	'stopforumspam-is-blocked' => 'IP-адрес <strong>[http://stopforumspam.com/ipcheck/$1 $1] </strong> был заблокирован за спам.',
);

/** Ukrainian (українська)
 * @author Andriykopanytsia
 */
$messages['uk'] = array(
	'stopforumspam-desc' => 'Дозволяє адміністраторам надсилати дані до [http://stopforumspam.com/ stopforumspam.com]',
	'stopforumspam-checkbox' => 'Надсилати відомості про користувача до stopforumspam.com',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'рівень довіри stopforumspam.com',
	'stopforumspam-blocked' => 'Редагування з вашої IP-адреси ($1) вже заблоковане, позаяк вона нещодавно використовувалася для спам-сайтів.',
	'stopforumspam-is-blocked' => 'IP-адреса <strong>[http://stopforumspam.com/ipcheck/$1 $1]</strong> вже заблокована за розсилку спаму.',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Yfdyh000
 */
$messages['zh-hans'] = array(
	'stopforumspam-desc' => '允许管理员发送数据到[http://stopforumspam.com/ stopforumspam.com]',
	'stopforumspam-checkbox' => '发送用户信息到stopforumspam.com',
	'abusefilter-edit-builder-vars-sfs-confidence' => 'stopforumspam.com信任级别',
	'stopforumspam-blocked' => '已禁止从您的IP地址 ($1) 编辑，因为它最近被用于网站垃圾信息。',
	'stopforumspam-is-blocked' => 'IP地址 <strong>[http://stopforumspam.com/ipcheck/$1 $1]</strong> 已被封禁，以免垃圾信息侵袭。',
);
