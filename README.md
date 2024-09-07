# README

## Changelog

### Version 5.5.1 - 26.10.2023

* Fix - Use filter woocommerce_available_payment_gateways to conditionally show TWINT on the checkout.
* Fix - Correctly sort logs.

### Version 5.5.0 - 23.10.2023

* Feature - Added WooCommerce HPOS compatibility.
* Fix - Monitor order if order in incorrect state in webhook response.
* Fix - Save order confirmation pending status.
* Enhancement - Show different notice for timed out payments.
* Enhancement - Try to cancel order if customer cancels the payment.

### Version 5.4.2 - 11.10.2023

* Enhancement - WC_Blocks: use payment_gateways function instead of get_available_payment_gateways to prevent calling woocommerce_available_payment_gateways filter hook.

### Version 5.4.1 - 19.07.2023

* Fix - Correctly save certificate password for HTML encoded input.

### Version 5.4.0 - 28.04.2023

* Feature - Added setting to force cURL request with IPv4. 
* Fix - Admin_Ajax::confirm_transaction: return order_status and order_reason.

### Version 5.3.1 - 31.03.2023

* Enhancement - WC blocks: use payment_gateways instead of get_available_payment_gateways to check if gateway is enabled.

### Version 5.3.0 - 10.03.2023

* Feature - Added option to start background task via cron job.
* Enhancement - Background task: check if maximum execution time is almost reached and spawn new background task.
* Enhancement - Background task: check if memory limit is almost reached and cancel transaction.
* Fix - Type in AJAX nopriv background task action.
* Fix - TransactionHandler: check if TWINT URL exists before checking timestamp.

### Version 5.2.1 - 01.03.2023

* Enhancement - Activated AJAX background task request by default.

### Version 5.2.0 - 17.02.2023

* Feature - Added option to start background task via AJAX request.
* Fix - Correctly check and update pending orders in cron job.

### Version 5.1.0 - 14.02.2023

* Feature - Added option to run async task immediately (default).
* Fix - Use correct home URL for plugin updates.

### Version 5.0.6 - 07.02.2023

* Fix - Strip slashes for password in setup editor.

### Version 5.0.5 - 07.02.2023

* Fix - Spawn background task when order_uuid already exists.
* Fix - Correctly display gateway toggle in setup assistant.
* Dev - Removed Parameter OrderUpdateNotificationURL in SOAP request.

### Version 5.0.4 - 13.01.2023

* Fix - Use order currency instead of WC currency if available.

### Version 5.0.3 - 23.12.2022

* Fix - Add cron action to confirm order after some time if order confirmation fails. Send email if failing.
* Enhancement - Retry order confirmation if in wrong status.

### Version 5.0.2 - 19.12.2022

* Fix - Initially create folders and save DB version.
* Fix - CancelOrder function: get correct data from TWINT.
* Fix - Timed lock comparison.

### Version 5.0.1 - 14.12.2022

* Fix - Enqueue frontend scripts.

### Version 5.0.0 - 12.12.2022

* Feature - Implemented hosted TWINT checkout page.
* Feature - Added TWINT enable/disable toggle to setup assistant.
* Feature - Added settings tab to display and download file logs.
* Enhancement - Update field values when saving setup assistant steps.
* Enhancement - Directly start order without checkin first.
* Enhancement - Removed license check cron.
* Enhancement - Used timed lock for order status update.
* Enhancement - Removed unused settings page.
* Performance - Use file logging instead of DB logging.
* Performance - Replaced per-order cron actions with single cron job for all orders.
* Dev - Always use PAYMENT_DEFERRED.
* TWINT - Use API version 8.4.

### Version 4.1.1 - 14.11.2022

* Fix - Add successful order to transactions.
* Enhancement - Replaced comma with dot for amount on checkout page.
* Enhancement - Removed logging in order status check.

### Version 4.1.0 - 08.11.2022

* Feature - Added button in metabox to cancel unconfirmed transactions.
* Enhancement - Always require confirmOrder request for all TWINT orders.
* Enhancement - Show order confirmation and cancellation buttons in metabox for all unconfirmed orders.
* Fix - Only monitor order from monitor checkin if startOrder was called and UUID is saved.

### Version 4.0.2 - 07.10.2022

* Fix - Don't aquire start order lock in monitor_checkin function.
* Fix - User agent check in WC blocks.
* Fix - Check if fee property exists before accessing amount.
* Enhancement - Skip payment complete lock if it can't be acquired within reasonable time.
* Enhancement - Added request ID to logs.

### Version 4.0.1 - 23.08.2022

* Fix - Format string correctly in sprintf.
* Fix - Check if user agent exists.

### Version 4.0.0 - 26.07.2022

* Feature - Setup assistant on settings page.
* Feature - Added setting for deferred payments and button to settle deferred payment.
* Feature - Select order status for deferred payments.
* Fix - Order status check: check if order exists.
* Fix - correctly update order data if payment is complete (IMMEDIATE).
* Enhancement - Added WP version, WC version and PHP version to logs.
* Enhancement - Monitor order on status NO_PAIRING in monitorCheckin if order was started.
* Enhancmenet - User feedback when code is copied to clipboard.
* Dev - Use API version 8.3.

### Version 3.4.0 - 05.05.2022

* Feature - Download logs button.
* Feature - Added option to select order status for non-virtual orders.
* Feature - Added instructions field to payment method settings.
* Fix - Removed duplicate slash from asset paths.
* Enhancement - Close logs overlay on click outside of overlay.

### Version 3.3.1 - 10.03.2022

* Fix - WC_Helper: check if cart exists before emptying it.

### Version 3.3.0 - 20.01.2022

* Feature - Added setting to set SOAP connection timeout.
* Fix - Remove BeaconId check when enrolling cash register.
* Enhancement - Return status TIMEOUT when repeated request fails and schedule status check.
* Enhancement - Lower default SOAP connection timeout (25).

### Version 3.2.9 - 29.11.2021

* Fix - Display switch-to-app button in mobile in-app browsers.
* CSS - Removed top border of payment mask in Safari browser.

### Version 3.2.8 - 01.11.2021

* Fix - removed duplicate actions from Order_Ajax_Handler.

### Version 3.2.7 - 27.10.2021

* Fix - Save order UUID on successful order.
* Enhancement - Request order data to save transaction if not data is provided.
* Logs - Added user agent info.

### Version 3.2.6 - 22.10.2021

* Fix - Replace undefined constants for license check.

### Version 3.2.5 - 21.10.2021

* Fix - Get TWINT gateway in WC_Blocks_Payment_Method_Type::get_payment_method_script_handles.

### Version 3.2.4 - 21.10.2021

* Fix - Check existing payment methods in WC_Blocks_Payment_Method_Type::is_active.

### Version 3.2.3 - 18.10.2021

* Enhancement - Use DB lock for exclusive access to complete order.

### Version 3.2.2 - 12.10.2021

* Dev - Added filter to modifiy Store UUID.

### Version 3.2.1 - 11.10.2021

* Fix - Directly include dependencies for WP Blocks scripts.

### Version 3.2.0 - 11.10.2021

* Feature - Added support for WC Blocks.
* Dev - Added filter to modify SOAP data.

### Version 3.1.0 - 05.10.2021

* Fix - Don't use repeated requests for StartOrder request.
* Fix - Use lock per pairing UUID.
* Fix - Check if order UUID exists when StartOrder fails.
* Enhancement - Use DB lock for exclusive StartOrder request.
* Enhancement - Don't delete Order UUID when payment fails.

### Version 3.0.5 - 28.09.2021

* Fix - Remove new from wc_get_order in Event_Handler.

### Version 3.0.4 - 27.09.2021

* Fix - Only save PairingUUID for process which started order.
* Enhancement - Replace usage of WC_Order constructor with wc_get_order function.

### Version 3.0.3 - 22.09.2021

* Fix - Correctly lock startOrder and payment update to prevent concurrent access.
* Enhancement - Added filter for currency.

### Version 3.0.2 - 17.09.2021

* Fix - Prevent serialization of Closure when updating post meta for logs.
* Fix - Check if license status is not false before accessing array offset.

### Version 3.0.1 - 08.09.2021

* Fix - Removed call to die() when loading template in WC_Gateway_Twint.

### Version 3.0.0 - 01.09.2021

* Enhancement - Manually empty cart after payment.
* Enhancement - Cancel payment when customer clicks back to basket button.
* Enhancement - Cache result of network license check.
* Enhancement - Changed timeout to be applied only once. Default timeout = 180.
* Enhancement - Added error messages to checkout.
* Enhancement - Also abort checkin/payment on timeout.
* Fix - Check if response contains OrderStatus or Status object since TWINT sometimes returns one or the other.
* Fix - Correctly handle NO_PAIRING status.
* TWINT - Use API version 5.1.
* Dev - Refactored payment process.

### Version 2.2.4 - 19.07.2021

* Enhancement - Cache result of network license check.

### Version 2.2.3 - 21.06.2021

* Fix - Manually empty cart after successful payment to account for problem where cart is not emptied for mobile
  payments.

### Version 2.2.2 - 21.06.2021

* Fix - Check for different response structures in StartOrder response.

### Version 2.2.1 - 10.05.2021

* Enhancement - Added filter for merchant reference order id.
* Enhancement - Set flag if payment was intiated via TWINT to display metabox.
* Fix - Certificate renewal: delete temporary file instead of new certificate.
* CSS - Price font.
* Dev - Added constant TWINT_DEBUG to prevent log posts from being public when WP_DEBUG is set.

### Version 2.2.0 - 18.03.2021

* Feature - Option for order status for virtual orders.
* Feature - Added setting to change SOAP call interval and set default to 2 seconds.
* Enhancement - Check if startOrder already called for order_uuid to prevent startOrder from being called multiple
  times.
* Enhancement - Trim spaces around UUID before saving.
* Enhancement - Save all logs in one post and log all payment events.
* Dev - Added template loader and moved payment page to template.

### Version 2.1.12 - 04.02.2021

* Fix - WPML Multilingual did not use correct currency and amount in AJAX calls.

### Version 2.1.11 - 25.01.2021

* CSS - Defined a max-width for the qr code and the description box.

### Version 2.1.10 - 20.01.2021

* Fix - Check if WooCommerce is active before including settings tab.

### Version 2.1.9 - 30.12.2020

* Fix - Use plugin name instead of item ID for update check.
* Fix - Load settings page on init hook for WC version lower than 3.0.2.

### Version 2.1.8 - 21.11.2020

* Fix - Continue with MonitorCheckin on pairing status NO_PAIRING.

### Version 2.1.7 - 03.11.2020

* Fix - Always use weak cipher since SoapClient seems to cache the stream context.

### Version 2.1.6 - 21.10.2020

* Fix - Use wc_get_order instead of WC_Order constructor in Log_List_Table.
* Fix - Manually load WC_Settings_Tab_Twint in woocommerce_get_settings_pages filter to make sure that WC_Settings_Page
  is loaded.

### Version 2.1.5 - 09.10.2020

* Enhancement - Retry SOAP request with weak/strong cipher based on previously used cipher.
* Fix - Added correct links to system emails.

### Version 2.1.4 - 30.09.2020

* Fix - Check if get_post_timestamp exists. The function is only available in WooCommerce version 5.3 or newer.

### Version 2.1.3 - 28.09.2020

* Enhancement - Only show TWINT metabox for TWINT orders.
* Fix - Limit Register ID to 50 characters.

### Version 2.1.2 - 22.09.2020

* Fix - Certificate expiry check: fixed RenewalAllowed property access.
* Fix - Added option to use weaker cipher for SoapClient connection.
* Fix - Include files on plugins_loaded hook.

### Version 2.1.1 - 11.09.2020

* Fix - Check if file exists before autoloading.
* Fix - Always check if certificate file directory exists before saving the certificate file.

### Version 2.1.0 - 06.09.2020

* Enhancement - Display errors and license data on license activation/deactivation/check.
* Enhancement - Log license errors.
* Enhancement - Raised POST request timeout for license check.
* Dev - Updated Plugin_Updater.

### Version 2.0.6 - 04.09.2020

* Fix - Correct link to TWINT settings tab in license notice.
* Fix - Correct plugin_file for plugin_action_links_ hook.

### Version 2.0.5 - 01.09.2020

* Enhancement - Set unpaid orders to 'cancelled' instead of 'failed'.
* Fix - Show App icons also for Facebook in-app browser.

### Version 2.0.4 - 29.08.2020

* Fix - Corrected plugin file path for Plugin_Updater.

### Version 2.0.3 - 29.08.2020

* Fix - Removed logging of user agent which led to errors in some cases.
* Fix - Added page number to logs query in Log_List_Table.

### Version 2.0.2 - 24.08.2020

* Fix - Check if function is callable before invoking SoapClient function.
* Fix - Return JSON error message on error for payment page request.

### Version 2.0.1 - 21.08.2020

* Fix - Check if SOAP is enabled before loading plugin. Show notice if SOAP is not enabled.

### Version 2.0.0 - 21.08.2020

* Feature - Added log list table to TWINT settings section.
* Feature - Added option to set number of logs to settings.
* Feature - Added option to set number of days after which to delete logs to settings.
* Feature - Added option to define the interval of the system check to settings.
* Feature - Added automatic renewal of TWINT certificate.
* Feature - Send admin email when TWINT fails to connect on the checkout.
* Feature - Added transaction status check button to order admin.
* Feature - Added buttons to check certificate expiration and renew certificate.
* Feature - Added weekly certificate expiration check.
* Enhancement - Added fees to transaction list.
* Enhancement - Perform system check three times before sending email.
* Enhancement - Retry confirming payment if it fails.
* Enhancement - Added backtracks to logs.
* Fix - Removed database update notice on first install.
* Fix - Show App icons also for Instagram in-app browser.
* Fix - Issue where certificate file was overwritten with legacy certificate data from database.
* Fix - Leave order pending instead of setting it to failed for status FAILURE and NO_PAIRING.
* Update - Using version 2.1 of TWINTMerchantService.
* Dev - Moved classes into namespaces.

### Version 1.11.7 - 23.07.2020

* Fix - Renamed WP_Background_Process to Mame_Background_Process to prevent naming conflicts.
* Fix - Renamed WP_Async_Request to Mame_Async_Request to prevent naming conflicts.

### Version 1.11.6 - 22.07.2020

* Fix - Only set payment to failed if order is not complete.
* CSS - Changed payment icon max height to 30px.

### Version 1.11.5 - 05.06.2020

* Enhancement - Use checkout icon in SVG format.

### Version 1.11.4 - 04.06.2020

* Fix - Removed status change of order to pending payment in wait_for_pairing function.

### Version 1.11.3 - 28.05.2020

* Fix - Compare against navigator.platform to detect iOS devices.

### Version 1.11.2 - 27.05.2020

* Fix - Backend AJAX request: hide loader on error.
* Fix - Detect if device is iOS case insensitive.
* Dev - Added TWINT test mode which can be used by setting the constant TWINT_ENVIRONMENT.

### Version 1.11.1 - 15.05.2020

* Fix - Set keep_alive of SoapClient to false. Otherwise the SoapClient reuses the connection without handshake and it
  might time out before a response is received. The default PHP timout is 5 seconds and SOAP requests are made every 5
  seconds.

### Version 1.11.0 - 12.05.2020

* Enhancement - Add a scheduled event when payment is initiated to check payment status after some time.
* Enhancement - Fail orders where a negative response from TWINT is received. Retry all payments which time out or where
  the status is unclear.
* Enhancement - Retry payments where a SoapFault is received.
* Dev - Added new status RETURN_TO_CHECKOUT for JSON response to return to checkout without changing status.
* Dev - Removed all code related to the environment field which was removed in version 1.10.0.
* Fix - Raised SoapClient socket timeout which led to errors.

### Version 1.10.0 - 09.05.2020

* Enhancement - Return error messages for every step in the certificate conversion for the certificate upload field.
* Enhancement - Removed unnecessary test environment fields which lead to confusion.
* Enhancement - Added more explanations to settings fields.
* Enhancement - Removed enrolment step from certificate upload field.
* Enhancement - Convert pfx files to pem (certificate upload field).
* Fix - Mask more special characters before saving the certificate password.
* CSS - Changed button styles.

### Version 1.9.0 - 01.05.2020

* Feature - Added option to check pending payments and set them to status failed after some time.
* Enhancement - Don't set payments to status failed when payment fails. Instead keep status pending payment and show a
  message on the checkout page.
* Tweak - Changed retry interval to 5 seconds to speed up payment process.

### Version 1.8.1 - 23.04.2020

* Enhancement - Schedule event to check and update payment status when payment fails.
* Fix - Set payment status to failed on timeout.
* Tweak - Set payment status to failed instead of cancelled if payment is not successful for any reason.
* Tweak - Set default timeout to 300.

### Version 1.8.0 - 19.04.2020

* Feature - Added payment icons of most used apps to payment mask.
* Feature - Option to add customer details to reference number.

### Version 1.7.9 - 17.04.2020

* Fix - Changed default action for AJAX response to retry and check the payment status instead of aborting.
* Tweak - Added single event to check and update the payment status after 2 minutes after a payment is completed.

### Version 1.7.8 - 07.04.2020

* Fix - Added class Mame_WC_Helper to make plugin compatible to WooCommerce versions prior to 2.7.0.

### Version 1.7.7 - 16.03.2020

* Compatibility - Added WooCommerce version 4.0.0 compatibility header.

### Version 1.7.6 - 06.03.2020

* Fix - Check if woocommerce class exists to check if plugin is active instead of using the is_plugin_active function to
  prevent errors for mu-plugins and renamed folders.

### Version 1.7.5 - 06.03.2020

* Fix - Moved check for active WooCommerce plugin and other filters and actions into plugins_loaded hook.
* Fix - Removed modification of undefined element #logo-container in twint-redirect.js.

### Version 1.7.4 - 04.03.2020

* Fix - Use woocommerce_payment_gateways filter independently on payment gateway class initialization.

### Version 1.7.3 - 25.02.2020

* Fix - Initialize gateway on init hook to allow check for changed currency.

### Version 1.7.2 - 24.02.2020

* Fix - Added option to check for currency CHF to conditionally show payment gateway on the frontend.

### Version 1.7.1 - 23.02.2020

* Fix - Include plugin.php before calling is_plugin_active function.

### Version 1.7.0 - 23.02.2020

* Feature - Added cron event to enroll cash register and send an email if it fails.
* Feature - Certificate upload field: convert uploaded txt files to pem.
* Feature - Added option to show payment processing notice on the payment page.
* Tweak - Only enable TWINT gateway if currency is CHF.
* Tweak - Removed back to cart button on the bottom of the payment page.
* Tweak - Updated frontend payment mask for better mobile device UX.
* Tweak - Removed certificate text field.
* Security - Certificate conversion: delete uploaded file after conversion to pem.
* Localization - Added language files for de_CH_informal.

### Version 1.6.2 - 05.09.2019

* Tweak - Added CSS class to QR code image to prevent Jetpack lazy loading.
* Fix - Mask special characters in password before saving.

### Version 1.6.1 - 26.06.2019

* Feature - Display Logs in order admin screen.
* Tweak - Prune logs in background process if they exceed the threshold.
* Tweak - Added de_CH translation files.
* Tweak - Load payment mask via AJAX to prevent requests to TWINT on checkout page.
* Dev - Added background process functionality.

### Version 1.6.0 - 27.05.2019

* Feature - Added single transactions to TWINT meta box.
* Feature - Added Logging class to provide more logs for payment process.
* Fix - Display meta box.
* Tweak - Minified js files.
* Dev - Save TWINT data in hidden meta array.
* Dev - Added certificate handler.
* Dev - Added gulp dev tools.

### Version 1.5.8 - 06.05.2019

* Fix - Use property instead of constant for display name.

### Version 1.5.7 - 06.05.2019

* Fix - Use returned URL on checkout to redirect to the corresponding screen instead of the URL in the wrapper element.
* Fix - Decode HTML entities before sending response to frontend script.

### Version 1.5.6 - 03.05.2019

* Fix - AJAX call arguments on checkout

### Version 1.5.5 - 03.05.2019

* Fix - Preserve certificate line breaks when options are saved.
* Fix - Corrected option name for cashier registration.

### Version 1.5.4 - 01.05.2019

* Fix - Added custom settings field for certificate password to prevent escaping of special chars on output.
* Tweak - Restrict certificate upload to administrator role.

### Version 1.5.3 - 26.04.2019

* Fix - Removed error message from admin and frontend if create_client fails.
* Tweak - Remove certificate field if upload and enrolment of certificate is successful.

### Version 1.5.2 - 25.04.2019

* Fix - Handle exception for status check and checkin in frontend.
* Fix - Delete certificate files on update and activation.

### Version 1.5.1 - 24.04.2019

* Fix - Correctly save test certificate in own file instead of overwriting prod certificate on plugin update.

### Version 1.5.0 - 23.04.2019

* Feature - Implemented TWINT browser to app to allow users to switch to the TWINT app on the checkout.
* Dev - Moved payment script to own js file.
* Dev - Moved payment_page.php into WC_Gateway_Twint.
* CSS - Smaller font for amount on mobile screens.

### Version 1.4.3 - 18.04.2019

* Fix - Enqueued wp media for the upload editor in the TWINT admin screen.
* Tweak - Admin styles.

### Version 1.4.2 - 17.04.2019

* Fix - Renamed certificate option name.
* Fix - Removed automatic cashier enrolment.

### Version 1.4.1 - 17.04.2019

* Fix - Correctly save previous certificates.

### Version 1.4.0 - 17.04.2019

* Feature - Upload field to upload certificate file in pkcs12 format and automatically convert it to PEM format.
* Tweak - Renamed CSS names to prevent conflicts.
* Tweak - Removed notice "connecting to twint" on checkout page.

### Version 1.3.5 - 11.04.2019

* Fix - Correctly check, activate and deactivate licenses of the current blog.

### Version 1.3.4 - 09.04.2019

* Fix - Correctly check activated licenses in multisite installations.

### Version 1.3.3 - 27.02.2019

* Fix - Check if certificate fields not empty if pkcs file cannot be converted to use existing certificate.

### Version 1.3.2 - 22.11.2018

* Fix - Use correct nonce for cashier register AJAX call.

### Version 1.3.1 - 20.11.2018

* Fix - Payment page styles.
* Tweak - Use Mame_Licensing and Mame_Plugin_Updater for license and updates.

### Version 1.3.0 - 17.08.2018

* Feature - Refunds.
* Tweak - Only show payment data metabox in TWINT orders.
* Tweak - Admin styles.

### Version 1.2.6 - 31.06.2018

* Fix - Allow failed orders to be paid.
* Fix - Remove unnecessary nonce in payment process.
* Fix - Correct license notice redirect.
* Tweak - Added translations.

### Version 1.2.5 - 14.04.2018

* Fix - Correct admin URL for network AJAX requests..

### Version 1.2.4 - 12.09.2017

* Fix - Wrong check to complete the order.

### Version 1.2.3 - 09.09.2017

* Fix - Round amount to two decimals.
* Fix - Replaced deprecated direct property access to order status.
* Fix - Close session before calling sleep.
* Fix - Correct update URL for multisite installations.

### Version 1.2.2 - 13.07.2017

* Fix - Payment handler proceeds with payment without AJAX requests.
* Fix - Unique MerchantTransactionReference on TWINT order start.
* Fix - Fixed no-js payment process.
* Tweak - Replaced deprecated WooCommerce update option hook.

### Version 1.2.1 - 12.06.2017

* Feature - Copy to clipboard button for mobile devices.
* Fix - AJAX handler to continue with unfulfilled payments.
* Fix - Replaced deprecated WooCommerce query args with endpoint.
* Tweak - CSS responsive styles.

### Version 1.2.0 - 01.05.2017

* Feature - Set timeout in settings.
* Update - New TWINT payment mask.
* Update - Localization.
* Fix - Enroll cash register on activation.

### Version 1.1.2 - 20.12.2016

* Fix - Bug on settings page that occurred if certificate is created from settings field content.

### Version 1.1.1 - 19.12.2016

* Fix - Fixed bug that occurred on plugin update.

### Version 1.1.0 - 27.11.2016

* Feature - Automatic certificate generation.
* Feature - System status section.
* Tweak - Save production and test params.
* Tweak - Automatic register enrollment.
* Fix - No register enrollment on plugin acivation.

### Version 1.0.4 - 28.07.2016

* Fix - Assets now loading correctly.
* Tweak - Regenerate key and enroll register on activation.

### Version 1.0.3 - 01.05.2016

* Fix - Renamed constants due to conflict with PF plugin.
* Fix - Renamed localized php variables due to conflict with PF plugin.

### Version 1.0.2 - 29.04.2016

* Fix - Renamed licensing settings field due to conflict with other plugin.

### Version 1.0.1 - 29.04.2016

* Fix- changed function to retrieve cart totals.
* Tweak - frontend styles.

### Version 1.0.0 - 29.04.2016

Release.