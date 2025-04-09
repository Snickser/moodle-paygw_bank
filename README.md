# Impruved Bank transferences payment gateway

[![](https://img.shields.io/github/v/release/Snickser/moodle-paygw_bank.svg)](https://github.com/Snickser/moodle-paygw_bank/releases)

Этот плагин представляет собой платежный шлюз Moodle, который обеспечивает поддержку платежей, осуществляемых посредством денежных переводов или других методов ручной оплаты, которые могут быть одобрены человеком вручную.

Улучшения:
- Поддержка моего [enrol_yafee](https://moodle.org/plugins/enrol_yafee) плагина.
- Преподаватели курса могут управлять запросами, если у них есть соответствующие разрешения роли (paygw/bank:manageincourse).
- Можно настроить рассылку уведомлений не только на выделенный адрес, а также всем обладателям разрешений в роли, и ограничить рассылку внутри групп.
- Уведомления настраиваются независимо для каждого инстанса платёжного шлюза.
- Доступ к архиву загруженных файлов утверждённых заявок, с возможностью их удаления.
- Загруженные файлы имеют единую структуру имени.
- Предпросмотр pdf файлов в модальном окне.
- Режим автоматического утверждения заявок по таймеру, в которых имеются загруженные файлы.
- Возможность студенту заменять файлы в заявке.
- Режим рекомендованной нефиксированной цены с ограничением максимума.
- Можно установить фиксированный комментарий платежам.


## Instalation.

This plugin is tested in Moodle 4.3+

You can download the zip file and install directly in "Site administration" > Plugins > "Install plugins"

## Global configuration.

The plugin has the following configurations in the plugin section:
- Allow user add files. The user can (or must, depending of the instructions), upload files that proves the payment.
- Surcharge. The surcharge is an additional percentage charged to users who choose to pay using this payment gateway.
- Send confirmation email. An email is sent to user if the payment is approved.
- Send denied email an email is sent to user if the payment is denied.
- Automatically approve request after file(s) are uploaded.
 

The mail texts are in the language strings of the plugin.

## Add this payment gateway to your courses.

Just as all the payment gateways, add the bank transference to a payment account. You must configure the instructions shown to the user in the payment process, acording to your process.

## Management of payment request.

"Site administration" > "General" > "Bank transfer" > "Manage transfers", or in "More" in course, you can see the list of pending payments, and access to the files attached if the option is enabled. You can deny or approve the payments. If you approve the payment, automatically the element purchased is served (f.e it the user buy an enrollment to a course, the enrollment is created).
