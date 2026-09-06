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
}
