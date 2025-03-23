# Impruved Bank transferences payment gateway

This plugin, is a moodle payment gateway that provides support to payments made by bank transferences, or another manual payment methods that need to be approved by a person.

- Supports my enrol_yafee plugin.
- Course teachers can manage requests if they have the appropriate role permissions.
- Notifications in courses are sent to everyone who has an administrative role. 

## Instalation.

This plugin is tested in Moodle 4.3 and 4.5.

You can download the zip file and install directly in "Site administration " > Plugins > "Install plugins"

## Global configuration.

The plugin has the following configurations in the plugin section:
- Allow user add files. The user can (or must, depending of the instructions), upload files that proves the payment.
- Surcharge. The surcharge is an additional percentage charged to users who choose to pay using this payment gateway.
- Send confirmation email. An email is sent to user if the payment is approved.
- Send denied email an email is sent to user if the payment is denied.

The mail texts are in the language strings of the plugin.

## Add this payment gateway to your courses.

Just as all the payment gateways, add the bank transference to a payment account. You must configure the instructions shown to the user in the payment process, acording to your process.

## Management of payment request.

"Site administration " > "Bank transference" > "Manage transfer" you can see the list of pending payments, and access to the files attached if the option is enabled.  You can deny or approve the payments. If you approve the payment, automatically the element purchased is served (f.e it the user buy an enrollment to a course, the enrollment is created).
