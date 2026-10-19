import 'package:flutter_test/flutter_test.dart';
import 'package:flutkit/main.dart';
import 'package:flutkit/jewellery_mobile/screens/app_shell.dart';
import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutter/material.dart';

void main() {
  testWidgets('shows login when there is no saved session', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const MyApp(initialSession: null));

    expect(find.text('JEWELLERY WORKSPACE'), findsOneWidget);
    await tester.pump(const Duration(milliseconds: 1600));
    await tester.pump();

    expect(find.text('Aabhushan ERP'), findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
  });

  testWidgets('system back returns to the previous app section', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AppShell(
          session: const MobileSession(
            baseUrl: 'https://example.test',
            token: 'test-token',
            userName: 'Test User',
            userEmail: 'test@example.com',
          ),
          onLogout: () async {},
        ),
      ),
    );
    await tester.pump();

    final scaffold = tester.state<ScaffoldState>(find.byType(Scaffold).first);
    scaffold.openDrawer();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Orders'));
    await tester.pumpAndSettle();

    expect(
      find.descendant(of: find.byType(AppBar), matching: find.text('Orders')),
      findsOneWidget,
    );

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();

    expect(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.text('Dashboard'),
      ),
      findsOneWidget,
    );

    await tester.binding.handlePopRoute();
    await tester.pump();
    expect(find.byType(AppShell), findsOneWidget);
  });

  testWidgets('android drawer matches the PWA workspace options for staff', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AppShell(
          session: const MobileSession(
            baseUrl: 'https://example.test',
            token: 'test-token',
            userName: 'Staff User',
            userEmail: 'staff@example.com',
            roleCodes: ['STAFF'],
          ),
          onLogout: () async {},
        ),
      ),
    );
    await tester.pump();

    final scaffold = tester.state<ScaffoldState>(find.byType(Scaffold).first);
    scaffold.openDrawer();
    await tester.pumpAndSettle();

    final drawer = find.byType(Drawer);
    for (final label in const [
      'Delivery Challans',
      'Packing Lists',
      'Customer & Karigar',
      'Issuements',
    ]) {
      await tester.dragUntilVisible(
        find.descendant(of: drawer, matching: find.text(label)),
        find.descendant(of: drawer, matching: find.byType(ListView)),
        const Offset(0, -120),
      );
      expect(
        find.descendant(of: drawer, matching: find.text(label)),
        findsOneWidget,
        reason: '$label should be listed in the Android drawer',
      );
    }
    expect(
      find.descendant(of: drawer, matching: find.text('Approval Desk')),
      findsNothing,
    );

    await tester.tap(
      find.descendant(of: drawer, matching: find.text('Packing Lists')),
    );
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.text('Packing Lists'),
      ),
      findsOneWidget,
    );
  });
}
