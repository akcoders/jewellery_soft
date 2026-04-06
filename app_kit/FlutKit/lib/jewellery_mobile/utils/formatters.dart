import 'package:intl/intl.dart';

class AppFormatters {
  AppFormatters._();

  static final NumberFormat _amount = NumberFormat('#,##0.00');
  static final NumberFormat _quantity = NumberFormat('#,##0.###');
  static final DateFormat _dateTime = DateFormat('dd MMM yyyy, hh:mm a');
  static final DateFormat _date = DateFormat('dd MMM yyyy');

  static String amount(dynamic value) {
    final number = _toDouble(value);
    return _amount.format(number);
  }

  static String quantity(dynamic value) {
    final number = _toDouble(value);
    return _quantity.format(number);
  }

  static String dateTime(dynamic value) {
    final parsed = _parseDate(value);
    if (parsed == null) {
      return (value ?? '-').toString();
    }
    return _dateTime.format(parsed.toLocal());
  }

  static String date(dynamic value) {
    final parsed = _parseDate(value);
    if (parsed == null) {
      return (value ?? '-').toString();
    }
    return _date.format(parsed.toLocal());
  }

  static double _toDouble(dynamic value) {
    if (value is num) {
      return value.toDouble();
    }
    return double.tryParse((value ?? '').toString()) ?? 0;
  }

  static DateTime? _parseDate(dynamic value) {
    final raw = (value ?? '').toString().trim();
    if (raw.isEmpty) {
      return null;
    }
    return DateTime.tryParse(raw);
  }
}
