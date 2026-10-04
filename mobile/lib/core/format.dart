import 'package:intl/intl.dart';

final _inr0 = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 0);
final _inr2 = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);

/// ₹1,23,456 (decimals only when the amount has paise).
String inr(num v) {
  final r = (v * 100).round();
  return r % 100 == 0 ? _inr0.format(v) : _inr2.format(v);
}

String trimNum(num v) => v == v.roundToDouble() ? v.toInt().toString() : v.toString();

final _dt = DateFormat('d MMM, h:mm a');
final _d = DateFormat('d MMM yyyy');
String fmtDateTime(DateTime? t) => t == null ? '—' : _dt.format(t.toLocal());
String fmtDate(DateTime? t) => t == null ? '—' : _d.format(t.toLocal());

String timeAgo(DateTime t) {
  final d = DateTime.now().difference(t.toLocal());
  if (d.inMinutes < 1) return 'just now';
  if (d.inMinutes < 60) return '${d.inMinutes}m ago';
  if (d.inHours < 24) return '${d.inHours}h ago';
  if (d.inDays < 7) return '${d.inDays}d ago';
  return fmtDate(t);
}

String statusLabelFallback(String s) => s.isEmpty ? s : s[0].toUpperCase() + s.substring(1).replaceAll('_', ' ');
