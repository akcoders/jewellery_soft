import 'package:flutkit/jewellery_mobile/widgets/order_full_details.dart'
    as mobile;
import 'package:flutkit/jewellery_pwa/widgets/order_full_details.dart' as pwa;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

const _order = <String, dynamic>{
  'order_no': 'ORD-26004',
  'status': 'In Production',
  'customer_name': 'Ankit Jain',
  'sales_person_name': 'Ravi Shah',
  'sales_person_mobile': '9876500000',
  'certificate_requirement': 'IGI',
  'approximate_price': '150000',
  'advance_amount': '50000',
  'gold_rate_block_status': 'Fixed',
  'gold_rate_per_gm': '7200',
  'order_notes': 'Wider motif',
};

const _stone = [
  {
    'id': 7,
    'voucher_no': 'ISS-77',
    'issue_date': '2026-10-14',
    'karigar_name': 'Imran',
    'total_pcs': 12,
    'total_qty': 1.5,
    'total_value': 900,
  },
];

Future<void> _expandAll(WidgetTester tester) async {
  for (final title in [
    'Customer & sales',
    'Payment & commercials',
    'Stone issued for this order',
  ]) {
    await tester.ensureVisible(find.text(title));
    await tester.tap(find.text(title));
    await tester.pumpAndSettle();
  }
}

void main() {
  for (final entry in <String, Widget>{
    'pwa': pwa.OrderFullDetails(order: _order, stoneIssues: _stone),
    'android': mobile.OrderFullDetails(order: _order, stoneIssues: _stone),
  }.entries) {
    testWidgets('${entry.key} order details show every section collapsed', (
      tester,
    ) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: SingleChildScrollView(child: entry.value)),
        ),
      );

      // Only the first section starts open; the rest are collapsed.
      expect(find.text('ORDER NO'), findsOneWidget);
      expect(find.text('Ravi Shah'), findsNothing);

      await _expandAll(tester);

      expect(find.text('Ravi Shah'), findsOneWidget);
      expect(find.text('9876500000'), findsOneWidget);
      expect(find.text('IGI'), findsOneWidget);
      expect(find.text('₹50,000.00'), findsOneWidget);
      expect(find.text('₹100,000.00'), findsOneWidget); // approx. balance
      expect(find.textContaining('Balance ₹1'), findsOneWidget);
      expect(find.text('ISS-77'), findsOneWidget);
    });
  }
}
