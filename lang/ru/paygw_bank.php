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
 * @package    paygw_bank
 * @subpackage bank
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['approve'] = 'Утвердить заявку';
$string['are_you_sure_cancel'] = 'Вы точно уверены что хотите отменить запрос?';
$string['cancel_process'] = 'Отменить';
$string['code'] = 'Код платежа';
$string['codeprefix'] = 'Префикс кода платежа';
$string['concept'] = 'Описание';
$string['cost'] = 'Цена';
$string['deny'] = 'Отказать';
$string['file_already_uploaded'] = 'Файл с таким именем уже загружен';
$string['file_uploaded'] = 'Файл загружен';
$string['gatewaydescription'] = 'Bank Transfer - это ручной способ оплаты и денежных переводов по реквизитам, с возможным предоставлением подтверждающих документов.';
$string['hasfiles'] = 'Файлы';
$string['mails_sent'] = 'Письма отправлены';
$string['mail_confirm_pay'] = 'Уважаемый(ая) {$a->username}!
Ваш платёж за "{$a->concept}" с кодом {$a->code} подтверждён.

{$a->url}';
$string['mail_confirm_pay_subject'] = 'Запрос подтверждён';
$string['mail_denied_pay'] = 'Уважаемый(ая) {$a->username}!
Ваш запрос c кодом платежа "{$a->code}" на "{$a->concept}" отклонён.';
$string['mail_denied_pay_subject'] = 'Запрос отклонён';
$string['max_number_of_files'] = 'Максимальное кол-во файлов';
$string['my_pending_payments'] = 'Мои ожидающие платежи';
$string['noentriesfound'] = 'Нет запросов.';
$string['payments'] = 'Платежи';
$string['payment_denied'] = 'Вы отменили запрос';
$string['pending_payments'] = 'Ожидаемые трансфертные платежи';
$string['pluginname_desc'] = 'Плагин «Банковский перевод» позволяет оплачивать курсы банковским переводом или другими способами оплаты вручную.';
$string['send'] = 'Отправить';
$string['sendmailtoselected'] = 'Написать письмо всем выбранным';
$string['start_process'] = 'Начать процесс оплаты';
$string['today_cost'] = 'На сегодня';
$string['total_cost'] = 'Сумма';
$string['transfer_code'] = 'Код платежа';
$string['transfer_process_initiated'] = 'Процесс банковского перевода активирован';
$string['email_notifications_subject_new'] = 'Новая заявка на оплату';
$string['email_notifications_subject_attachments'] = 'В заявку был добавлен документ';
$string['email_notifications_subject_confirm'] = 'Заявка подтверждена';
$string['email_notifications_new_request'] = 'Новая заявка на оплату в курс "{$a->course}".
Код "{$a->code}" от {$a->userfullname} ({$a->useremail}) с описанием "{$a->concept}".
Группа - {$a->groups}

Просмотр заявки {$a->url}';
$string['email_notifications_new_attachments'] = 'Файл в заявку с кодом "{$a->code}" от {$a->userfullname} ({$a->useremail}) для курса "{$a->course}" с пометкой "{$a->concept}" добавлен.
Группа - {$a->groups}

Просмотр заявки {$a->url}';
$string['email_notifications_confirm'] = 'Заявка с кодом "{$a->code}" от {$a->userfullname} ({$a->useremail}) в курс "{$a->course}" с пометкой "{$a->concept}" утверждена.
 {$a->teacher}';
$string['unpaidnotice'] = 'Просрочено!';
$string['unpaidtimeend'] = 'Конечная дата';
$string['pendingrequests'] = 'Все активные запросы';
$string['onlyingroup'] = 'Отправлять уведомления учителям только в пределах их групп';
