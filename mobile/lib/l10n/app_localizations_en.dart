// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'Royal Snacks';

  @override
  String get navHome => 'Home';

  @override
  String get navShop => 'Shop';

  @override
  String get navOrders => 'Orders';

  @override
  String get navSupport => 'Support';

  @override
  String get navAccount => 'Account';

  @override
  String get login => 'Log in';

  @override
  String get logout => 'Log out';

  @override
  String get register => 'Register your shop';

  @override
  String get mobileNumber => 'Mobile number';

  @override
  String get password => 'Password';

  @override
  String get confirmPassword => 'Confirm password';

  @override
  String get forgotPassword => 'Forgot password?';

  @override
  String get next => 'Next';

  @override
  String get back => 'Back';

  @override
  String get verifyAndContinue => 'Verify & continue';

  @override
  String get resendOtp => 'Resend OTP';

  @override
  String get search => 'Search products or brands';

  @override
  String get addToCart => 'Add';

  @override
  String get cart => 'Cart';

  @override
  String get cartEmpty => 'Your cart is empty';

  @override
  String get placeOrder => 'Place order';

  @override
  String get orderSummary => 'Order summary';

  @override
  String get payment => 'Payment';

  @override
  String get cashOnDelivery => 'Cash on delivery';

  @override
  String get payOnline => 'Pay online (sandbox)';

  @override
  String get payLater => 'Pay later (credit)';

  @override
  String get couponCode => 'Coupon code';

  @override
  String get apply => 'Apply';

  @override
  String get remove => 'Remove';

  @override
  String get total => 'Total';

  @override
  String get myOrders => 'My orders';

  @override
  String get noOrders => 'No orders yet';

  @override
  String get trackOrder => 'Track order';

  @override
  String get deliveryOtp => 'Delivery OTP';

  @override
  String get deliveryOtpHint => 'Share this code with the driver on delivery';

  @override
  String get cancelOrder => 'Cancel order';

  @override
  String get reorder => 'Reorder';

  @override
  String get downloadInvoice => 'Download GST invoice';

  @override
  String get reportProblem => 'Report a problem';

  @override
  String get offers => 'Offers & deals';

  @override
  String get volumeDiscounts => 'Volume discounts';

  @override
  String get support => 'Help & support';

  @override
  String get newTicket => 'New ticket';

  @override
  String get noTickets => 'No tickets yet';

  @override
  String get send => 'Send';

  @override
  String get reply => 'Your reply';

  @override
  String get notifications => 'Notifications';

  @override
  String get profile => 'Profile';

  @override
  String get language => 'Language';

  @override
  String get save => 'Save';

  @override
  String get cancel => 'Cancel';

  @override
  String get retry => 'Retry';

  @override
  String get pendingTitle => 'Awaiting approval';

  @override
  String get pendingBody =>
      'Our team is verifying your details. You can browse the catalogue now; ordering unlocks once you\'re approved.';

  @override
  String greeting(String name) {
    return 'Namaste, $name';
  }

  @override
  String minOrderHint(String amount, int cases) {
    return 'Minimum order $amount and $cases cases';
  }
}
