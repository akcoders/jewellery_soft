import 'package:flutkit/jewellery_mobile/session/mobile_session_store.dart';
import 'package:flutkit/jewellery_pwa/screens/app_shell.dart';
import 'package:flutkit/jewellery_pwa/screens/login_screen.dart';
import 'package:flutkit/jewellery_pwa/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

const _admin = MobileSession(
  baseUrl: 'https://example.test',
  token: 'test-token',
  userName: 'Workspace Admin',
  userEmail: 'admin@example.test',
  roleCodes: ['ADMIN'],
);

void _viewport(WidgetTester tester, Size size) {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

Widget _app(Widget child, {double textScale = 1}) => MaterialApp(
  theme: AppTheme.light(),
  builder: (context, content) => MediaQuery(
    data: MediaQuery.of(
      context,
    ).copyWith(textScaler: TextScaler.linear(textScale)),
    child: content!,
  ),
  home: child,
);

void main() {
  testWidgets('mobile navigation and drawer preserve section back history', (
    tester,
  ) async {
    _viewport(tester, const Size(390, 844));
    await tester.pumpWidget(
      _app(AppShell(session: _admin, onLogout: () async {})),
    );
    await tester.pumpAndSettle();
    expect(find.byType(NavigationBar), findsOneWidget);

    await tester.tap(
      find.descendant(
        of: find.byType(NavigationBar),
        matching: find.text('Orders'),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.descendant(of: find.byType(AppBar), matching: find.text('Orders')),
      findsOneWidget,
    );

    await tester.tap(find.text('More'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(
        of: find.byType(Drawer),
        matching: find.text('Followups'),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.text('Order Followups'),
      ),
      findsOneWidget,
    );

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(
      find.descendant(of: find.byType(AppBar), matching: find.text('Orders')),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('desktop sidebar opens staff tasks without mobile navigation', (
    tester,
  ) async {
    _viewport(tester, const Size(1280, 900));
    await tester.pumpWidget(
      _app(AppShell(session: _admin, onLogout: () async {})),
    );
    await tester.pumpAndSettle();
    expect(find.byType(NavigationBar), findsNothing);
    expect(find.byType(Drawer), findsNothing);
    await tester.tap(find.text('Staff Tasks'));
    await tester.pumpAndSettle();
    expect(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.text('Staff Tasks'),
      ),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  for (final size in [const Size(320, 740), const Size(1280, 900)]) {
    testWidgets('login remains usable at ${size.width}px and enlarged text', (
      tester,
    ) async {
      _viewport(tester, size);
      await tester.pumpWidget(
        _app(
          LoginScreen(
            onLoginSuccess:
                ({
                  required baseUrl,
                  required token,
                  required userName,
                  required userEmail,
                  required roleCodes,
                }) async {},
          ),
          textScale: 1.4,
        ),
      );
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Sign In'));
      await tester.tap(find.text('Sign In'));
      await tester.pumpAndSettle();
      expect(find.text('Email is required'), findsOneWidget);
      expect(find.text('Password is required'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  }
}
