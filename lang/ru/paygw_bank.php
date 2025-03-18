<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local language pack from https://edu1080.duckdns.org
 *
 * @package    paygw
 * @subpackage bank
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['approve'] = 'Зачислить в курс';
$string['are_you_sure_cancel'] = 'Вы точно уверены что хотите отменить запрос?';
$string['cancel_process'] = 'Отменить';
$string['code'] = 'Код оплаты';
$string['codeprefix'] = 'Префикс кода оплаты';
$string['concept'] = 'Описание';
$string['cost'] = 'Цена';
$string['deny'] = 'Отказать';
$string['file_already_uploaded'] = 'Файл уже был загружен';
$string['file_uploaded'] = 'Файл загружен';
$string['gatewaydescription'] = 'Bank Transfer - это ручной способ оплаты с использованием подтверждающих документов.';
$string['hasfiles'] = 'Вложения';
$string['mails_sent'] = 'Письма отправлены';
$string['mail_confirm_pay'] = 'Уважаемый {$a->username}. Ваш платёж за "{$a->concept}" подтверждён.<br/> код: {$a->code}';
$string['mail_confirm_pay_subject'] = 'Запрос подтверждён';
$string['mail_denied_pay'] = 'Уважаемый {$a->username}. Ваш запрос за "{$a->concept}" отклонён. <br/> код: {$a->code}';
$string['mail_denied_pay_subject'] = 'Запрос отклонён';
$string['max_number_of_files'] = 'Максимальное кол-во файлов';
$string['my_pending_payments'] = 'Мои ожидающие платежи';
$string['noentriesfound'] = 'Нет запросов';
$string['payments'] = 'Платежи';
$string['payment_denied'] = 'Вы отменили платеж';
$string['pending_payments'] = 'Ожидаемые трансфертные платежи';
$string['pluginname_desc'] = 'Плагин «Банковский перевод» позволяет оплачивать курсы банковским переводом или другими способами оплаты вручную.';
$string['send'] = 'Отправить';
$string['sendmailtoselected'] = 'Написать письмо всем выбранным';
$string['send_confirmation_mail'] = 'Написать письмо всем выбранным';
$string['start_process'] = 'Начать процесс оплаты';
$string['today_cost'] = 'На сегодня';
$string['total_cost'] = 'Сумма';
$string['transfer_code'] = 'Код оплаты';
$string['transfer_process_initiated'] = 'Процесс ручного перевода начат';
$string['donate'] = '<div>Версия плагина: {$a->release} ({$a->versiondisk})<br>
Новые версии плагина вы можете найти на <a href=https://github.com/Snickser/moodle-paygw_bank>GitHub.com</a>
<img src="https://img.shields.io/github/v/release/Snickser/moodle-paygw_bank.svg"><br>
Пожалуйста, отправьте мне немножко <a href="https://yoomoney.ru/fundraise/143H2JO3LLE.240720">доната</a>😊</div>
TRX TRGMc3b63Lus6ehLasbbHxsb2rHky5LbPe<br>
BTC 1GFTTPCgRTC8yYL1gU7wBZRfhRNRBdLZsq<br>
ETH 0x1bce7aadef39d328d262569e6194febe597cb2c9<br>
<iframe src="https://yoomoney.ru/quickpay/fundraise/button?billNumber=143H2JO3LLE.240720"
width="330" height="50" frameborder="0" allowtransparency="true" scrolling="no"></iframe>';
